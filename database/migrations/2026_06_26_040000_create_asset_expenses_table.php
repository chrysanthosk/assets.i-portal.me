<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->date('spent_on');
            $table->string('category', 60)->default('Other');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('EUR');
            $table->string('vendor', 150)->nullable();
            $table->string('description', 255)->nullable();

            $table->timestamps();

            $table->index('spent_on');
            $table->index('category');
            $table->index(['asset_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_expenses');
    }
};
