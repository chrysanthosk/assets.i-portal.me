@extends('layouts.app')

@section('content')
<div class="container py-4">

    @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <h3 class="mb-4">Profile</h3>

    <div class="card mb-4">
        <div class="card-header">Two-Factor Authentication (2FA)</div>
        <div class="card-body">

            @if ($errors->has('two_factor'))
            <div class="alert alert-danger">{{ $errors->first('two_factor') }}</div>
            @endif

            @if (auth()->user()->two_factor_enabled && auth()->user()->two_factor_secret)
            <div class="alert alert-info">
                2FA is <strong>enabled</strong> on your account.
            </div>

            @if (session('2fa_show_backup_codes') && session('2fa_backup_codes'))
            <div class="alert alert-warning">
                <strong>Important:</strong> These are your recovery codes. Save them now. You will not be able to view them again.
            </div>

            <div class="mb-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="button" onclick="copyBackupCodes()">Copy recovery codes</button>
            </div>

            <pre id="backupCodesBox" class="p-3 bg-light border rounded" style="white-space: pre-wrap;">{{ implode("\n", session('2fa_backup_codes')) }}</pre>
            @endif

            <form method="POST" action="{{ route('2fa.disable') }}" onsubmit="return confirm('Disable 2FA for your account?');">
                @csrf
                <button class="btn btn-danger">Disable 2FA</button>
            </form>
            @else
            <div class="alert alert-secondary">
                2FA is <strong>not enabled</strong>.
            </div>

            <form method="POST" action="{{ route('2fa.enable') }}">
                @csrf
                <button class="btn btn-success">Enable 2FA</button>
            </form>
            @endif
        </div>
    </div>

</div>

<script>
    function copyBackupCodes() {
        const el = document.getElementById('backupCodesBox');
        if (!el) return;

        const text = el.innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            alert('Recovery codes copied to clipboard.');
        }).catch(() => {
            alert('Could not copy. Please select and copy manually.');
        });
    }
</script>
@endsection
