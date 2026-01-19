@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Two-Factor Authentication</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Enter the 6-digit code from your Google Authenticator app.</p>

    <form method="POST" action="{{ route('2fa.verify') }}" class="row g-3">
      @csrf
      <div class="col-md-4">
        <input type="text" name="code" class="form-control" placeholder="123456" required maxlength="6">
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Verify</button>
      </div>
    </form>
  </div>
</div>
@endsection
