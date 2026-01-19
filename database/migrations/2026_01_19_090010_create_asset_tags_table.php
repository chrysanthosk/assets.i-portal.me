<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. Paphos, Airbnb, Long-term
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tags');
    }
};
