<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            // Day of month the rent is due for this agreement; null = portal default
            $table->unsignedTinyInteger('due_day')->nullable()->after('paid_in_arrears');
        });
    }

    public function down(): void
    {
        Schema::table('asset_rentals', fn (Blueprint $t) => $t->dropColumn('due_day'));
    }
};
