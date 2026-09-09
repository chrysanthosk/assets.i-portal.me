<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index()
    {
        return view('settings.users.index', [
            'users' => User::orderByDesc('id')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('settings.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'name' => $data['name'],
            'surname' => $data['surname'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('settings.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('settings.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'currentRole' => $user->getRoleNames()->first(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->name = $data['name'];
        $user->surname = $data['surname'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->two_factor_enabled = false;
            $user->two_factor_secret = null;
        }

        $admin = (string) config('portal.admin_role', 'Admin');
        if ($user->hasRole($admin) && $data['role'] !== $admin && User::role($admin)->count() <= 1) {
            return back()->withInput()->with('error', 'This is the only '.$admin.' account; give another user the '.$admin.' role first.');
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('settings.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'You cannot delete your own user.');
        }
        $admin = (string) config('portal.admin_role', 'Admin');
        if ($user->hasRole($admin) && User::role($admin)->count() <= 1) {
            return back()->with('error', 'This is the only '.$admin.' account; it cannot be deleted.');
        }

        $user->delete();

        return redirect()->route('settings.users.index')->with('success', 'User deleted.');
    }
}
