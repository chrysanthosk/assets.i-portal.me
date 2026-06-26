<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_documents', 'doc_type')) {
                $table->string('doc_type', 60)->nullable()->after('title');
            }
            if (! Schema::hasColumn('asset_documents', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('doc_type');
                $table->index('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {
            if (Schema::hasColumn('asset_documents', 'expires_at')) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('asset_documents', 'doc_type')) {
                $table->dropColumn('doc_type');
            }
        });
    }
};
