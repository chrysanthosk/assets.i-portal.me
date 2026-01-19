<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Core identity
            $table->string('name');
            $table->string('type')->default('Apartment'); // Apartment, House, Land, Commercial, Other
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            // Purchase & ownership
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->string('currency', 10)->default('EUR');
            $table->string('owner_entity')->nullable(); // personal/company/joint text
            $table->decimal('ownership_percentage', 5, 2)->default(100.00);

            // Title deed & legal
            $table->boolean('title_deed')->default(false);
            $table->string('title_deed_number')->nullable();
            $table->date('title_deed_date')->nullable();
            $table->string('lawyer_notary')->nullable();

            // Financing
            $table->boolean('financed')->default(false);
            $table->string('lender')->nullable();
            $table->decimal('loan_amount', 14, 2)->nullable();
            $table->decimal('interest_rate', 6, 3)->nullable(); // e.g. 3.750
            $table->date('loan_start_date')->nullable();
            $table->date('loan_end_date')->nullable();
            $table->decimal('monthly_payment', 14, 2)->nullable();

            // Property details
            $table->decimal('size_sqm', 10, 2)->nullable();
            $table->decimal('land_sqm', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->boolean('parking')->default(false);
            $table->unsignedSmallInteger('year_built')->nullable();

            // Rental status
            $table->string('status')->default('Vacant'); // Owner-occupied, Rented (long-term), Airbnb/Short-term, Vacant
            $table->decimal('estimated_annual_expenses', 14, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
