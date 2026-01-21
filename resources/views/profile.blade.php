@extends('layouts.app')

@section('content')

<div class="row">

    {{-- STATUS + GLOBAL ERRORS --}}
    <div class="col-12">
        @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->has('two_factor'))
        <div class="alert alert-danger">{{ $errors->first('two_factor') }}</div>
        @endif
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Profile</h5></div>
            <div class="card-body">

                <form method="POST" action="{{ route('profile.updateName') }}" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Surname</label>
                        <input class="form-control @error('surname') is-invalid @enderror" name="surname" value="{{ old('surname', $user->surname) }}" required>
                        @error('surname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-save me-2"></i>Save</button>
                    </div>
                </form>

                <hr>

                <h6>Change Email (OTP confirmation)</h6>
                <form method="POST" action="{{ route('profile.requestEmailChange') }}" class="row g-3">
                    @csrf

                    <div class="col-12">
                        <label class="form-label">New Email</label>
                        <input class="form-control @error('new_email') is-invalid @enderror" name="new_email" type="email" placeholder="new@email.com" value="{{ old('new_email') }}" required>
                        @error('new_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <button class="btn btn-outline-primary"><i class="bi bi-envelope me-2"></i>Send OTP</button>
                    </div>
                </form>

                @if ($user->pending_email)
                <div class="alert alert-info mt-3">
                    OTP sent to <b>{{ $user->pending_email }}</b>. Enter it to confirm.
                </div>

                <form method="POST" action="{{ route('profile.confirmEmailChange') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">OTP</label>
                        <input class="form-control @error('otp') is-invalid @enderror" name="otp" maxlength="6" placeholder="123456" value="{{ old('otp') }}" required>
                        @error('otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check2-circle me-2"></i>Confirm Email</button>
                    </div>
                </form>
                @endif

            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Security</h5></div>
            <div class="card-body">

                <h6>Change Password</h6>
                <form method="POST" action="{{ route('profile.updatePassword') }}" class="row g-3" id="pwForm">
                    @csrf

                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input
                            class="form-control @error('password') is-invalid @enderror"
                            id="profile-password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            data-password-meter="profile-password"
                            required
                        >
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar" id="profile-password-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted d-block mt-1">Strength: <span id="profile-password-text">-</span></small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Confirm New Password</label>
                        <input class="form-control @error('password_confirmation') is-invalid @enderror" type="password" name="password_confirmation" autocomplete="new-password" required>
                        @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-shield-lock me-2"></i>Update Password</button>
                    </div>
                </form>

                <hr>

                <h6>Two-Factor Authentication (Google Authenticator)</h6>

                {{-- 2FA ERRORS --}}
                @error('code')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                @if (!$user->two_factor_enabled)
                <form method="POST" action="{{ route('profile.2fa.enable') }}">
                    @csrf
                    <button class="btn btn-outline-primary"><i class="bi bi-shield-check me-2"></i>Enable 2FA</button>
                </form>

                @if (session('2fa_qr_url'))
                <div class="mt-3">
                    <p class="mb-1">Scan this QR code in Google Authenticator:</p>
                    <div class="p-2 d-inline-block rounded border bg-body text-body">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode(session('2fa_qr_url')) }}"
                             alt="2FA QR Code">
                    </div>

                    <p class="mt-2 mb-1"><small class="text-muted">Or enter secret manually:</small></p>
                    <code>{{ session('2fa_secret') }}</code>

                    <form method="POST" action="{{ route('profile.2fa.confirm') }}" class="row g-3 mt-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Code</label>
                            <input class="form-control @error('code') is-invalid @enderror" name="code" maxlength="6" placeholder="123456" value="{{ old('code') }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="bi bi-check2-circle me-2"></i>Confirm & Enable</button>
                        </div>
                    </form>
                </div>
                @endif
                @else
                <div class="alert alert-success">2FA is enabled.</div>

                @if (session('2fa_show_backup_codes') && session('2fa_backup_codes'))
                <div class="alert alert-warning">
                    <strong>Important:</strong> These are your recovery codes. Save them now. You will not be able to view them again.
                </div>

                <div class="mb-2 d-flex gap-2">
                    <button class="btn btn-sm btn-primary" type="button" onclick="copyBackupCodes()">Copy recovery codes</button>
                </div>

                    <pre id="backupCodesBox"
                     class="p-3 border rounded bg-body text-body"
                     style="white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                    {{ implode("\n", session('2fa_backup_codes')) }}
                    </pre>
                @endif

                <form method="POST" action="{{ route('profile.2fa.disable') }}" class="row g-3" onsubmit="return confirm('Disable 2FA for your account?');">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Authenticator Code</label>
                        <input class="form-control @error('code') is-invalid @enderror" name="code" maxlength="6" placeholder="123456" value="{{ old('code') }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button class="btn btn-danger"><i class="bi bi-shield-x me-2"></i>Disable 2FA</button>
                    </div>
                </form>
                @endif

            </div>
        </div>
    </div>

</div>

{{-- Scripts --}}
<script>
    // Password strength meter (no external libs)
    (function () {
        const input = document.getElementById('profile-password');
        const bar   = document.getElementById('profile-password-bar');
        const text  = document.getElementById('profile-password-text');

        if (!input || !bar || !text) return;

        function scorePassword(pwd) {
            let score = 0;
            if (!pwd) return 0;

            // length
            if (pwd.length >= 8) score++;
            if (pwd.length >= 12) score++;

            // character variety
            if (/[a-z]/.test(pwd)) score++;
            if (/[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;

            return Math.min(score, 6);
        }

        function render() {
            const pwd = input.value || '';
            const s = scorePassword(pwd);
            const pct = Math.round((s / 6) * 100);

            bar.style.width = pct + '%';

            let label = '-';
            let cls = 'bg-secondary';

            if (!pwd.length) {
                label = '-';
                cls = 'bg-secondary';
            } else if (s <= 2) {
                label = 'Weak';
                cls = 'bg-danger';
            } else if (s <= 4) {
                label = 'Medium';
                cls = 'bg-warning';
            } else {
                label = 'Strong';
                cls = 'bg-success';
            }

            bar.className = 'progress-bar ' + cls;
            text.textContent = label;
        }

        input.addEventListener('input', render);
        render();
    })();

    function copyBackupCodes() {
        const el = document.getElementById('backupCodesBox');
        if (!el) return;

        const txt = el.innerText.trim();
        navigator.clipboard.writeText(txt).then(() => {
            alert('Recovery codes copied to clipboard.');
        }).catch(() => {
            alert('Could not copy. Please select and copy manually.');
        });
    }
</script>

@endsection
