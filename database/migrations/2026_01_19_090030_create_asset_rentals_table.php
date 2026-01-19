<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            // Monthly rental record
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('month'); // 1-12
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->default('EUR');
            $table->string('channel')->nullable(); // e.g. Airbnb, Long-term, Booking, etc.
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['asset_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_rentals');
    }
};
