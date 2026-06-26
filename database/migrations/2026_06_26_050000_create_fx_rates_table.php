<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            // 1 unit of `currency` equals `rate_to_base` units of the base currency.
            $table->string('currency', 10)->unique();
            $table->decimal('rate_to_base', 18, 8)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
