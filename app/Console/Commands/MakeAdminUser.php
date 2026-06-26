<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MakeAdminUser extends Command
{
    protected $signature = 'make:admin
        {--name= : Admin full name}
        {--email= : Admin email}
        {--username= : Admin username (optional; defaults from email/name)}
        {--password= : Admin password (min 10 chars)}
        {--force : Update existing user if found by email/username}';

    protected $description = 'Create (or update) an Admin user and assign the Admin role';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Admin name', 'Admin'));
        $email = (string) ($this->option('email') ?: $this->ask('Admin email'));
        $usernameOpt = (string) ($this->option('username') ?: '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: '');
        if ($password === '') {
            $password = (string) $this->secret('Admin password (min 10 chars)');
            $confirm = (string) $this->secret('Confirm password');

            if ($password !== $confirm) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        if (mb_strlen($password) < 10) {
            $this->error('Validation failed: The password field must be at least 10 characters.');

            return self::FAILURE;
        }

        // Build username if not provided
        $baseUsername = $usernameOpt !== ''
            ? $this->sanitizeUsername($usernameOpt)
            : $this->sanitizeUsername(Str::before($email, '@'));

        if ($baseUsername === '') {
            $baseUsername = $this->sanitizeUsername($name);
        }
        if ($baseUsername === '') {
            $baseUsername = 'admin';
        }

        $force = (bool) $this->option('force');

        // Find existing user by email or username
        $user = User::query()
            ->where('email', $email)
            ->orWhere('username', $baseUsername)
            ->first();

        if ($user && ! $force) {
            $this->error("User already exists (id={$user->id}). Re-run with --force to update.");

            return self::FAILURE;
        }

        // Ensure unique username (important when user exists with same username or collisions)
        $username = $this->uniqueUsername($baseUsername, $user?->id);

        // Create/update
        if (! $user) {
            $user = new User;
        }

        $user->name = $name;
        $user->email = $email;
        $user->username = $username; // <- FIX for your MySQL error
        $user->password = Hash::make($password);

        $user->save();

        // Ensure Admin role exists, assign it
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->info('Admin user ready:');
        $this->line(" - id: {$user->id}");
        $this->line(" - name: {$user->name}");
        $this->line(" - email: {$user->email}");
        $this->line(" - username: {$user->username}");
        $this->line(' - role: Admin');

        return self::SUCCESS;
    }

    private function sanitizeUsername(string $value): string
    {
        $v = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/i', '_')
            ->trim('_')
            ->limit(30, '');

        return (string) $v;
    }

    private function uniqueUsername(string $base, ?int $ignoreUserId = null): string
    {
        $candidate = $base;
        $i = 0;

        while (true) {
            $q = User::query()->where('username', $candidate);
            if ($ignoreUserId) {
                $q->where('id', '!=', $ignoreUserId);
            }

            if (! $q->exists()) {
                return $candidate;
            }

            $i++;
            $suffix = (string) $i;
            $maxBaseLen = max(1, 30 - (1 + strlen($suffix))); // 30 total; "_" + suffix
            $candidate = Str::limit($base, $maxBaseLen, '').'_'.$suffix;
        }
    }
}
