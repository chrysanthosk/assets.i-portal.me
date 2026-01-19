@extends('layouts.app')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Users</h1>
    <a href="{{ route('settings.users.create') }}" class="btn btn-primary">
      <i class="bi bi-person-plus me-2"></i> Add User
    </a>
  </div>

  <div class="card">
    <div class="card-header">
      <strong>User List</strong>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:70px;">#</th>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Email</th>
              <th style="width:140px;">Role</th>
              <th style="width:190px;">Created</th>
              <th style="width:160px;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $u)
              <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->name ?? '—' }}</td>
                <td>{{ $u->surname ?? '—' }}</td>
                <td><span class="font-monospace">{{ $u->email }}</span></td>
                <td>
                  @php $r = $u->getRoleNames()->first(); @endphp
                  <span class="badge {{ $r === 'Admin' ? 'bg-danger' : 'bg-secondary' }}">
                    {{ strtoupper($r ?? 'NONE') }}
                  </span>
                </td>
                <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
                <td class="text-end">

                  <a href="{{ route('settings.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <button type="button"
                          class="btn btn-sm btn-outline-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteUserModal"
                          data-user-id="{{ $u->id }}"
                          data-user-email="{{ $u->email }}"
                          data-delete-url="{{ route('settings.users.destroy', $u) }}"
                          {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                    <i class="bi bi-trash"></i>
                  </button>

                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted p-4">No users found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-footer">
      {{ $users->links() }}
    </div>
  </div>

</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete user</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="mb-1">Are you sure you want to delete this user?</p>
        <div class="small text-muted">
          <span id="deleteUserEmail"></span>
        </div>
        <div class="alert alert-warning mt-3 mb-0">
          This action cannot be undone.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>

        <form method="POST" id="deleteUserForm">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-2"></i>Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('deleteUserModal');
  const form = document.getElementById('deleteUserForm');
  const emailEl = document.getElementById('deleteUserEmail');

  modal.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    const email = btn.getAttribute('data-user-email');
    const url = btn.getAttribute('data-delete-url');

    emailEl.textContent = email || '';
    form.setAttribute('action', url);
  });
});
</script>
@endsection
