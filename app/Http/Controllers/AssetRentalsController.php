<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRental;
use App\Models\Tenant;
use App\Support\Audit;
use App\Support\RentSchedule;
use Illuminate\Http\Request;

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
            ->orderByDesc('agreement_start_date')
            ->orderByDesc('id')
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
        $rental = $this->persist($request, new AssetRental);
        Audit::log('asset_rental.created', $rental, null, $rental->toArray());
        $n = RentSchedule::backfill($rental);

        return back()->with('success', 'Agreement saved.'.($n ? " {$n} payment(s) due this year were added to the rent check." : ''));
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
        $this->persist($request, $rental);
        Audit::log('asset_rental.updated', $rental, $old, $rental->fresh()->toArray());
        $n = RentSchedule::backfill($rental->fresh());

        return redirect()->route('assets.rentals.index')->with('success', 'Agreement updated.'.($n ? " {$n} payment(s) due this year were added to the rent check." : ''));
    }

    /** Validate and save an agreement (create or update). */
    public function persistFromRequest(Request $request, AssetRental $rental): AssetRental
    {
        return $this->persist($request, $rental);
    }

    private function persist(Request $request, AssetRental $rental): AssetRental
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_name' => ['nullable', 'string', 'max:120'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
            'tenant_phone' => ['nullable', 'string', 'max:60'],
            'tenant_id_number' => ['nullable', 'string', 'max:120'],
            'agreement_start_date' => ['required', 'date'],
            'agreement_end_date' => ['nullable', 'date', 'after_or_equal:agreement_start_date'],
            'rent_type' => ['required', 'in:Airbnb,Long-term,Other'],
            'is_active' => ['required', 'in:0,1'],
            'payment_schedule' => ['nullable', 'in:monthly,installments'],
            'paid_in_arrears' => ['nullable', 'in:0,1'],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_if:payment_schedule,monthly'],
            'installments' => ['nullable', 'array', 'required_if:payment_schedule,installments'],
            'installments.*.day' => ['required', 'integer', 'min:1', 'max:31'],
            'installments.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'installments.*.amount' => ['required', 'numeric', 'min:0'],
            'installments.*.label' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'max:10'],
            'channel' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ], [
            'installments.required_if' => 'Add at least one instalment.',
            'amount.required_if' => 'Enter the monthly amount.',
        ]);

        $data['payment_schedule'] = $data['payment_schedule'] ?? 'monthly';
        $data['paid_in_arrears'] = $data['payment_schedule'] === 'monthly' && ($data['paid_in_arrears'] ?? '0') === '1';
        if ($data['payment_schedule'] === 'installments') {
            $data['installments'] = array_values(array_map(fn ($i) => [
                'day' => (int) $i['day'], 'month' => (int) $i['month'], 'amount' => (float) $i['amount'], 'label' => $i['label'] ?? null,
            ], $data['installments']));
            $data['amount'] = array_sum(array_column($data['installments'], 'amount')); // annual total
        } else {
            $data['installments'] = null;
            $data['amount'] = $data['amount'] ?? 0;
        }

        // Linked tenant wins; a typed name finds or creates a tenant record so the
        // person exists once and shows up under Tenants.
        if (! empty($data['tenant_id'])) {
            $data['tenant_name'] = Tenant::find($data['tenant_id'])?->name ?? ($data['tenant_name'] ?? null);
        } elseif (! empty(trim((string) ($data['tenant_name'] ?? '')))) {
            $name = trim($data['tenant_name']);
            $tenant = Tenant::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? Tenant::create(['name' => $name, 'email' => $data['tenant_email'] ?? null,
                    'phone' => $data['tenant_phone'] ?? null, 'id_number' => $data['tenant_id_number'] ?? null]);
            $data['tenant_id'] = $tenant->id;
            $data['tenant_name'] = $tenant->name;
        } else {
            $data['tenant_id'] = null;
            $data['tenant_name'] = null;
        }
        $data['agreement_end_date'] = $data['agreement_end_date'] ?? null;
        $data['is_active'] = (int) $data['is_active'] === 1;

        unset($data['tenant_email'], $data['tenant_phone'], $data['tenant_id_number']);
        $rental->fill($data)->save();

        return $rental;
    }

    public function destroy(AssetRental $rental)
    {
        $old = $rental->toArray();

        $rental->delete();

        Audit::log('asset_rental.deleted', $rental, $old, null);

        return back()->with('success', 'Agreement deleted.');
    }
}
