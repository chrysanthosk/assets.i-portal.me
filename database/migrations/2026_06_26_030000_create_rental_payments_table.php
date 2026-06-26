<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_rental_id')->constrained('asset_rentals')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('EUR');

            $table->date('paid_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending | paid
            $table->string('method', 60)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};
