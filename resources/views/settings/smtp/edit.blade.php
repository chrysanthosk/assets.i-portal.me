@extends('layouts.app')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Settings / SMTP</h1>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong>SMTP Configuration</strong>

      <div class="text-muted small">
        Last test:
        <strong>
          @if(!empty($smtp?->last_tested_at))
            {{ $smtp->last_tested_at->format('Y-m-d H:i:s') }}
          @else
            Never
          @endif
        </strong>
      </div>
    </div>

    <div class="card-body">

      {{-- SAVE SETTINGS --}}
      <form method="POST" action="{{ route('settings.smtp.update') }}">
        @csrf
        @method('PUT')

        <div class="form-check mb-3">
          <input type="hidden" name="enabled" value="0">
          <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                 @checked(old('enabled', (int)($smtp?->enabled ?? 0)) === 1)>
          <label class="form-check-label" for="enabled">
            Enable SMTP
          </label>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Host</label>
            <input type="text" name="host"
                   class="form-control @error('host') is-invalid @enderror"
                   value="{{ old('host', $smtp?->host) }}" autocomplete="off">
            @error('host') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Port</label>
            <input type="number" name="port"
                   class="form-control @error('port') is-invalid @enderror"
                   value="{{ old('port', $smtp?->port) }}" min="1" max="65535">
            @error('port') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Encryption</label>
            <select name="encryption" class="form-select @error('encryption') is-invalid @enderror">
              <option value="" @selected(old('encryption', $smtp?->encryption) === null || old('encryption', $smtp?->encryption) === '')>None</option>
              <option value="tls" @selected(old('encryption', $smtp?->encryption) === 'tls')>TLS</option>
              <option value="ssl" @selected(old('encryption', $smtp?->encryption) === 'ssl')>SSL</option>
            </select>
            @error('encryption') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username"
                   class="form-control @error('username') is-invalid @enderror"
                   value="{{ old('username', $smtp?->username) }}" autocomplete="off">
            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   value="" autocomplete="new-password">
            <div class="text-muted small mt-1">Leave blank to keep existing password.</div>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">From Address</label>
            <input type="email" name="from_address"
                   class="form-control @error('from_address') is-invalid @enderror"
                   value="{{ old('from_address', $smtp?->from_address) }}">
            @error('from_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">From Name</label>
            <input type="text" name="from_name"
                   class="form-control @error('from_name') is-invalid @enderror"
                   value="{{ old('from_name', $smtp?->from_name) }}">
            @error('from_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-2">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-2"></i> Save SMTP Settings
          </button>
        </div>
      </form>

      <hr>

      {{-- TEST SMTP --}}
      <form method="POST" action="{{ route('settings.smtp.test') }}">
        @csrf

        <div class="row align-items-end">
          <div class="col-md-6 mb-3">
            <label class="form-label">Test Email Address</label>
            <input type="email" name="test_email"
                   class="form-control @error('test_email') is-invalid @enderror"
                   value="{{ old('test_email', auth()->user()->email ?? '') }}"
                   placeholder="you@company.com" required>
            <div class="text-muted small mt-1">
              Sends a test email using the current saved SMTP settings.
            </div>
            @error('test_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3 mb-3">
            <button type="submit"
                    class="btn btn-outline-secondary w-100"
                    {{ empty($smtp?->enabled) ? 'disabled' : '' }}>
              <i class="bi bi-send me-2"></i> Send Test Email
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>

</div>
@endsection
