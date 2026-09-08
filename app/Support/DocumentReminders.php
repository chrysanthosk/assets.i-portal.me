<?php

namespace App\Support;

use App\Mail\DocumentExpiryDigestMail;
use App\Models\AssetDocument;
use App\Models\PortalSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly digest of documents that have expired or expire soon (insurance,
 * certificates, contracts). Uses the same recipients / on-off switch as the
 * rent reminders.
 */
class DocumentReminders
{
    public const SETTING_DAYS = 'doc_reminder_days';

    public static function days(): int
    {
        return max(1, min(365, (int) PortalSetting::get(self::SETTING_DAYS, '30')));
    }

    /** @return Collection<int, AssetDocument> */
    public static function due(): Collection
    {
        return AssetDocument::query()->with('asset')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(self::days())->toDateString())
            ->orderBy('expires_at')
            ->get();
    }

    /** @return int emails sent (0 or 1) */
    public static function send(bool $force = false): int
    {
        if (! $force && ! RentSchedule::enabled()) {
            return 0;
        }

        $recipients = RentSchedule::recipients();
        $docs = self::due();
        if ($recipients === [] || $docs->isEmpty()) {
            return 0;
        }

        Mail::to($recipients)->send(new DocumentExpiryDigestMail($docs, self::days()));

        return 1;
    }
}
