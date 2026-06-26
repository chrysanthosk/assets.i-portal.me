<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AssetRentalsController extends Controller
{
    public function index(Request $request)
    {
        $assetId = $request->integer('asset_id') ?: null;

        $assets = Asset::orderBy('name')->get();

        $rentals = AssetRental::query()
            ->with('asset')
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('assets.rentals', compact('assets', 'assetId', 'rentals'));
    }

    /**
     * Create a new agreement (monthly amount).
     * NOTE: asset_rentals table requires year+month (legacy), so we derive them from agreement_start_date.
     */
    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],

            'tenant_name' => ['nullable', 'string', 'max:120'],
            'agreement_start_date' => ['required', 'date'],
            'agreement_end_date' => ['nullable', 'date', 'after_or_equal:agreement_start_date'],
            'rent_type' => ['required', 'in:Airbnb,Long-term,Other'],
            'is_active' => ['required', 'in:0,1'],

            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'channel' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($data['agreement_start_date']);
        $year = (int) $start->format('Y');
        $month = (int) $start->format('m');

        $rental = AssetRental::create([
            'asset_id' => $data['asset_id'],

            // legacy required columns
            'year' => $year,
            'month' => $month,

            'tenant_name' => $data['tenant_name'] ?? null,
            'agreement_start_date' => $data['agreement_start_date'],
            'agreement_end_date' => $data['agreement_end_date'] ?? null,
            'rent_type' => $data['rent_type'],
            'is_active' => (int) $data['is_active'] === 1,

            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'channel' => $data['channel'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::log('asset_rental.created', $rental, null, $rental->toArray());

        return back()->with('success', 'Agreement saved.');
    }

    public function edit(AssetRental $rental)
    {
        $assets = Asset::orderBy('name')->get();

        return view('assets.rentals_edit', [
            'rental' => $rental,
            'assets' => $assets,
        ]);
    }

    public function update(Request $request, AssetRental $rental)
    {
        $old = $rental->toArray();

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],

            'tenant_name' => ['nullable', 'string', 'max:120'],
            'agreement_start_date' => ['required', 'date'],
            'agreement_end_date' => ['nullable', 'date', 'after_or_equal:agreement_start_date'],
            'rent_type' => ['required', 'in:Airbnb,Long-term,Other'],
            'is_active' => ['required', 'in:0,1'],

            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'channel' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($data['agreement_start_date']);
        $year = (int) $start->format('Y');
        $month = (int) $start->format('m');

        $rental->update([
            'asset_id' => $data['asset_id'],

            // keep legacy required columns consistent
            'year' => $year,
            'month' => $month,

            'tenant_name' => $data['tenant_name'] ?? null,
            'agreement_start_date' => $data['agreement_start_date'],
            'agreement_end_date' => $data['agreement_end_date'] ?? null,
            'rent_type' => $data['rent_type'],
            'is_active' => (int) $data['is_active'] === 1,

            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'channel' => $data['channel'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::log('asset_rental.updated', $rental, $old, $rental->fresh()->toArray());

        return redirect()->route('assets.rentals.index')->with('success', 'Agreement updated.');
    }

    public function destroy(AssetRental $rental)
    {
        $old = $rental->toArray();

        $rental->delete();

        Audit::log('asset_rental.deleted', $rental, $old, null);

        return back()->with('success', 'Agreement deleted.');
    }
}
