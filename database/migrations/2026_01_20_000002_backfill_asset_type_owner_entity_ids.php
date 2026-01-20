<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill asset_type_id from existing string column "type"
        DB::statement("
            UPDATE assets
            SET asset_type_id = (
                SELECT id FROM asset_types
                WHERE asset_types.name = assets.type
                LIMIT 1
            )
            WHERE asset_type_id IS NULL
              AND type IS NOT NULL
              AND TRIM(type) <> ''
        ");

        // Backfill owner_entity_id from existing string column "owner_entity"
        DB::statement("
            UPDATE assets
            SET owner_entity_id = (
                SELECT id FROM owner_entities
                WHERE owner_entities.name = assets.owner_entity
                LIMIT 1
            )
            WHERE owner_entity_id IS NULL
              AND owner_entity IS NOT NULL
              AND TRIM(owner_entity) <> ''
        ");
    }

    public function down(): void
    {
        // Safe down: do nothing (don’t wipe existing IDs)
    }
};
