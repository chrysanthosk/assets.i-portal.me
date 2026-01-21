@extends('layouts.app')

@section('content')
<div class="container py-4">

    @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('two_factor'))
    <div class="alert alert-danger">{{ $errors->first('two_factor') }}</div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Enable Two-Factor Authentication (2FA)</h5>
                </div>

                <div class="card-body">
                    <p class="text-muted mb-3">
                        Scan the QR code using <strong>Google Authenticator</strong> (or any authenticator app),
                        then enter the 6-digit code below to confirm and enable 2FA.
                    </p>

                    <div class="row g-4">
                        <div class="col-md-5 text-center">
                            <div class="p-3 border rounded d-inline-block bg-body text-body">
                                <img
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}"
                                    alt="2FA QR Code"
                                    style="max-width: 200px; height: auto;"
                                >
                            </div>

                            <div class="mt-3 text-start">
                                <div class="small text-muted mb-1">Manual setup key:</div>
                                <code class="d-inline-block p-2 border rounded bg-body text-body">{{ $secret }}</code>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <h6 class="mb-2">Step 2: Confirm code</h6>

                            <form method="POST" action="{{ route('profile.2fa.confirm') }}" class="row g-3">
                                @csrf

                                <div class="col-12">
                                    <label class="form-label">6-digit code</label>
                                    <input
                                        class="form-control @error('code') is-invalid @enderror"
                                        name="code"
                                        maxlength="6"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        placeholder="123456"
                                        value="{{ old('code') }}"
                                        required
                                    >
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button class="btn btn-primary">
                                        <i class="bi bi-check2-circle me-2"></i>Confirm & Enable
                                    </button>

                                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </form>

                            <hr class="my-4">

                            <h6 class="mb-2">Recovery codes</h6>
                            <div class="alert alert-warning">
                                <strong>Save these recovery codes now.</strong>
                                If you lose access to your authenticator app, you can use a recovery code to sign in.
                            </div>

                            <div class="mb-2 d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyRecoveryCodes()">
                                    Copy recovery codes
                                </button>
                            </div>

                                <pre id="recoveryCodesBox"
                                    class="p-3 border rounded bg-body text-body"
                                    style="white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
                                    {{ implode("\n", $recoveryCodes ?? []) }}
                                </pre>

                            <small class="text-muted d-block mt-2">
                                Tip: store them in a password manager.
                            </small>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

</div>

<script>
    function copyRecoveryCodes() {
        const el = document.getElementById('recoveryCodesBox');
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
