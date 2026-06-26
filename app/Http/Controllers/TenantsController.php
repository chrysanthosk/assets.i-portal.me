<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\Audit;
use Illuminate\Http\Request;

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
