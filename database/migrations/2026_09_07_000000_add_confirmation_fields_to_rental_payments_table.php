<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_payments', function (Blueprint $table) {
            // "YYYY-MM" the payment covers; set for auto-generated monthly rows so
            // generation is idempotent (one row per agreement per period).
            $table->string('period', 7)->nullable()->after('due_date');

            // Reminder / confirmation loop
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('notes');
            $table->timestamp('last_reminded_at')->nullable()->after('reminder_count');
            $table->timestamp('confirmed_at')->nullable()->after('last_reminded_at');
            $table->timestamp('not_received_at')->nullable()->after('confirmed_at');

            $table->unique(['asset_rental_id', 'period'], 'rental_payments_rental_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rental_payments', function (Blueprint $table) {
            $table->dropUnique('rental_payments_rental_period_unique');
            $table->dropColumn(['period', 'reminder_count', 'last_reminded_at', 'confirmed_at', 'not_received_at']);
        });
    }
};
