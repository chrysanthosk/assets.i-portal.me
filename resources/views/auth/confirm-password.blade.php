@extends('layouts.guest')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header text-center"><b>assets.i-portal.me</b></div>
    <div class="card-body">
        <p class="text-muted small">
            This is a secure area. Please confirm your password before continuing.
        </p>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="current-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-100">Confirm</button>
        </form>
    </div>
</div>
@endsection
