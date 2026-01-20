<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {

            // ---- Add missing columns (safe checks)
            if (!Schema::hasColumn('asset_documents', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable()->after('asset_id');
                $table->index('uploaded_by');
            }

            if (!Schema::hasColumn('asset_documents', 'title')) {
                $table->string('title', 255)->nullable()->after('uploaded_by');
            }

            if (!Schema::hasColumn('asset_documents', 'disk')) {
                $table->string('disk', 50)->nullable()->after('title');
            }

            // ---- Rename file_path -> path (SQLite-safe approach)
            // We'll keep file_path for backward safety and also create "path"
            if (!Schema::hasColumn('asset_documents', 'path')) {
                $table->string('path', 500)->nullable()->after('disk');
            }

            // ---- Rename mime -> mime_type (keep mime too, but add mime_type)
            if (!Schema::hasColumn('asset_documents', 'mime_type')) {
                $table->string('mime_type', 120)->nullable()->after('path');
            }

            // ---- Rename size -> size_bytes (keep size too, but add size_bytes)
            if (!Schema::hasColumn('asset_documents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            }

            if (!Schema::hasColumn('asset_documents', 'notes')) {
                $table->string('notes', 500)->nullable()->after('size_bytes');
            }
        });

        // Backfill new columns from legacy ones where possible
        // (No DB facade needed; we can leave values null if old columns are empty)
    }

    public function down(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {
            // We won't drop legacy columns in down; just remove new ones
            foreach (['uploaded_by','title','disk','path','mime_type','size_bytes','notes'] as $col) {
                if (Schema::hasColumn('asset_documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
