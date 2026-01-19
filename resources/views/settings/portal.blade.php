@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-header"><h5 class="mb-0">Portal Settings</h5></div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.portal.update') }}" class="row g-3">
      @csrf
      <div class="col-md-6">
        <label class="form-label">Portal Name</label>
        <input class="form-control" name="portal_name" value="{{ old('portal_name', $portalName) }}" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection
