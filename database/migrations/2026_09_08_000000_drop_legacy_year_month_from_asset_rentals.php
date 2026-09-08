<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agreements used to be one row per asset per month (year/month + a unique
 * key on asset_id+year+month). Since agreements have start/end dates that
 * constraint only blocks legitimate cases (two agreements starting in the same
 * month, e.g. tenant change). Drop the columns and the key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_rentals', 'year')) {
            return;
        }

        // Make sure every row has a start date before the columns go away
        DB::table('asset_rentals')->whereNull('agreement_start_date')->orderBy('id')->each(function ($r) {
            $y = (int) ($r->year ?: now()->year);
            $m = max(1, min(12, (int) ($r->month ?: 1)));
            DB::table('asset_rentals')->where('id', $r->id)->update([
                'agreement_start_date' => sprintf('%04d-%02d-01', $y, $m),
            ]);
        });

        // Indexes first (SQLite refuses to drop a column that is still indexed)
        Schema::table('asset_rentals', function (Blueprint $table) {
            $table->dropUnique(['asset_id', 'year', 'month']);
            $table->dropIndex(['year', 'month']);
        });
        Schema::table('asset_rentals', function (Blueprint $table) {
            $table->dropColumn(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->default(0);
            $table->unsignedSmallInteger('month')->default(0);
        });
    }
};
