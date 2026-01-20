<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table already exists (e.g. created by an earlier migration/manual), do nothing.
        if (Schema::hasTable('asset_documents')) {
            return;
        }

        Schema::create('asset_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();

            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->string('notes', 500)->nullable();

            $table->timestamps();

            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
    }
};
