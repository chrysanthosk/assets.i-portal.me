@extends('layouts.guest')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header text-center"><b>assets.i-portal.me</b></div>
    <div class="card-body">
        <p class="text-muted small">
            Forgot your password? Enter your email and we'll send you a reset link.
        </p>

        @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a class="small" href="{{ route('login') }}">Back to login</a>
                <button type="submit" class="btn btn-primary">Email Reset Link</button>
            </div>
        </form>
    </div>
</div>
@endsection
