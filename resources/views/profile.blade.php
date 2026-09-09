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
                     style="white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ implode("\n", session('2fa_backup_codes')) }}</pre>
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
