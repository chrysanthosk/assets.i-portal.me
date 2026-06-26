<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These information_schema lookups + ALTER TABLE ADD FOREIGN KEY are only
     * supported on MySQL / MariaDB. On other drivers (e.g. SQLite used in tests)
     * the columns and their FKs are already created inline by an earlier
     * migration, so this one is a no-op.
     */
    private function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function fkExists(string $table, string $constraintName): bool
    {
        // Works for MySQL / MariaDB
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

        return ! empty($rows);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    public function up(): void
    {
        $table = 'assets';

        // Ensure columns exist first (if your project expects these)
        Schema::table($table, function (Blueprint $t) {
            if (! Schema::hasColumn('assets', 'asset_type_id')) {
                $t->unsignedBigInteger('asset_type_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('assets', 'owner_entity_id')) {
                $t->unsignedBigInteger('owner_entity_id')->nullable()->after('asset_type_id');
            }
        });

        // FK (re)attachment below is MySQL/MariaDB-only.
        if (! $this->isMysql()) {
            return;
        }

        // Add FK for asset_type_id if missing
        $fk1 = 'assets_asset_type_id_foreign';
        if ($this->columnExists($table, 'asset_type_id') && ! $this->fkExists($table, $fk1)) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('asset_type_id', 'assets_asset_type_id_foreign')
                    ->references('id')->on('asset_types')
                    ->onDelete('restrict');
            });
        }

        // Add FK for owner_entity_id if missing
        $fk2 = 'assets_owner_entity_id_foreign';
        if ($this->columnExists($table, 'owner_entity_id') && ! $this->fkExists($table, $fk2)) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('owner_entity_id', 'assets_owner_entity_id_foreign')
                    ->references('id')->on('owner_entities')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        $table = 'assets';

        if (! $this->isMysql()) {
            return;
        }

        // Drop FK only if exists (safe)
        foreach ([
            'assets_asset_type_id_foreign',
            'assets_owner_entity_id_foreign',
        ] as $fk) {
            if ($this->fkExists($table, $fk)) {
                Schema::table($table, function (Blueprint $t) use ($fk) {
                    $t->dropForeign($fk);
                });
            }
        }

        // (Optional) don’t drop columns automatically to avoid data loss
    }
};
