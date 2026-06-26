<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_rentals', 'agreement_start_date')) {
            Schema::table('asset_rentals', function (Blueprint $table) {
                $table->date('agreement_start_date')->nullable()->after('channel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('asset_rentals', 'agreement_start_date')) {
            Schema::table('asset_rentals', function (Blueprint $table) {
                $table->dropColumn('agreement_start_date');
            });
        }
    }
};
