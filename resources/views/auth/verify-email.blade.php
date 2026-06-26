@extends('layouts.guest')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header text-center"><b>assets.i-portal.me</b></div>
    <div class="card-body">
        <p class="text-muted small">
            Thanks for signing up! Please verify your email by clicking the link we just emailed you.
            If you didn't receive it, request another below.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="alert alert-success">A new verification link has been sent to your email.</div>
        @endif

        <div class="d-flex justify-content-between align-items-center">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Resend Verification Email</button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link">Log Out</button>
            </form>
        </div>
    </div>
</div>
@endsection
