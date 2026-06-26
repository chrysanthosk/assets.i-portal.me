<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'asset_rentals';

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {

            if (! Schema::hasColumn($table, 'agreement_start_date')) {
                $tableBlueprint->date('agreement_start_date')->nullable()->after('month');
            }

            if (! Schema::hasColumn($table, 'agreement_end_date')) {
                $tableBlueprint->date('agreement_end_date')->nullable()->after('agreement_start_date');
            }

            if (! Schema::hasColumn($table, 'tenant_name')) {
                $tableBlueprint->string('tenant_name', 255)->nullable()->after('agreement_end_date');
            }

            if (! Schema::hasColumn($table, 'rent_type')) {
                // Airbnb / Long-term (string to keep it flexible)
                $tableBlueprint->string('rent_type', 30)->nullable()->after('tenant_name');
            }

            if (! Schema::hasColumn($table, 'is_active')) {
                // Optional but useful for dashboard logic:
                $tableBlueprint->boolean('is_active')->default(true)->after('rent_type');
            }
        });
    }

    public function down(): void
    {
        // Keep down simple / safe for SQLite and older MySQL setups.
        // If you ever want reversibility, we can add conditional dropColumn() logic,
        // but note SQLite column drops depend on version/capabilities.
    }
};
