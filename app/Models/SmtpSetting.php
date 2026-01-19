<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmtpSetting extends Model
{
    protected $fillable = [
        'enabled',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'last_tested_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function setPasswordPlain(?string $plain): void
    {
        if ($plain === null || trim($plain) === '') {
            return;
        }

        $this->password = Crypt::encryptString($plain);
    }

    public function getPasswordPlain(): ?string
    {
        if (!$this->password) return null;

        try {
            return Crypt::decryptString($this->password);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
