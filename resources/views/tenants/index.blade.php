@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Tenants</h5>
                    <small class="text-muted">People renting your assets</small>
                </div>
                <form method="GET" action="{{ route('tenants.index') }}" class="d-flex gap-2 align-items-center ms-auto">
                    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm"
                           placeholder="Search name / email / phone" style="min-width: 240px;">
                    <button class="btn btn-sm btn-outline-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
                </form>
            </div>

            <div class="card-body">

                {{-- Add new --}}
                <form method="POST" action="{{ route('tenants.store') }}" class="row g-3 mb-4">
                    @csrf
                    <div class="col-12"><h6 class="mb-0">Add Tenant</h6><hr class="mt-1"></div>

                    <div class="col-md-4">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="form-control @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ID / Passport No.</label>
                        <input type="text" name="id_number" value="{{ old('id_number') }}"
                               class="form-control @error('id_number') is-invalid @enderror">
                        @error('id_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                               class="form-control @error('notes') is-invalid @enderror">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Tenant</button>
                    </div>
                </form>

                {{-- Existing --}}
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">ID No.</th>
                            <th scope="col">Rentals</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($tenants as $t)
                        <tr>
                            <form method="POST" action="{{ route('tenants.update', $t) }}">
                                @csrf @method('PUT')
                                <td><input type="text" name="name" value="{{ $t->name }}" class="form-control form-control-sm" required></td>
                                <td><input type="email" name="email" value="{{ $t->email }}" class="form-control form-control-sm"></td>
                                <td><input type="text" name="phone" value="{{ $t->phone }}" class="form-control form-control-sm"></td>
                                <td><input type="text" name="id_number" value="{{ $t->id_number }}" class="form-control form-control-sm"></td>
                                <td><span class="badge text-bg-secondary">{{ $t->rentals_count }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" aria-label="Save tenant"><i class="bi bi-save"></i></button>
                            </form>
                                    <form method="POST" action="{{ route('tenants.destroy', $t) }}" class="d-inline"
                                          onsubmit="return confirm('Delete tenant {{ addslashes($t->name) }}? Their rentals keep the recorded name.');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete tenant"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            @if($q) No tenants match “{{ $q }}”. @else No tenants yet — add one above. @endif
                        </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <small class="text-muted">{{ $tenants->total() }} total</small>
                    {{ $tenants->links() }}
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
