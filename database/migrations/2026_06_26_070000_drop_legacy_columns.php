<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire legacy denormalised columns now that the FK relationships
 * (asset_type_id, owner_entity_id) and the canonical document columns
 * (path/mime_type/size_bytes) are the single source of truth.
 *
 * Any still-unmapped rows are backfilled from the legacy values first.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill asset FK ids from legacy names where missing.
        if (Schema::hasColumn('assets', 'type') && Schema::hasColumn('assets', 'asset_type_id')) {
            foreach (DB::table('asset_types')->get(['id', 'name']) as $t) {
                DB::table('assets')->whereNull('asset_type_id')->where('type', $t->name)
                    ->update(['asset_type_id' => $t->id]);
            }
        }
        if (Schema::hasColumn('assets', 'owner_entity') && Schema::hasColumn('assets', 'owner_entity_id')) {
            foreach (DB::table('owner_entities')->get(['id', 'name']) as $o) {
                DB::table('assets')->whereNull('owner_entity_id')->where('owner_entity', $o->name)
                    ->update(['owner_entity_id' => $o->id]);
            }
        }

        // Backfill canonical document columns from legacy ones where missing.
        if (Schema::hasColumn('asset_documents', 'file_path')) {
            DB::table('asset_documents')->whereNull('path')->whereNotNull('file_path')
                ->update(['path' => DB::raw('file_path')]);
        }
        if (Schema::hasColumn('asset_documents', 'mime')) {
            DB::table('asset_documents')->whereNull('mime_type')->whereNotNull('mime')
                ->update(['mime_type' => DB::raw('mime')]);
        }
        if (Schema::hasColumn('asset_documents', 'size')) {
            DB::table('asset_documents')->whereNull('size_bytes')->whereNotNull('size')
                ->update(['size_bytes' => DB::raw('size')]);
        }

        Schema::table('assets', function (Blueprint $table) {
            foreach (['type', 'owner_entity'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('asset_documents', function (Blueprint $table) {
            foreach (['file_path', 'mime', 'size'] as $col) {
                if (Schema::hasColumn('asset_documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // Re-add the columns (data cannot be restored).
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'type')) {
                $table->string('type')->nullable();
            }
            if (! Schema::hasColumn('assets', 'owner_entity')) {
                $table->string('owner_entity')->nullable();
            }
        });

        Schema::table('asset_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_documents', 'file_path')) {
                $table->string('file_path')->nullable();
            }
            if (! Schema::hasColumn('asset_documents', 'mime')) {
                $table->string('mime')->nullable();
            }
            if (! Schema::hasColumn('asset_documents', 'size')) {
                $table->integer('size')->nullable();
            }
        });
    }
};
