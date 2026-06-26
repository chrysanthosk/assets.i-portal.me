<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for columns that are filtered/sorted frequently:
 * - assets.status        (dashboard occupancy buckets + search)
 * - assets.created_at     (default list ordering)
 * - audit_logs.action/entity/created_at (audit log filtering)
 * - asset_rentals(asset_id, is_active)  (dashboard active-agreement scan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->index('status', 'assets_status_index');
            $table->index('created_at', 'assets_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action', 'audit_logs_action_index');
            $table->index('entity', 'audit_logs_entity_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });

        if (Schema::hasColumn('asset_rentals', 'is_active')) {
            Schema::table('asset_rentals', function (Blueprint $table) {
                $table->index(['asset_id', 'is_active'], 'asset_rentals_asset_active_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('assets_status_index');
            $table->dropIndex('assets_created_at_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_index');
            $table->dropIndex('audit_logs_entity_index');
            $table->dropIndex('audit_logs_created_at_index');
        });

        if (Schema::hasColumn('asset_rentals', 'is_active')) {
            Schema::table('asset_rentals', function (Blueprint $table) {
                $table->dropIndex('asset_rentals_asset_active_index');
            });
        }
    }
};
