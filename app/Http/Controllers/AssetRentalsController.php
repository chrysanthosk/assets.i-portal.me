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
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('assets.rentals', compact('assets', 'assetId', 'rentals'));
    }

    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required','integer','exists:assets,id'],
            'year' => ['required','integer','min:2000','max:2100'],
            'month' => ['required','integer','min:1','max:12'],

            'agreement_start_date' => ['nullable','date'],
            'agreement_end_date' => ['nullable','date','after_or_equal:agreement_start_date'],
            'tenant_name' => ['nullable','string','max:120'],
            'rent_type' => ['nullable','in:Airbnb,Long-term,Other'],
            'is_active' => ['required','in:0,1'],

            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','max:10'],
            'channel' => ['nullable','string','max:100'],
            'notes' => ['nullable','string'],
        ]);

        AssetRental::updateOrCreate(
            [
                'asset_id' => $data['asset_id'],
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            [
                'agreement_start_date' => $data['agreement_start_date'] ?? null,
                'agreement_end_date' => $data['agreement_end_date'] ?? null,
                'tenant_name' => $data['tenant_name'] ?? null,
                'rent_type' => $data['rent_type'] ?? null,
                'is_active' => ((int)$data['is_active'] === 1),

                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'channel' => $data['channel'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Rental record saved.');
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
            'year' => ['required','integer','min:2000','max:2100'],
            'month' => ['required','integer','min:1','max:12'],

            'agreement_start_date' => ['nullable','date'],
            'agreement_end_date' => ['nullable','date','after_or_equal:agreement_start_date'],
            'tenant_name' => ['nullable','string','max:120'],
            'rent_type' => ['nullable','in:Airbnb,Long-term,Other'],
            'is_active' => ['required','in:0,1'],

            'amount' => ['required','numeric','min:0'],
            'currency' => ['required','string','max:10'],
            'channel' => ['nullable','string','max:100'],
            'notes' => ['nullable','string'],
        ]);

        $exists = AssetRental::query()
            ->where('id', '!=', $rental->id)
            ->where('asset_id', $data['asset_id'])
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'A rental record already exists for that asset and period (year/month).');
        }

        $rental->update([
            'asset_id' => $data['asset_id'],
            'year' => $data['year'],
            'month' => $data['month'],

            'agreement_start_date' => $data['agreement_start_date'] ?? null,
            'agreement_end_date' => $data['agreement_end_date'] ?? null,
            'tenant_name' => $data['tenant_name'] ?? null,
            'rent_type' => $data['rent_type'] ?? null,
            'is_active' => ((int)$data['is_active'] === 1),

            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'channel' => $data['channel'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('assets.rentals.index')->with('success', 'Rental record updated.');
    }

    public function destroy(AssetRental $rental)
    {
        $rental->delete();
        return back()->with('success', 'Rental record deleted.');
    }
}
