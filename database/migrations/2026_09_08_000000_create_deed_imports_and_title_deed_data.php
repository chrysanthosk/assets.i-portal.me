<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Structured data extracted from the title deed (full JSON), kept on the asset
        Schema::table('assets', function (Blueprint $table) {
            $table->json('title_deed_data')->nullable()->after('title_deed_date');
        });

        // One row per uploaded deed: file, extraction result, and the asset it became
        Schema::create('deed_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();

            $table->string('original_name');
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->string('status', 20)->default('pending'); // pending | extracted | failed | completed
            $table->json('extracted')->nullable();
            $table->text('error')->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();

            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deed_imports');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('title_deed_data');
        });
    }
};
