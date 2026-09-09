@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <h5 class="mb-0">Property types</h5>
                <span class="text-muted small">Configure the list used as the property Type</span>
            </div>

            <div class="card-body">

                {{-- Add new --}}
                <form method="POST" action="{{ route('settings.assetTypes.store') }}" class="row g-3 mb-4">
                    @csrf

                    <div class="col-12">
                        <h6 class="mb-0">Add New Type</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror"
                            placeholder="e.g. Apartment"
                            required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input
                            type="number"
                            name="sort_order"
                            value="{{ old('sort_order', 0) }}"
                            class="form-control @error('sort_order') is-invalid @enderror"
                            min="0"
                            max="100000">
                        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label d-block">Active</label>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="is_active_new" name="is_active" value="1"
                                   @checked((int)old('is_active', 1) === 1)>
                            <label class="form-check-label" for="is_active_new">Enabled</label>
                        </div>
                        @error('is_active') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Add Type
                        </button>
                    </div>
                </form>

                {{-- Existing list --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h6 class="mb-0">Existing Types</h6>
                    <span class="text-muted small">{{ $types->count() }} total</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <th scope="col"ead>
                        <tr>
                            <th scope="col" style="width: 45%">Name</th>
                            <th scope="col" style="width: 15%">Sort</th>
                            <th scope="col" style="width: 15%">Active</th>
                            <th scope="col" style="width: 25%" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($types as $t)
                        <tr>
                            @php $fid = 'row-'.$t->id; @endphp
                            <td>
                                <input type="text" name="name" form="{{ $fid }}" value="{{ old('name', $t->name) }}" class="form-control form-control-sm" aria-label="Name" required>
                            </td>

                            <td>
                                <input type="number" name="sort_order" form="{{ $fid }}" value="{{ old('sort_order', $t->sort_order) }}" class="form-control form-control-sm" aria-label="Sort order" min="0" max="100000">
                            </td>

                            <td>
                                <input type="hidden" name="is_active" form="{{ $fid }}" value="0">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" form="{{ $fid }}"
                                           id="active_{{ $t->id }}"
                                           name="is_active" value="1"
                                           @checked(old('is_active', $t->is_active ? 1 : 0) == 1)>
                                    <label class="form-check-label small" for="active_{{ $t->id }}">Yes</label>
                                </div>
                            </td>

                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('settings.assetTypes.update', $t) }}" id="{{ $fid }}" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-save me-1"></i> Save</button>
                                </form>

                                <form method="POST" action="{{ route('settings.assetTypes.destroy', $t) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete property type: {{ addslashes($t->name) }} ? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No property types yet.
                            </td>
                        </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="text-muted small mt-3">
                    Tip: Keep a low sort order (0, 10, 20…) to control the dropdown order.
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
