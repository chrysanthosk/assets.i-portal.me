<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Laravel already has "name"
            $table->string('username')->unique()->after('id');
            $table->string('surname')->nullable()->after('name');

            // Email change with OTP confirmation
            $table->string('pending_email')->nullable()->after('email');
            $table->string('email_otp_hash')->nullable()->after('pending_email');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_hash');

            // Google Authenticator 2FA (TOTP)
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'name', 'surname',
                'pending_email', 'email_otp_hash', 'email_otp_expires_at',
                'two_factor_enabled', 'two_factor_secret',
            ]);
        });
    }
};
