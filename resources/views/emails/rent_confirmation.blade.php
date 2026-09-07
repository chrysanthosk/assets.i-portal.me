@php
    $amount = $payment->currency.' '.number_format((float) $payment->amount, 2);
    $asset = $payment->asset?->name ?? 'Property';
    $tenant = $payment->tenantName();
    $nth = $payment->reminder_count + 1;
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Rent check</title></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#222; background:#f6f7f9; margin:0; padding:24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px; margin:0 auto; background:#fff; border-radius:8px; border:1px solid #e5e7eb;">
    <tr><td style="padding:28px;">
        <h2 style="margin:0 0 16px; font-size:20px;">Did the rent for <span style="white-space:nowrap;">{{ $asset }}</span> arrive?</h2>

        <table role="presentation" cellspacing="0" cellpadding="0" style="font-size:15px; margin-bottom:20px;">
            <tr><td style="color:#6b7280; padding:2px 16px 2px 0;">Period</td><td>{{ $payment->periodLabel() }}</td></tr>
            <tr><td style="color:#6b7280; padding:2px 16px 2px 0;">Amount</td><td><strong>{{ $amount }}</strong></td></tr>
            @if($tenant)<tr><td style="color:#6b7280; padding:2px 16px 2px 0;">Tenant</td><td>{{ $tenant }}</td></tr>@endif
            <tr><td style="color:#6b7280; padding:2px 16px 2px 0;">Due date</td><td>{{ optional($payment->due_date)->format('d M Y') }}</td></tr>
        </table>

        <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom:20px;">
            <tr>
                <td style="padding-right:12px;">
                    <a href="{{ $receivedUrl }}" style="display:inline-block; background:#198754; color:#fff; text-decoration:none; padding:12px 20px; border-radius:6px; font-weight:600;">✔ Yes, received</a>
                </td>
                <td>
                    <a href="{{ $notReceivedUrl }}" style="display:inline-block; background:#dc3545; color:#fff; text-decoration:none; padding:12px 20px; border-radius:6px; font-weight:600;">✖ No, not yet</a>
                </td>
            </tr>
        </table>

        <p style="font-size:13px; color:#6b7280; margin:0 0 8px;">
            Either link opens a page with one button to confirm. Until you answer, this reminder repeats every few days.
            @if($nth > 1) This is reminder #{{ $nth }}. @endif
        </p>
        <p style="font-size:13px; color:#6b7280; margin:0;">
            All open items: <a href="{{ route('payments.unconfirmed') }}" style="color:#0d6efd;">{{ route('payments.unconfirmed') }}</a>
        </p>
    </td></tr>
</table>
</body>
</html>
