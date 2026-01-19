@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-12">

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Permission Sets</h5>

        <form method="POST" action="{{ route('settings.permissionSets.storeRole') }}" class="d-flex gap-2">
          @csrf
          <input type="text" name="role_name" class="form-control form-control-sm" placeholder="New permission set name" required>
          <button class="btn btn-sm btn-primary">Create</button>
        </form>
      </div>

      <div class="card-body">

        @foreach ($roles as $role)
          <div class="border rounded p-3 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <h6 class="mb-0">{{ $role->name }}</h6>
                <small class="text-muted">Tick permissions to allow access</small>
              </div>

              @if (!in_array($role->name, ['Admin','User'], true))
                <form method="POST" action="{{ route('settings.permissionSets.destroyRole', $role) }}">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              @endif
            </div>

            <form method="POST" action="{{ route('settings.permissionSets.updateRolePermissions', $role) }}">
              @csrf

              @php
                $rolePerms = $role->permissions->pluck('name')->toArray();
              @endphp

              <div class="row">
                @foreach ($groupedPermissions as $groupName => $perms)
                  <div class="col-12 col-lg-4 mb-3">
                    <div class="border rounded p-2 h-100">
                      <div class="fw-semibold mb-2">{{ $groupName }}</div>

                      @foreach ($perms as $permName => $meta)
                        @php
                          $label = $meta['label'] ?? $permName;
                          $checked = in_array($permName, $rolePerms, true);
                        @endphp

                        <div class="form-check">
                          <input
                            class="form-check-input"
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permName }}"
                            id="{{ $role->id }}_{{ $permName }}"
                            {{ $checked ? 'checked' : '' }}
                          >
                          <label class="form-check-label" for="{{ $role->id }}_{{ $permName }}">
                            {{ $label }}
                            <small class="text-muted">({{ $permName }})</small>
                          </label>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endforeach
              </div>

              <div class="mt-2">
                <button class="btn btn-sm btn-primary">Save Permissions</button>
              </div>

            </form>
          </div>
        @endforeach

      </div>
    </div>

  </div>
</div>
@endsection
