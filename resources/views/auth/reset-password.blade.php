@extends('layouts.guest')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header text-center"><b>assets.i-portal.me</b></div>
    <div class="card-body">
        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}"
                       class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password"
                       class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       class="form-control" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>
</div>
@endsection
