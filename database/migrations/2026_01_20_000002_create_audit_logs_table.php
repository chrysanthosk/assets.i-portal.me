<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('action', 80);          // created / updated / deleted / uploaded
            $table->string('entity', 80);          // Asset, AssetType, OwnerEntity, AssetDocument
            $table->unsignedBigInteger('entity_id')->nullable()->index();

            $table->json('meta')->nullable();      // anything extra (old/new name, filenames, etc.)
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
