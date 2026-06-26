<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SmtpSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class SmtpSettingsController extends Controller
{
    public function edit()
    {
        $smtp = SmtpSetting::first();

        if (! $smtp) {
            $smtp = SmtpSetting::create([
                'enabled' => false,
                'host' => null,
                'port' => 587,
                'encryption' => 'tls',
                'username' => null,
                'from_address' => null,
                'from_name' => null,
            ]);
        }

        return view('settings.smtp.edit', compact('smtp'));
    }

    public function update(Request $request)
    {
        $smtp = SmtpSetting::firstOrCreate([]);

        $data = $request->validate([
            'enabled' => ['nullable', 'in:0,1'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:,tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $enabled = (int) ($data['enabled'] ?? 0) === 1;
        $host = $data['host'] ?? null;
        $port = (int) ($data['port'] ?? 587);
        $enc = ($data['encryption'] ?? null) ?: null; // null, 'tls', 'ssl'

        // Guard against invalid combinations (SendGrid + most providers)
        if ($enc === 'ssl' && $port === 587) {
            return redirect()
                ->route('settings.smtp.edit')
                ->with('error', 'Invalid SMTP combination: SSL on port 587 will fail. Use TLS on 587 (STARTTLS) or SSL on 465.');
        }

        $smtp->enabled = $enabled;
        $smtp->host = $host;
        $smtp->port = $port;
        $smtp->encryption = $enc;
        $smtp->username = $data['username'] ?? null;
        $smtp->from_address = $data['from_address'] ?? null;
        $smtp->from_name = $data['from_name'] ?? null;

        if (! empty($data['password'])) {
            $smtp->setPasswordPlain($data['password']);
        }

        $smtp->save();

        return redirect()->route('settings.smtp.edit')->with('success', 'SMTP settings saved.');
    }

    public function test(Request $request)
    {
        $smtp = SmtpSetting::first();

        if (! $smtp || ! $smtp->enabled) {
            return redirect()->route('settings.smtp.edit')->with('error', 'SMTP is disabled.');
        }

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $host = $smtp->host;
        $port = (int) ($smtp->port ?: 587);
        $enc = $smtp->encryption ?: ''; // '', 'tls', 'ssl'

        if (empty($host) || empty($port)) {
            return redirect()->route('settings.smtp.edit')->with('error', 'SMTP host/port are required.');
        }

        // Block SSL+587 at test time too (in case it was already saved)
        if ($enc === 'ssl' && $port === 587) {
            return redirect()
                ->route('settings.smtp.edit')
                ->with('error', 'Invalid SMTP combination: SSL on port 587 will fail. Use TLS on 587 (STARTTLS) or SSL on 465.');
        }

        try {
            $user = $smtp->username ?: '';
            $pass = $smtp->getPasswordPlain() ?: '';

            $scheme = ($enc === 'ssl') ? 'smtps' : 'smtp';

            $dsn = $scheme.'://';
            if ($user !== '' || $pass !== '') {
                $dsn .= rawurlencode($user).':'.rawurlencode($pass).'@';
            }
            $dsn .= $host.':'.$port;

            if ($enc === 'tls') {
                // STARTTLS
                $dsn .= '?encryption=tls';
            }

            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $fromAddress = $smtp->from_address ?: config('mail.from.address') ?: 'no-reply@example.com';
            $fromName = $smtp->from_name ?: config('mail.from.name') ?: 'Portal';

            $email = (new Email)
                ->from(sprintf('%s <%s>', $fromName, $fromAddress))
                ->to($data['test_email'])
                ->subject('SMTP Test Email')
                ->text("This is a test email from your portal.\n\nTime: ".now()->format('Y-m-d H:i:s'));

            $mailer->send($email);

            $smtp->last_tested_at = Carbon::now();
            $smtp->save();

            return redirect()->route('settings.smtp.edit')->with('success', 'Test email sent successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('settings.smtp.edit')->with('error', 'SMTP test failed: '.$e->getMessage());
        }
    }
}
