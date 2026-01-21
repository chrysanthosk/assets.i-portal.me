@extends('layouts.app')

@section('content')

<div class="row">

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
                        <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input
                            class="form-control @error('password') is-invalid @enderror"
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
                        <small class="text-muted d-block mt-1">Strength: <span id="profile-password-text"></span></small>
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

                {{-- =========================================================
                SHOW BACKUP CODES (ONLY ONCE) - RIGHT AFTER CONFIRM
                Controller returns session('2fa_backup_codes') after confirm()
                ========================================================= --}}
                @if (session('2fa_backup_codes') && is_array(session('2fa_backup_codes')) && count(session('2fa_backup_codes')) > 0)
                <div class="alert alert-warning">
                    <div class="d-flex align-items-start">
                        <div class="me-2">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">Store these backup codes now</div>
                            <div class="small">
                                You will only see these codes <b>once</b>. Save them somewhere safe (password manager recommended).
                                Each code can be used once if you lose access to your authenticator.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-warning mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">Backup codes (shown once)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyBackupCodes()">
                            <i class="bi bi-clipboard me-1"></i>Copy
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2" id="backupCodesGrid">
                            @foreach(session('2fa_backup_codes') as $code)
                            <div class="col-6">
                                <code class="d-block p-2 bg-body-tertiary rounded text-center">{{ $code }}</code>
                            </div>
                            @endforeach
                        </div>

                        <input type="hidden" id="backupCodesText" value="{{ implode(PHP_EOL, session('2fa_backup_codes')) }}">
                        <div class="small text-muted mt-2" id="backupCopyStatus"></div>
                    </div>
                </div>
                @endif

                @if (!$user->two_factor_enabled)
                <form method="POST" action="{{ route('profile.2fa.enable') }}">
                    @csrf
                    <button class="btn btn-outline-primary"><i class="bi bi-shield-check me-2"></i>Enable 2FA</button>
                </form>

                @if (session('2fa_qr_url'))
                <div class="mt-3">
                    <p class="mb-1">Scan this QR code in Google Authenticator:</p>
                    <div class="p-2 bg-white d-inline-block rounded">
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

                <form method="POST" action="{{ route('profile.2fa.disable') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" required>
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

<script>
    function copyBackupCodes() {
        const status = document.getElementById('backupCopyStatus');
        const el = document.getElementById('backupCodesText');
        if (!el) return;

        const text = el.value || '';
        if (!text.trim()) return;

        const done = () => {
            if (status) {
                status.textContent = 'Copied to clipboard.';
            }
        };

        const fail = () => {
            if (status) {
                status.textContent = 'Copy failed. Please select and copy manually.';
            }
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fail);
        } else {
            // fallback for older browsers / non-secure context
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? done() : fail();
            } catch (e) {
                fail();
            }
        }
    }
</script>

@endsection
