<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            // Add FKs only if they aren't already there
            // Some DBs require constraint names; we’ll use Laravel default naming.

            if (Schema::hasColumn('assets', 'asset_type_id')) {
                // Avoid duplicate constraint errors if rerun
                try {
                    $table->foreign('asset_type_id')
                        ->references('id')
                        ->on('asset_types')
                        ->restrictOnDelete()
                        ->cascadeOnUpdate();
                } catch (\Throwable $e) {
                    // ignore if already exists
                }
            }

            if (Schema::hasColumn('assets', 'owner_entity_id')) {
                try {
                    $table->foreign('owner_entity_id')
                        ->references('id')
                        ->on('owner_entities')
                        ->restrictOnDelete()
                        ->cascadeOnUpdate();
                } catch (\Throwable $e) {
                    // ignore if already exists
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Drop constraints if they exist
            try { $table->dropForeign(['asset_type_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['owner_entity_id']); } catch (\Throwable $e) {}
        });
    }
};
