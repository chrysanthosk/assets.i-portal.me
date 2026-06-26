<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add the columns your code uses if missing
            if (! Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->string('auditable_type', 255)->default('system')->after('action');
            }
            if (! Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->index()->after('auditable_type');
            }
            if (! Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('auditable_id');
            }
            if (! Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            // If you created "entity/entity_id/meta" earlier, you can keep them
            // (harmless), or later remove them with another migration.
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'new_values')) {
                $table->dropColumn('new_values');
            }
            if (Schema::hasColumn('audit_logs', 'old_values')) {
                $table->dropColumn('old_values');
            }
            if (Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->dropColumn('auditable_id');
            }
            if (Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->dropColumn('auditable_type');
            }
        });
    }
};
