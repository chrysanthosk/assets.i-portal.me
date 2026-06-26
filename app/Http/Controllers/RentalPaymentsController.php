<?php

namespace App\Http\Controllers;

use App\Models\AssetRental;
use App\Models\RentalPayment;
use App\Support\Audit;
use Illuminate\Http\Request;

class RentalPaymentsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status'); // pending | paid | overdue | null
        $rentalId = $request->integer('rental_id') ?: null;

        $payments = RentalPayment::query()
            ->with(['asset', 'rental.tenant'])
            ->when($rentalId, fn ($q) => $q->where('asset_rental_id', $rentalId))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($status === 'overdue', fn ($q) => $q->where('status', 'pending')->whereDate('due_date', '<', now()->toDateString()))
            ->orderByDesc('due_date')
            ->paginate(20)
            ->withQueryString();

        $rentals = AssetRental::with(['asset', 'tenant'])->orderByDesc('id')->get();

        // Outstanding (pending) totals by currency, plus overdue count.
        $outstandingByCurrency = RentalPayment::query()
            ->where('status', 'pending')
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $overdueCount = RentalPayment::where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return view('payments.index', compact(
            'payments', 'rentals', 'status', 'rentalId', 'outstandingByCurrency', 'overdueCount'
        ));
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

        $payment->update([
            'paid_date' => now()->toDateString(),
            'status' => 'paid',
            'method' => $request->input('method', $payment->method),
        ]);

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
