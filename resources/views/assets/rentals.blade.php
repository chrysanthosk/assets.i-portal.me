@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Rental Agreements</h5>
                    <small class="text-muted">Store rental agreements once; dashboard calculates monthly income automatically.</small>
                </div>
            </div>

            <div class="card-body">
                <form method="GET" action="{{ route('assets.rentals.index') }}" class="row g-2 align-items-end mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Filter by Asset</label>
                        <select name="asset_id" class="form-select">
                            <option value="">All assets</option>
                            @foreach($assets as $a)
                            <option value="{{ $a->id }}" @selected((int)$assetId === (int)$a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Apply</button>
                    </div>
                </form>

                <hr>

                <h6 class="mb-2">Add Agreement</h6>

                <form method="POST" action="{{ route('assets.rentals.storeOrUpdate') }}" class="row g-2">
                    @csrf

                    <div class="col-md-5">
                        <label class="form-label">Asset</label>
                        <select name="asset_id" class="form-select @error('asset_id') is-invalid @enderror" required>
                            <option value="">Select asset</option>
                            @foreach($assets as $a)
                            <option value="{{ $a->id }}" @selected((int)old('asset_id', $assetId) === (int)$a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tenant Name</label>
                        <input type="text" name="tenant_name" class="form-control @error('tenant_name') is-invalid @enderror"
                               value="{{ old('tenant_name') }}" placeholder="e.g. John Smith">
                        @error('tenant_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Rent Type</label>
                        <select name="rent_type" class="form-select @error('rent_type') is-invalid @enderror" required>
                            @foreach(['Airbnb','Long-term','Other'] as $t)
                            <option value="{{ $t }}" @selected(old('rent_type','Long-term') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('rent_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Agreement Start Date</label>
                        <input type="date" name="agreement_start_date" class="form-control @error('agreement_start_date') is-invalid @enderror"
                               value="{{ old('agreement_start_date') }}" required>
                        @error('agreement_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Agreement End Date</label>
                        <input type="date" name="agreement_end_date" class="form-control @error('agreement_end_date') is-invalid @enderror"
                               value="{{ old('agreement_end_date') }}">
                        @error('agreement_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Active</label>
                        <select name="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                            <option value="1" @selected((string)old('is_active','1') === '1')>Yes</option>
                            <option value="0" @selected((string)old('is_active','1') === '0')>No</option>
                        </select>
                        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Monthly Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount', 0) }}" min="0" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                            @foreach(['EUR','USD','GBP'] as $c)
                            <option value="{{ $c }}" @selected(old('currency','EUR') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Channel</label>
                        <input name="channel" class="form-control @error('channel') is-invalid @enderror"
                               value="{{ old('channel') }}" placeholder="Optional">
                        @error('channel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Notes</label>
                        <input name="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Agreement</button>
                    </div>
                </form>

                <hr>

                <h6 class="mb-2">Agreements History</h6>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Tenant</th>
                            <th>Type</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Active</th>
                            <th class="text-end">Monthly Amount</th>
                            <th>Channel</th>
                            <th class="text-end" style="width: 140px;">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rentals as $r)
                        <tr>
                            <td class="fw-semibold">{{ $r->asset?->name }}</td>
                            <td>{{ $r->tenant_name ?: '—' }}</td>
                            <td>{{ $r->rent_type ?: '—' }}</td>
                            <td>{{ $r->agreement_start_date ? $r->agreement_start_date->format('Y-m-d') : '—' }}</td>
                            <td>{{ $r->agreement_end_date ? $r->agreement_end_date->format('Y-m-d') : '—' }}</td>
                            <td>
                                @if($r->is_active)
                                <span class="badge text-bg-success">Yes</span>
                                @else
                                <span class="badge text-bg-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float)$r->amount, 2) }} {{ $r->currency }}</td>
                            <td>{{ $r->channel ?: '—' }}</td>

                            <td class="text-end">
                                @can('manage_asset_rentals')
                                <a href="{{ route('assets.rentals.edit', $r) }}" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan

                                @can('manage_asset_rentals')
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteRentalModal"
                                    data-delete-url="{{ route('assets.rentals.destroy', $r) }}"
                                    data-delete-body="Delete agreement for <b>{{ e($r->asset?->name ?? 'Asset') }}</b>?">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No agreements yet.</td>
                        </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $rentals->links() }}
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteRentalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Agreement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteRentalBody">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteRentalForm">
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
        const modal = document.getElementById('deleteRentalModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            const url = btn.getAttribute('data-delete-url');
            const body = btn.getAttribute('data-delete-body') || 'Are you sure?';

            document.getElementById('deleteRentalBody').innerHTML = body;
            document.getElementById('deleteRentalForm').setAttribute('action', url);
        });
    });
</script>
@endsection
