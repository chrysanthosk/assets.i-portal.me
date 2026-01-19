<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite "duplicate column" happens if you re-run migrations in odd states.
        // We guard each column using PRAGMA table_info().
        $table = 'asset_rentals';
        $existing = collect(DB::select("PRAGMA table_info('$table')"))->pluck('name')->all();

        Schema::table($table, function (Blueprint $tableBlueprint) use ($existing) {

            if (!in_array('agreement_start_date', $existing, true)) {
                $tableBlueprint->date('agreement_start_date')->nullable()->after('month');
            }

            if (!in_array('agreement_end_date', $existing, true)) {
                $tableBlueprint->date('agreement_end_date')->nullable()->after('agreement_start_date');
            }

            if (!in_array('tenant_name', $existing, true)) {
                $tableBlueprint->string('tenant_name', 255)->nullable()->after('agreement_end_date');
            }

            if (!in_array('rent_type', $existing, true)) {
                // Airbnb / Long-term (string to keep it flexible)
                $tableBlueprint->string('rent_type', 30)->nullable()->after('tenant_name');
            }

            // Optional but useful for dashboard logic:
            if (!in_array('is_active', $existing, true)) {
                $tableBlueprint->boolean('is_active')->default(true)->after('rent_type');
            }
        });
    }

    public function down(): void
    {
        // Keep down simple. If you need it reversible, add dropColumn logic.
        // For SQLite, dropping columns is non-trivial depending on version.
    }
};
