<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Portal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdvancedModeTest extends TestCase
{
    use RefreshDatabase;

    private function adminLike(): User
    {
        $user = User::factory()->create();
        foreach (['view_dashboard', 'manage_assets', 'manage_users', 'manage_permission_sets', 'manage_owner_entities',
            'manage_asset_tags', 'manage_fx_rates', 'manage_audit_logs', 'manage_portal_settings', 'manage_smtp_settings'] as $p) {
            Permission::findOrCreate($p, 'web');
            $user->givePermissionTo($p);
        }

        return $user;
    }

    public function test_advanced_modules_are_hidden_by_default_and_shown_when_enabled(): void
    {
        $user = $this->adminLike();

        $this->assertFalse(Portal::advanced());

        $page = $this->actingAs($user)->get('/dashboard')->assertOk();
        $page->assertSee('Portal &amp; reminders', false)->assertSee('Email (SMTP)');
        foreach (['settings/users', 'settings/permission-sets', 'settings/owner-entities', 'assets/tags', 'settings/currencies', '/audit-logs'] as $href) {
            $page->assertDontSee($href);
        }

        $this->actingAs($user)->post('/settings/portal', [
            'portal_name' => 'P', 'rent_due_day' => 1, 'rent_reminder_repeat_days' => 3, 'advanced_mode' => '1',
        ])->assertSessionHas('success');
        Portal::flush();
        $this->assertTrue(Portal::advanced());

        $page = $this->actingAs($user)->get('/dashboard')->assertOk();
        foreach (['settings/users', 'settings/permission-sets', 'settings/owner-entities', 'assets/tags', 'settings/currencies', '/audit-logs'] as $href) {
            $page->assertSee($href);
        }
    }

    public function test_hidden_pages_remain_reachable_by_permission(): void
    {
        $user = $this->adminLike();
        $this->assertFalse(Portal::advanced());

        $this->actingAs($user)->get('/settings/users')->assertOk();
        $this->actingAs($user)->get('/audit-logs')->assertOk();
    }

    public function test_asset_form_hides_owner_entity_and_tags_in_simple_mode(): void
    {
        $user = $this->adminLike();
        \App\Models\AssetType::create(['name' => 'Apartment', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($user)->get('/assets/create')->assertOk()
            ->assertDontSee('Owner entity')->assertDontSee('name="tags[]"', false);

        Portal::setAdvanced(true);
        $this->actingAs($user)->get('/assets/create')->assertOk()->assertSee('Owner entity');
    }
}
