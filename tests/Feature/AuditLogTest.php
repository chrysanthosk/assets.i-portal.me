<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_mutations_write_an_audit_record(): void
    {
        $user = $this->userWith('manage_tenants');

        $this->actingAs($user)->post('/tenants', ['name' => 'Audited Tenant']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'tenant.created',
            'entity' => 'Tenant',
        ]);
    }

    public function test_audit_log_page_requires_permission(): void
    {
        $this->get('/audit-logs')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/audit-logs')->assertForbidden();
        $this->actingAs($this->userWith('manage_audit_logs'))->get('/audit-logs')->assertOk();
    }
}
