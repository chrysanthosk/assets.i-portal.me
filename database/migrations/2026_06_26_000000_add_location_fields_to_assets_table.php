<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The asset form, $fillable, and the index() search already reference
 * city / postcode / country, but no migration ever created the columns —
 * which made asset search (and saving those fields) fail at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (! Schema::hasColumn('assets', 'postcode')) {
                $table->string('postcode', 20)->nullable()->after('city');
            }
            if (! Schema::hasColumn('assets', 'country')) {
                $table->string('country', 100)->nullable()->after('postcode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            foreach (['city', 'postcode', 'country'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
