<?php

namespace App\Http\Controllers;

use App\Models\AssetRental;
use App\Models\RentalPayment;
use App\Support\Audit;
use App\Support\RentSchedule;
use Illuminate\Http\Request;

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
