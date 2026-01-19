@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header">
                Two-Factor Authentication
            </div>
            <div class="card-body">
                <p class="mb-3">Enter the 6-digit code from Google Authenticator.</p>

                <form method="POST" action="{{ route('2fa.verify') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" inputmode="numeric" maxlength="6" name="otp" class="form-control" placeholder="123456" required>
                    </div>

                    <button class="btn btn-primary w-100">Verify</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
