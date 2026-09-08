<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PortalSetting;
use App\Support\Deeds\ClaudeDeedExtractor;
use App\Support\DocumentReminders;
use App\Support\Portal;
use App\Support\RentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PortalSettingsController extends Controller
{
    public function edit()
    {
        $setting = PortalSetting::firstOrCreate(
            ['key' => 'portal_name'],
            ['value' => 'assets.i-portal.me']
        );

        return view('settings.portal', [
            'portalName' => $setting->value,
            'rentRemindersEnabled' => RentSchedule::enabled(),
            'rentReminderEmail' => PortalSetting::get(RentSchedule::SETTING_EMAIL, ''),
            'rentDueDay' => RentSchedule::dueDay(),
            'rentRepeatDays' => RentSchedule::repeatDays(),
            'docReminderDays' => DocumentReminders::days(),
            'fallbackRecipients' => PortalSetting::get(RentSchedule::SETTING_EMAIL) ? [] : RentSchedule::recipients(),
            'anthropicKeySet' => (bool) PortalSetting::get(ClaudeDeedExtractor::SETTING_API_KEY),
            'anthropicKeyFromEnv' => ! PortalSetting::get(ClaudeDeedExtractor::SETTING_API_KEY) && (bool) config('services.anthropic.key'),
            'anthropicModel' => ClaudeDeedExtractor::model(),
            'advancedMode' => Portal::advanced(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'portal_name' => ['required', 'string', 'max:60'],
            'rent_reminders_enabled' => ['nullable', 'in:0,1'],
            'rent_reminder_email' => ['nullable', 'string', 'max:500'],
            'rent_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'rent_reminder_repeat_days' => ['required', 'integer', 'min:1', 'max:30'],
            'doc_reminder_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'anthropic_api_key' => ['nullable', 'string', 'max:300'],
            'anthropic_api_key_clear' => ['nullable', 'in:0,1'],
            'advanced_mode' => ['nullable', 'in:0,1'],
        ]);

        // Accept a comma/space separated list; every entry must be a valid address.
        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['rent_reminder_email'] ?? '')))->filter();
        $bad = $emails->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($bad->isNotEmpty()) {
            return back()->withInput()->withErrors([
                'rent_reminder_email' => 'Invalid email address: '.$bad->implode(', '),
            ]);
        }

        PortalSetting::set('portal_name', $data['portal_name']);
        Portal::setAdvanced(($data['advanced_mode'] ?? '0') === '1');
        PortalSetting::set(RentSchedule::SETTING_ENABLED, ($data['rent_reminders_enabled'] ?? '0') === '1' ? '1' : '0');
        PortalSetting::set(RentSchedule::SETTING_EMAIL, $emails->implode(', '));
        PortalSetting::set(RentSchedule::SETTING_DUE_DAY, (string) $data['rent_due_day']);
        PortalSetting::set(RentSchedule::SETTING_REPEAT_DAYS, (string) $data['rent_reminder_repeat_days']);
        PortalSetting::set(DocumentReminders::SETTING_DAYS, (string) ($data['doc_reminder_days'] ?? 30));

        // API key: only overwrite when a new value is typed; encrypted at rest.
        if (($data['anthropic_api_key_clear'] ?? '0') === '1') {
            PortalSetting::set(ClaudeDeedExtractor::SETTING_API_KEY, '');
        } elseif (trim((string) ($data['anthropic_api_key'] ?? '')) !== '') {
            PortalSetting::set(ClaudeDeedExtractor::SETTING_API_KEY, Crypt::encryptString(trim($data['anthropic_api_key'])));
        }

        return back()->with('success', 'Portal settings saved.');
    }
}
