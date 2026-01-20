<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'asset_type_id')) {
                $table->foreignId('asset_type_id')->nullable()->after('type')->constrained('asset_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('assets', 'owner_entity_id')) {
                $table->foreignId('owner_entity_id')->nullable()->after('owner_entity')->constrained('owner_entities')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'asset_type_id')) {
                $table->dropConstrainedForeignId('asset_type_id');
            }
            if (Schema::hasColumn('assets', 'owner_entity_id')) {
                $table->dropConstrainedForeignId('owner_entity_id');
            }
        });
    }
};
