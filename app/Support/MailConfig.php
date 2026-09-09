<?php

namespace App\Support;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Apply the SMTP settings saved in Settings → SMTP to the runtime mail config,
 * so every Mailable (reminders, OTPs) goes through the server configured in
 * the UI instead of whatever MAIL_* happens to be in .env.
 */
class MailConfig
{
    public const CACHE_KEY = 'smtp_settings.active';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function apply(): void
    {
        try {
            // Cached: this runs on every request. SmtpSettingsController clears it on save.
            $smtp = Cache::remember(self::CACHE_KEY, 300,
                fn () => SmtpSetting::query()->where('enabled', true)->first() ?? false);
        } catch (\Throwable $e) {
            return; // no DB yet (fresh install, migrations pending)
        }

        if (! $smtp || ! $smtp->host) {
            return;
        }

        $enc = strtolower((string) $smtp->encryption);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $smtp->host);
        Config::set('mail.mailers.smtp.port', (int) ($smtp->port ?: 587));
        Config::set('mail.mailers.smtp.username', $smtp->username ?: null);
        Config::set('mail.mailers.smtp.password', $smtp->getPasswordPlain());
        // 'smtps' = implicit TLS (465); plain 'smtp' upgrades via STARTTLS when offered (587)
        Config::set('mail.mailers.smtp.scheme', $enc === 'ssl' ? 'smtps' : 'smtp');

        if ($smtp->from_address) {
            Config::set('mail.from.address', $smtp->from_address);
        }
        if ($smtp->from_name) {
            Config::set('mail.from.name', $smtp->from_name);
        }
    }
}
