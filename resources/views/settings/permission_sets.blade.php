@extends('layouts.app')

@section('content')
<div class="row">

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Create Permission Set</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('settings.permissionSets.storeRole') }}" class="row g-3">
          @csrf
          <div class="col-12">
            <label class="form-label">Permission Set Name</label>
            <input class="form-control" name="role_name" placeholder="e.g. Finance" required>
            <small class="text-muted">Default access will be Dashboard only.</small>
          </div>
          <div class="col-12">
            <button class="btn btn-primary">Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Permission Matrix</h5></div>
      <div class="card-body">

        @foreach($roles as $role)
          <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0">{{ $role->name }}</h6>

              @if(!in_array($role->name, ['Admin','User'], true))
                <form method="POST" action="{{ route('settings.permissionSets.destroyRole', $role) }}"
                      onsubmit="return confirm('Delete this permission set?');">
                  @csrf
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
              @endif
            </div>

            <form method="POST" action="{{ route('settings.permissionSets.updateRolePermissions', $role) }}" class="mt-3">
              @csrf

              <div class="row">
                @foreach($permissions as $perm)
                  @php
                    $label = $labels[$perm->name] ?? $perm->name;
                    $has = $role->hasPermissionTo($perm->name);
                  @endphp

                  <div class="col-md-6 mb-2">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="permissions[]"
                             value="{{ $perm->name }}"
                             id="{{ $role->id }}_{{ $perm->id }}"
                             @checked($has)>
                      <label class="form-check-label" for="{{ $role->id }}_{{ $perm->id }}">
                        {{ $label }}
                        <small class="text-muted">({{ $perm->name }})</small>
                      </label>
                    </div>
                  </div>
                @endforeach
              </div>

              <button class="btn btn-success mt-2">Save Permissions</button>
            </form>
          </div>
        @endforeach

      </div>
    </div>
  </div>
</div>
@endsection
