<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\Tenant;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AssetRentalsController extends Controller
{
    public function index(Request $request)
    {
        $assetId = $request->integer('asset_id') ?: null;

        $assets = Asset::orderBy('name')->get();
        $tenants = Tenant::orderBy('name')->get();

        $rentals = AssetRental::query()
            ->with(['asset', 'tenant'])
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('assets.rentals', compact('assets', 'tenants', 'assetId', 'rentals'));
    }

    /**
     * Create a new agreement (monthly amount).
     * NOTE: asset_rentals table requires year+month (legacy), so we derive them from agreement_start_date.
     */
    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],

            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
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

        $tenantName = $this->resolveTenantName($data);

        $start = Carbon::parse($data['agreement_start_date']);
        $year = (int) $start->format('Y');
        $month = (int) $start->format('m');

        $rental = AssetRental::create([
            'asset_id' => $data['asset_id'],
            'tenant_id' => $data['tenant_id'] ?? null,

            // legacy required columns
            'year' => $year,
            'month' => $month,

            'tenant_name' => $tenantName,
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
        $tenants = Tenant::orderBy('name')->get();

        return view('assets.rentals_edit', [
            'rental' => $rental,
            'assets' => $assets,
            'tenants' => $tenants,
        ]);
    }

    public function update(Request $request, AssetRental $rental)
    {
        $old = $rental->toArray();

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],

            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
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

        $tenantName = $this->resolveTenantName($data);

        $start = Carbon::parse($data['agreement_start_date']);
        $year = (int) $start->format('Y');
        $month = (int) $start->format('m');

        $rental->update([
            'asset_id' => $data['asset_id'],
            'tenant_id' => $data['tenant_id'] ?? null,

            // keep legacy required columns consistent
            'year' => $year,
            'month' => $month,

            'tenant_name' => $tenantName,
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

    /**
     * Keep the legacy free-text tenant_name in sync: prefer the linked tenant's
     * name when one is selected, otherwise fall back to the typed value.
     */
    private function resolveTenantName(array $data): ?string
    {
        if (! empty($data['tenant_id'])) {
            return Tenant::find($data['tenant_id'])?->name ?? ($data['tenant_name'] ?? null);
        }

        return $data['tenant_name'] ?? null;
    }

    public function destroy(AssetRental $rental)
    {
        $old = $rental->toArray();

        $rental->delete();

        Audit::log('asset_rental.deleted', $rental, $old, null);

        return back()->with('success', 'Agreement deleted.');
    }
}
