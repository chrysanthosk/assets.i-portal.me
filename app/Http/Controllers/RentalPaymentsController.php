<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\RentalPayment;
use App\Support\Audit;
use App\Support\Deeds\DeedExtractionException;
use App\Support\RentSchedule;
use App\Support\Statements\StatementExtractor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RentalPaymentsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status'); // pending | paid | overdue | not_received | null
        $rentalId = $request->integer('rental_id') ?: null;

        $payments = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->when($rentalId, fn ($q) => $q->where('asset_rental_id', $rentalId))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->when($status === 'not_received', fn ($q) => $q->where('status', RentalPayment::STATUS_NOT_RECEIVED))
            ->orderByDesc('due_date')
            ->paginate(20)
            ->withQueryString();

        $rentals = AssetRental::with(['asset', 'tenant'])->orderByDesc('id')->get();

        // Outstanding (unpaid) totals by currency, plus overdue / unconfirmed counts.
        $outstandingByCurrency = RentalPayment::query()
            ->where('status', '!=', RentalPayment::STATUS_PAID)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $overdueCount = RentalPayment::query()->overdue()->count();
        $unconfirmedCount = RentalPayment::query()->awaitingConfirmation()->count();

        return view('payments.index', compact(
            'payments', 'rentals', 'status', 'rentalId', 'outstandingByCurrency', 'overdueCount', 'unconfirmedCount'
        ));
    }

    /** Everything due where nobody has yet said whether the rent arrived. */
    public function unconfirmed()
    {
        $payments = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->awaitingConfirmation()
            ->orderBy('due_date')
            ->get();

        $notReceived = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->where('status', RentalPayment::STATUS_NOT_RECEIVED)
            ->orderBy('due_date')
            ->get();

        return view('payments.unconfirmed', [
            'payments' => $payments,
            'notReceived' => $notReceived,
            'remindersEnabled' => RentSchedule::enabled(),
            'recipients' => RentSchedule::recipients(),
            'repeatDays' => RentSchedule::repeatDays(),
        ]);
    }

    /** Create this month's expected payments now instead of waiting for the scheduler. */
    public function generate()
    {
        $created = RentSchedule::generateDue();

        return back()->with('success', $created
            ? "{$created} payment(s) generated for ".now()->format('F Y').'.'
            : 'Nothing to generate — this month\'s payments already exist.');
    }

    /** Send the confirmation email for every unconfirmed payment right now. */
    public function sendReminders()
    {
        if (RentSchedule::recipients() === []) {
            return back()->with('error', 'No reminder recipient configured. Set one under Settings → Portal.');
        }

        $sent = RentSchedule::sendReminders(null, true);

        return back()->with('success', $sent ? "{$sent} reminder(s) sent." : 'Nothing awaiting confirmation.');
    }

    /**
     * Correct the amount (variable rent, e.g. a short-let operator's monthly
     * payout) and optionally mark it received in the same step.
     */
    public function adjust(Request $request, RentalPayment $payment)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'paid_date' => ['nullable', 'date'],
            'received' => ['nullable', 'in:0,1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'statement' => ['nullable', 'json'],
        ]);

        $old = $payment->toArray();
        $payment->forceFill([
            'amount' => $data['amount'],
            'currency' => ($data['currency'] ?? null) ?: $payment->currency,
            'notes' => $data['notes'] ?? $payment->notes,
            'statement' => isset($data['statement']) ? json_decode($data['statement'], true) : $payment->statement,
        ])->save();

        if (($data['received'] ?? '0') === '1') {
            $payment->markReceived();
            if (! empty($data['paid_date'])) {
                $payment->forceFill(['paid_date' => $data['paid_date']])->save();
            }
        }

        Audit::log('rental_payment.adjusted', $payment, $old, $payment->fresh()->toArray());

        return back()->with('success', 'Payment updated'.((($data['received'] ?? '0') === '1') ? ' and marked as received.' : '.'));
    }

    /**
     * Read a manager's statement (PDF/image) and propose the net payout as the
     * payment amount. The figures are handed to the page via the session, where
     * a modal lets the user confirm or correct before anything is saved.
     */
    public function statement(Request $request, RentalPayment $payment, StatementExtractor $extractor)
    {
        $figures = $this->readStatement($request, $extractor, $payment->asset_id, $payment->periodLabel());
        if (! is_array($figures)) {
            return $figures; // redirect with error
        }

        return back()->with('statement', ['payment_id' => $payment->id, 'figures' => $figures]);
    }

    /**
     * Statement upload from the property page: no payment row needed. The
     * month is taken from the statement's period; the payment for that month
     * is found or created on the property's active agreement, then the same
     * confirm dialog opens.
     */
    public function statementForAsset(Request $request, Asset $asset, StatementExtractor $extractor)
    {
        $figures = $this->readStatement($request, $extractor, $asset->id, null);
        if (! is_array($figures)) {
            return $figures;
        }

        $rental = AssetRental::query()->where('asset_id', $asset->id)
            ->orderByDesc('is_active')->orderByDesc('agreement_start_date')->first();
        if (! $rental) {
            return redirect()->route('assets.show', [$asset, 'tab' => 'agreements'])
                ->with('error', 'Add an agreement for this property first (who manages it and the expected monthly amount), then upload the statement again.');
        }

        $start = ! empty($figures['period_start']) ? Carbon::parse($figures['period_start']) : now()->subMonth()->startOfMonth();
        $period = $start->format('Y-m');
        $payment = RentalPayment::query()->where('asset_rental_id', $rental->id)->where('period', $period)->first()
            ?? RentalPayment::create([
                'asset_rental_id' => $rental->id,
                'asset_id' => $asset->id,
                'due_date' => $start->copy()->endOfMonth()->addDay()->toDateString(),
                'period' => $period,
                'amount' => $figures['net_payable'] ?? $rental->amount,
                'currency' => $figures['currency'] ?? $rental->currency ?? 'EUR',
                'status' => RentalPayment::STATUS_PENDING,
            ]);

        return redirect()->route('assets.show', [$asset, 'tab' => 'payments'])
            ->with('statement', ['payment_id' => $payment->id, 'figures' => $figures]);
    }

    /**
     * Validate, store, and read a statement file. Returns the figures, or a
     * redirect response carrying the error.
     *
     * @return array<string, mixed>|RedirectResponse
     */
    private function readStatement(Request $request, StatementExtractor $extractor, int $assetId, ?string $periodLabel)
    {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp']]);

        if (! $extractor->isConfigured()) {
            return back()->with('error', 'No Anthropic API key configured. Add one under Settings → Portal.');
        }

        $file = $request->file('file');
        $path = $file->store("assets/{$assetId}", 'local');
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        try {
            $figures = $extractor->extract(Storage::disk('local')->path($path), $mime);
        } catch (DeedExtractionException $e) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'Could not read the statement: '.$e->getMessage());
        }

        $label = $periodLabel ?: (! empty($figures['period_start']) ? Carbon::parse($figures['period_start'])->format('F Y') : '');

        // Keep the statement with the property's documents
        $doc = AssetDocument::create([
            'asset_id' => $assetId,
            'uploaded_by' => auth()->id(),
            'title' => trim('Statement '.$label),
            'doc_type' => 'Statement',
            'notes' => trim(($figures['management_company'] ?? '').' '.($figures['period_start'] ?? '').' → '.($figures['period_end'] ?? '')),
            'original_name' => mb_substr(basename(str_replace('\\', '/', (string) $file->getClientOriginalName())), 0, 255) ?: 'statement',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
        ]);
        Audit::log('asset_document.uploaded', $doc, null, $doc->toArray());

        $figures['document_id'] = $doc->id;

        return $figures;
    }

    public function markNotReceived(RentalPayment $payment)
    {
        $old = $payment->toArray();
        $payment->markNotReceived();

        Audit::log('rental_payment.not_received', $payment, $old, $payment->fresh()->toArray());

        return back()->with('success', 'Payment flagged as not received.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_rental_id' => ['required', 'integer', 'exists:asset_rentals,id'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'paid_date' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rental = AssetRental::findOrFail($data['asset_rental_id']);

        $payment = RentalPayment::create([
            'asset_rental_id' => $rental->id,
            'asset_id' => $rental->asset_id,
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'paid_date' => $data['paid_date'] ?? null,
            'status' => ! empty($data['paid_date']) ? 'paid' : 'pending',
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::log('rental_payment.created', $payment, null, $payment->toArray());

        return back()->with('success', 'Payment recorded.');
    }

    public function markPaid(Request $request, RentalPayment $payment)
    {
        $old = $payment->toArray();

        $payment->markReceived($request->input('method'));

        Audit::log('rental_payment.marked_paid', $payment, $old, $payment->fresh()->toArray());

        return back()->with('success', 'Payment marked as paid.');
    }

    public function destroy(RentalPayment $payment)
    {
        $old = $payment->toArray();
        $payment->delete();

        Audit::log('rental_payment.deleted', $payment, $old, null);

        return back()->with('success', 'Payment deleted.');
    }
}
