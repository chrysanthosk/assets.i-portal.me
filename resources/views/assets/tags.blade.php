@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-12">

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="mb-0">Asset Tags</h5>
          <small class="text-muted">Create tags like “Paphos”, “Airbnb”, “Commercial”</small>
        </div>
      </div>

      <div class="card-body">

        <form method="POST" action="{{ route('assets.tags.store') }}" class="row g-2 align-items-end mb-4">
          @csrf
          <div class="col-md-6">
            <label class="form-label">New Tag</label>
            <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Paphos" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th style="width: 70px;">#</th>
                <th>Name</th>
                <th class="text-end" style="width: 180px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tags as $tag)
                <tr>
                  <td class="text-muted">{{ $tag->id }}</td>
                  <td class="fw-semibold">{{ $tag->name }}</td>
                  <td class="text-end">

                    <button
                      class="btn btn-sm btn-outline-primary me-1"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#editTagModal"
                      data-id="{{ $tag->id }}"
                      data-name="{{ e($tag->name) }}">
                      <i class="bi bi-pencil"></i>
                    </button>

                    <button
                      class="btn btn-sm btn-outline-danger"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#deleteTagModal"
                      data-url="{{ route('assets.tags.destroy', $tag) }}"
                      data-body="Delete tag <b>{{ e($tag->name) }}</b>? This will remove it from all assets.">
                      <i class="bi bi-trash"></i>
                    </button>

                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="3" class="text-center text-muted py-4">No tags yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-3">
          {{ $tags->links() }}
        </div>

      </div>
    </div>

  </div>
</div>

<!-- Edit Tag Modal -->
<div class="modal fade" id="editTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Tag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" id="editTagForm">
        @csrf
        @method('PUT')

        <div class="modal-body">
          <label class="form-label">Tag Name</label>
          <input class="form-control" name="name" id="editTagName" required>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Delete Tag Modal -->
<div class="modal fade" id="deleteTagModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Tag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="deleteTagBody">Are you sure?</div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" id="deleteTagForm">
          @csrf
          @method('DELETE')
          <button class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('editTagModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');

      // PUT route is /assets/tags/{tag}
      const action = "{{ url('/assets/tags') }}/" + id;

      document.getElementById('editTagForm').setAttribute('action', action);
      document.getElementById('editTagName').value = name || '';
    });
  }

  const deleteModal = document.getElementById('deleteTagModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      const url = btn.getAttribute('data-url');
      const body = btn.getAttribute('data-body') || 'Are you sure?';

      document.getElementById('deleteTagBody').innerHTML = body;
      document.getElementById('deleteTagForm').setAttribute('action', url);
    });
  }
});
</script>
@endsection
