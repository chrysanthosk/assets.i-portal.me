<?php

namespace App\Http\Controllers;

use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\Tenant;
use App\Support\Agreements\AgreementExtractor;
use App\Support\Audit;
use App\Support\Deeds\DeedExtractionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $tenants = Tenant::query()
            ->withCount('rentals')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('tenants.index', compact('tenants', 'q'));
    }

    /**
     * Catch up tenants from existing agreements and their filed contracts:
     *  1) agreements with only a typed name get a Tenant record (found or created)
     *  2) tenants missing email/phone/ID are filled from the newest 'Contract'
     *     document on the property, read with the agreement extractor.
     */
    public function syncFromContracts(AgreementExtractor $extractor)
    {
        $linked = 0;
        foreach (AssetRental::query()->whereNull('tenant_id')->whereNotNull('tenant_name')->get() as $rental) {
            $name = trim((string) $rental->tenant_name);
            if ($name === '') {
                continue;
            }
            $tenant = Tenant::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? Tenant::create(['name' => $name]);
            $rental->forceFill(['tenant_id' => $tenant->id])->save();
            $linked++;
        }

        $filled = 0;
        $errors = [];
        if ($extractor->isConfigured()) {
            $needing = Tenant::query()->with('rentals')->get()
                ->filter(fn ($t) => ! $t->email || ! $t->phone || ! $t->id_number);
            foreach ($needing as $tenant) {
                $assetIds = $tenant->rentals->pluck('asset_id')->unique()->all();
                $doc = AssetDocument::query()->whereIn('asset_id', $assetIds)->where('doc_type', 'Contract')->latest()->first();
                if (! $doc || ! Storage::disk($doc->disk ?: 'local')->exists($doc->path)) {
                    continue;
                }
                try {
                    $terms = $extractor->extract(Storage::disk($doc->disk ?: 'local')->path($doc->path), $doc->mime_type);
                } catch (DeedExtractionException $e) {
                    $errors[] = $tenant->name.': '.$e->getMessage();

                    continue;
                }
                $changes = array_filter([
                    'email' => $tenant->email ?: ($terms['counterparty_email'] ?? null),
                    'phone' => $tenant->phone ?: ($terms['counterparty_phone'] ?? null),
                    'id_number' => $tenant->id_number ?: ($terms['counterparty_id_number'] ?? null),
                ]);
                if ($changes && array_diff_assoc($changes, $tenant->only(array_keys($changes)))) {
                    $old = $tenant->toArray();
                    $tenant->fill($changes)->save();
                    Audit::log('tenant.filled_from_contract', $tenant, $old, $tenant->fresh()->toArray());
                    $filled++;
                }
            }
        }

        $msg = "{$linked} agreement(s) linked to tenants, {$filled} tenant(s) filled from contracts.";
        if (! $extractor->isConfigured()) {
            $msg .= ' Contact details were not read: no Anthropic API key configured.';
        }

        return back()->with($errors ? 'error' : 'success', $msg.($errors ? ' Problems: '.implode(' · ', $errors) : ''));
    }

    public function store(Request $request)
    {
        $data = $this->validateTenant($request);

        $tenant = Tenant::create($data);

        Audit::log('tenant.created', $tenant, null, $tenant->toArray());

        return back()->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $old = $tenant->toArray();

        $tenant->update($this->validateTenant($request));

        Audit::log('tenant.updated', $tenant, $old, $tenant->fresh()->toArray());

        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $old = $tenant->toArray();

        // Rentals keep their historical tenant_name; the FK is nulled on delete.
        $tenant->delete();

        Audit::log('tenant.deleted', $tenant, $old, null);

        return back()->with('success', 'Tenant deleted.');
    }

    private function validateTenant(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
