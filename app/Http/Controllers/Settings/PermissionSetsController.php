<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSetsController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        // Display labels from config if available
        $labels = config('portal_permissions', []);

        return view('settings.permission_sets', [
            'roles' => Role::orderBy('name')->get(),
            'permissions' => $permissions,
            'labels' => $labels,
        ]);
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'role_name' => ['required','string','max:50','unique:roles,name'],
        ]);

        $role = Role::create(['name' => $data['role_name']]);

        // default permissions: dashboard only
        $role->syncPermissions(['view_dashboard']);

        return back()->with('success', 'Permission set created (dashboard access only by default).');
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        $allPermissionNames = Permission::pluck('name')->all();
        $selected = $request->input('permissions', []);

        // Ensure only valid permissions
        $selected = array_values(array_intersect($selected, $allPermissionNames));

        $role->syncPermissions($selected);

        return back()->with('success', 'Permissions updated for '.$role->name.'.');
    }

    public function destroyRole(Role $role)
    {
        if (in_array($role->name, ['Admin', 'User'], true)) {
            return back()->with('error', 'Default permission sets cannot be deleted.');
        }

        $role->delete();

        return back()->with('success', 'Permission set deleted.');
    }
}
