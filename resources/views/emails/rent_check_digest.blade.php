@php
    $byCur = $payments->groupBy('currency')->map(fn ($g) => $g->sum('amount'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Rent check</title></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#222; background:#f6f7f9; margin:0; padding:24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; margin:0 auto; background:#fff; border-radius:8px; border:1px solid #e5e7eb;">
    <tr><td style="padding:28px;">
        <h2 style="margin:0 0 6px; font-size:20px;">Did this rent arrive?</h2>
        <p style="margin:0 0 18px; color:#6b7280; font-size:14px;">
            {{ $payments->count() }} payment{{ $payments->count() === 1 ? '' : 's' }} waiting ·
            {{ $byCur->map(fn ($t, $c) => $c.' '.number_format($t, 2))->implode(' · ') }}
        </p>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; border-collapse:collapse;">
            <th scope="col"ead>
                <tr style="text-align:left; color:#6b7280; font-size:12px; text-transform:uppercase;">
                    <th scope="col" style="padding:6px 8px 6px 0; border-bottom:1px solid #e5e7eb;">Property · tenant</th>
                    <th scope="col" style="padding:6px 8px; border-bottom:1px solid #e5e7eb;">Period</th>
                    <th scope="col" style="padding:6px 8px; border-bottom:1px solid #e5e7eb; text-align:right;">Amount</th>
                    <th scope="col" style="padding:6px 0 6px 8px; border-bottom:1px solid #e5e7eb; text-align:right;">Answer</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payments as $p)
                @php $late = $p->daysLate(); @endphp
                <tr>
                    <td style="padding:10px 8px 10px 0; border-bottom:1px solid #f0f0f0; vertical-align:top;">
                        <div style="font-weight:600;">{{ $p->asset?->name ?? 'Property' }}</div>
                        <div style="color:#6b7280;">{{ $p->tenantName() ?? 'No tenant' }}</div>
                    </td>
                    <td style="padding:10px 8px; border-bottom:1px solid #f0f0f0; vertical-align:top; white-space:nowrap;">
                        {{ $p->periodLabel() }}
                        <div style="color:{{ $late > 0 ? '#dc3545' : '#6b7280' }};">due {{ optional($p->due_date)->format('d M') }}{{ $late > 0 ? ' · '.$late.'d late' : '' }}</div>
                    </td>
                    <td style="padding:10px 8px; border-bottom:1px solid #f0f0f0; vertical-align:top; text-align:right; white-space:nowrap; font-weight:600;">
                        {{ $p->currency }} {{ number_format((float) $p->amount, 2) }}
                    </td>
                    <td style="padding:10px 0 10px 8px; border-bottom:1px solid #f0f0f0; vertical-align:top; text-align:right; white-space:nowrap;">
                        <a href="{{ $links[$p->id]['received'] }}" style="display:inline-block; background:#198754; color:#fff; text-decoration:none; padding:7px 12px; border-radius:6px; font-weight:600;">Yes</a>
                        <a href="{{ $links[$p->id]['not_received'] }}" style="display:inline-block; background:#fff; color:#dc3545; border:1px solid #dc3545; text-decoration:none; padding:6px 12px; border-radius:6px; font-weight:600; margin-left:4px;">No</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <p style="font-size:13px; color:#6b7280; margin:18px 0 8px;">
            Each link opens a page with one button to confirm. Anything left unanswered is listed again in a few days.
            To correct an amount or upload a manager's statement, use the portal.
        </p>
        <p style="font-size:13px; color:#6b7280; margin:0;">
            All open items: <a href="{{ route('payments.unconfirmed') }}" style="color:#0d6efd;">{{ route('payments.unconfirmed') }}</a>
        </p>
    </td></tr>
</table>
</body>
</html>
