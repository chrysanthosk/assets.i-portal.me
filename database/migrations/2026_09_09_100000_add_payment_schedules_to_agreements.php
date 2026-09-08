<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agreements can now be paid monthly (in advance or in arrears) or in a fixed
 * set of instalments that repeats every contract year (e.g. a villa operator's
 * annual guarantee paid 15 % on 15 Apr, 15 % on 31 May, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_rentals', function (Blueprint $table) {
            $table->string('payment_schedule', 20)->default('monthly')->after('rent_type'); // monthly | installments
            $table->boolean('paid_in_arrears')->default(false)->after('payment_schedule');  // monthly: due the month after
            $table->json('installments')->nullable()->after('paid_in_arrears');            // [{month, day, amount, label}]
        });

        Schema::table('rental_payments', function (Blueprint $table) {
            $table->string('period', 20)->nullable()->change();                            // "YYYY-MM" or "YYYY-MM#n"
            $table->string('label', 120)->nullable()->after('period');                     // e.g. "Instalment 2 (15 %)"
        });

        Schema::table('deed_imports', function (Blueprint $table) {
            $table->string('kind', 20)->default('deed')->after('id');                      // deed | agreement
        });
    }

    public function down(): void
    {
        Schema::table('asset_rentals', fn (Blueprint $t) => $t->dropColumn(['payment_schedule', 'paid_in_arrears', 'installments']));
        Schema::table('rental_payments', fn (Blueprint $t) => $t->dropColumn('label'));
        Schema::table('deed_imports', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
