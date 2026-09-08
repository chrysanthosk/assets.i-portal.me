<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_payments', function (Blueprint $table) {
            // Breakdown from an imported manager statement (gross, fee, net…)
            $table->json('statement')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('rental_payments', function (Blueprint $table) {
            $table->dropColumn('statement');
        });
    }
};
