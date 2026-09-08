@php $expired = $documents->filter(fn ($d) => $d->expires_at->isPast()); $soon = $documents->reject(fn ($d) => $d->expires_at->isPast()); @endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Document expiry</title></head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#222; background:#f6f7f9; margin:0; padding:24px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px; margin:0 auto; background:#fff; border-radius:8px; border:1px solid #e5e7eb;">
    <tr><td style="padding:28px;">
        <h2 style="margin:0 0 6px; font-size:20px;">Documents that need attention</h2>
        <p style="margin:0 0 20px; color:#6b7280; font-size:14px;">{{ $expired->count() }} expired · {{ $soon->count() }} expiring within {{ $days }} days</p>

        @foreach([['Expired', $expired, '#dc3545'], ['Expiring soon', $soon, '#f59e0b']] as [$title, $rows, $color])
            @if($rows->isNotEmpty())
                <h3 style="font-size:14px; margin:18px 0 8px; color:{{ $color }};">{{ $title }}</h3>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; border-collapse:collapse;">
                    @foreach($rows as $d)
                        <tr style="border-top:1px solid #eee;">
                            <td style="padding:8px 8px 8px 0;">
                                <a href="{{ route('assets.show', ['asset' => $d->asset_id, 'tab' => 'documents']) }}" style="color:#0d6efd; text-decoration:none; font-weight:600;">{{ $d->title ?: $d->original_name }}</a>
                                <div style="color:#6b7280;">{{ $d->asset?->name ?? 'Property #'.$d->asset_id }}@if($d->doc_type) · {{ $d->doc_type }}@endif</div>
                            </td>
                            <td style="padding:8px 0; text-align:right; white-space:nowrap; color:{{ $color }};">{{ $d->expires_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        @endforeach

        <p style="font-size:13px; color:#6b7280; margin:20px 0 0;">Sent weekly while something is expired or expiring. Update the expiry date on the document to clear it.</p>
    </td></tr>
</table>
</body>
</html>
