@extends('layouts.guest')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Two-Factor Authentication</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Enter the 6-digit code from your Authenticator app (or a recovery code).
        </p>

        <form method="POST" action="{{ route('2fa.verify') }}">
            @csrf

            <div class="mb-3">
                <input
                    type="text"
                    name="code"
                    class="form-control"
                    placeholder="123456 or RECOVERYCODE"
                    required
                    autofocus
                >
            </div>

            <button class="btn btn-primary w-100">Verify</button>

            <div class="text-center mt-3">
                <a href="{{ route('login') }}" class="small text-muted">Back to login</a>
            </div>
        </form>
    </div>
</div>
@endsection
