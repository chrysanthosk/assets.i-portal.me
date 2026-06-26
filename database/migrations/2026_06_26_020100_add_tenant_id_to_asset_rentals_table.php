<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_rentals', 'tenant_id')) {
                // Nullable FK; the legacy free-text tenant_name is kept in sync.
                $table->foreignId('tenant_id')->nullable()->after('asset_id')
                    ->constrained('tenants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            if (Schema::hasColumn('asset_rentals', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
