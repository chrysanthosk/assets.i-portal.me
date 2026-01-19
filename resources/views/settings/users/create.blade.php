@extends('layouts.app')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Create User</h1>
    <a href="{{ route('settings.users.index') }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-2"></i> Back
    </a>
  </div>

  <div class="card">
    <div class="card-header">
      <strong>User Details</strong>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('settings.users.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username"
                   class="form-control @error('username') is-invalid @enderror"
                   value="{{ old('username') }}" required>
            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="surname"
                   class="form-control @error('surname') is-invalid @enderror"
                   value="{{ old('surname') }}" required>
            @error('surname') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Permission Set</label>
            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
              @foreach($roles as $r)
                <option value="{{ $r->name }}" @selected(old('role') === $r->name)>{{ $r->name }}</option>
              @endforeach
            </select>
            @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required autocomplete="new-password"
                   data-password-meter="create-user-password">
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror

            <div class="mt-2">
              <div class="small text-muted" id="create-user-password-text"></div>
              <div class="progress" style="height: 8px;">
                <div id="create-user-password-bar" class="progress-bar" style="width:0%"></div>
              </div>
            </div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation"
                   class="form-control"
                   required autocomplete="new-password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-2"></i> Create User
        </button>
      </form>
    </div>
  </div>

</div>
@endsection
