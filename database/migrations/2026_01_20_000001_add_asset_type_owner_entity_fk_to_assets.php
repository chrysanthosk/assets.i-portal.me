<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'asset_type_id')) {
                $table->unsignedBigInteger('asset_type_id')->nullable()->after('type');
                $table->index('asset_type_id');
            }
            if (!Schema::hasColumn('assets', 'owner_entity_id')) {
                $table->unsignedBigInteger('owner_entity_id')->nullable()->after('owner_entity');
                $table->index('owner_entity_id');
            }
        });

        // Backfill asset_type_id from legacy "type" string
        if (Schema::hasColumn('assets', 'type')) {
            $types = DB::table('assets')
                ->select('type')
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->pluck('type');

            foreach ($types as $typeName) {
                $typeName = trim((string)$typeName);
                if ($typeName === '') continue;

                $typeId = DB::table('asset_types')->where('name', $typeName)->value('id');
                if (!$typeId) {
                    $typeId = DB::table('asset_types')->insertGetId([
                        'name' => $typeName,
                        'is_active' => 1,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('assets')
                    ->where('type', $typeName)
                    ->update(['asset_type_id' => $typeId]);
            }
        }

        // Backfill owner_entity_id from legacy "owner_entity" string
        if (Schema::hasColumn('assets', 'owner_entity')) {
            $entities = DB::table('assets')
                ->select('owner_entity')
                ->whereNotNull('owner_entity')
                ->where('owner_entity', '!=', '')
                ->distinct()
                ->pluck('owner_entity');

            foreach ($entities as $entityName) {
                $entityName = trim((string)$entityName);
                if ($entityName === '') continue;

                $entityId = DB::table('owner_entities')->where('name', $entityName)->value('id');
                if (!$entityId) {
                    $entityId = DB::table('owner_entities')->insertGetId([
                        'name' => $entityName,
                        'is_active' => 1,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('assets')
                    ->where('owner_entity', $entityName)
                    ->update(['owner_entity_id' => $entityId]);
            }
        }

        // Add foreign keys (restrict delete if referenced)
        Schema::table('assets', function (Blueprint $table) {
            // Avoid duplicate FK creation
            // Laravel doesn't have a clean "if fk exists", but this is fine for fresh envs.

            $table->foreign('asset_type_id')
                ->references('id')
                ->on('asset_types')
                ->restrictOnDelete();

            $table->foreign('owner_entity_id')
                ->references('id')
                ->on('owner_entities')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Drop FKs first
            try { $table->dropForeign(['asset_type_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['owner_entity_id']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('assets', 'asset_type_id')) {
                $table->dropColumn('asset_type_id');
            }
            if (Schema::hasColumn('assets', 'owner_entity_id')) {
                $table->dropColumn('owner_entity_id');
            }
        });
    }
};
