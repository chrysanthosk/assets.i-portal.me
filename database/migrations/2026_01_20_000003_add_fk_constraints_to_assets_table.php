<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL / MariaDB only — information_schema + ALTER ADD FOREIGN KEY are not
     * portable. On other drivers (SQLite in tests) the FKs already exist inline.
     */
    private function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        $db = DB::getDatabaseName();

        $rows = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = ?
             LIMIT 1",
            [$db, $table, $constraintName]
        );

        return !empty($rows);
    }

    public function up(): void
    {
        if (!$this->isMysql()) {
            return;
        }

        $table = 'assets';

        // IMPORTANT: use explicit names so we can detect duplicates safely
        $fkAssetType   = 'assets_asset_type_id_foreign';
        $fkOwnerEntity = 'assets_owner_entity_id_foreign';

        // asset_type_id FK
        if (Schema::hasColumn($table, 'asset_type_id') && !$this->fkExists($table, $fkAssetType)) {
            Schema::table($table, function (Blueprint $t) use ($fkAssetType) {
                $t->foreign('asset_type_id', $fkAssetType)
                    ->references('id')->on('asset_types')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        // owner_entity_id FK
        if (Schema::hasColumn($table, 'owner_entity_id') && !$this->fkExists($table, $fkOwnerEntity)) {
            Schema::table($table, function (Blueprint $t) use ($fkOwnerEntity) {
                $t->foreign('owner_entity_id', $fkOwnerEntity)
                    ->references('id')->on('owner_entities')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (!$this->isMysql()) {
            return;
        }

        $table = 'assets';

        // Drop by constraint name (most reliable)
        $fkAssetType   = 'assets_asset_type_id_foreign';
        $fkOwnerEntity = 'assets_owner_entity_id_foreign';

        Schema::table($table, function (Blueprint $t) use ($fkAssetType, $fkOwnerEntity) {
            // dropForeign accepts the constraint name string too
            try { $t->dropForeign($fkAssetType); } catch (\Throwable $e) {}
            try { $t->dropForeign($fkOwnerEntity); } catch (\Throwable $e) {}
        });
    }
};
