<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {

            // Add only if missing (prevents "duplicate column" on SQLite)
            if (! Schema::hasColumn('asset_rentals', 'agreement_start_date')) {
                $table->date('agreement_start_date')->nullable()->after('month');
            }

            if (! Schema::hasColumn('asset_rentals', 'agreement_end_date')) {
                $table->date('agreement_end_date')->nullable()->after('agreement_start_date');
            }

            if (! Schema::hasColumn('asset_rentals', 'tenant_name')) {
                $table->string('tenant_name', 120)->nullable()->after('agreement_end_date');
            }

            if (! Schema::hasColumn('asset_rentals', 'rent_type')) {
                $table->string('rent_type', 30)->nullable()->after('tenant_name');
            }

            if (! Schema::hasColumn('asset_rentals', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('rent_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            // Drop only if exists (safe)
            if (Schema::hasColumn('asset_rentals', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('asset_rentals', 'rent_type')) {
                $table->dropColumn('rent_type');
            }
            if (Schema::hasColumn('asset_rentals', 'tenant_name')) {
                $table->dropColumn('tenant_name');
            }
            if (Schema::hasColumn('asset_rentals', 'agreement_end_date')) {
                $table->dropColumn('agreement_end_date');
            }
            if (Schema::hasColumn('asset_rentals', 'agreement_start_date')) {
                $table->dropColumn('agreement_start_date');
            }
        });
    }
};
