<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRental;
use Illuminate\Http\Request;

class AssetRentalsController extends Controller
{
    public function index(Request $request)
    {
        $assetId = $request->integer('asset_id') ?: null;

        $assets = Asset::orderBy('name')->get();

        $rentals = AssetRental::query()
            ->with('asset')
            ->when($assetId, fn($q) => $q->where('asset_id', $assetId))
            ->orderByRaw("CASE WHEN agreement_start_date IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy('agreement_start_date', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('assets.rentals', compact('assets', 'assetId', 'rentals'));
    }

    /**
     * Create a new agreement (monthly amount).
     */
    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required','integer','exists:assets,id'],

            'tenant_name' => ['nullable','string','max:120'],
            'agreement_start_date' => ['required','date'],
            'agreement_end_date' => ['nullable','date','after_or_equal:agreement_start_date'],
            'rent_type' => ['required','in:Airbnb,Long-term,Other'],
            'is_active' => ['required','in:0,1'],

            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','max:10'],
            'channel' => ['nullable','string','max:100'],
            'notes' => ['nullable','string'],
        ]);

        AssetRental::create([
            'asset_id' => $data['asset_id'],

            'tenant_name' => $data['tenant_name'] ?? null,
            'agreement_start_date' => $data['agreement_start_date'],
            'agreement_end_date' => $data['agreement_end_date'] ?? null,
            'rent_type' => $data['rent_type'],
            'is_active' => (int)$data['is_active'] === 1,

            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'channel' => $data['channel'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

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
        $data = $request->validate([
            'asset_id' => ['required','integer','exists:assets,id'],

            'tenant_name' => ['nullable','string','max:120'],
            'agreement_start_date' => ['required','date'],
            'agreement_end_date' => ['nullable','date','after_or_equal:agreement_start_date'],
            'rent_type' => ['required','in:Airbnb,Long-term,Other'],
            'is_active' => ['required','in:0,1'],

            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','max:10'],
            'channel' => ['nullable','string','max:100'],
            'notes' => ['nullable','string'],
        ]);

        $rental->update([
            'asset_id' => $data['asset_id'],

            'tenant_name' => $data['tenant_name'] ?? null,
            'agreement_start_date' => $data['agreement_start_date'],
            'agreement_end_date' => $data['agreement_end_date'] ?? null,
            'rent_type' => $data['rent_type'],
            'is_active' => (int)$data['is_active'] === 1,

            'amount' => $data['amount'],
            'currency' => $data['currency'],

            'channel' => $data['channel'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('assets.rentals.index')->with('success', 'Agreement updated.');
    }

    public function destroy(AssetRental $rental)
    {
        $rental->delete();
        return back()->with('success', 'Agreement deleted.');
    }
}
