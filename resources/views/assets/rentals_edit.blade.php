@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12 col-lg-8">

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Edit Rental Agreement</h5>
                    <small class="text-muted">Monthly amount applies to every month overlapped by this agreement.</small>
                </div>
                <a href="{{ route('assets.rentals.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('assets.rentals.update', $rental) }}" class="row g-2">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="form-label">Asset</label>
                        <select name="asset_id" class="form-select @error('asset_id') is-invalid @enderror" required>
                            @foreach($assets as $a)
                            <option value="{{ $a->id }}" @selected((int)old('asset_id', $rental->asset_id) === (int)$a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tenant Name</label>
                        <input type="text" name="tenant_name" class="form-control @error('tenant_name') is-invalid @enderror"
                               value="{{ old('tenant_name', $rental->tenant_name) }}">
                        @error('tenant_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Agreement Start Date</label>
                        <input type="date" name="agreement_start_date" class="form-control @error('agreement_start_date') is-invalid @enderror"
                               value="{{ old('agreement_start_date', optional($rental->agreement_start_date)->format('Y-m-d')) }}" required>
                        @error('agreement_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Agreement End Date</label>
                        <input type="date" name="agreement_end_date" class="form-control @error('agreement_end_date') is-invalid @enderror"
                               value="{{ old('agreement_end_date', optional($rental->agreement_end_date)->format('Y-m-d')) }}">
                        @error('agreement_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Active</label>
                        <select name="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                            <option value="1" @selected((string)old('is_active', $rental->is_active ? '1' : '0') === '1')>Yes</option>
                            <option value="0" @selected((string)old('is_active', $rental->is_active ? '1' : '0') === '0')>No</option>
                        </select>
                        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Rent Type</label>
                        <select name="rent_type" class="form-select @error('rent_type') is-invalid @enderror" required>
                            @foreach(['Airbnb','Long-term','Other'] as $t)
                            <option value="{{ $t }}" @selected(old('rent_type', $rental->rent_type ?: 'Long-term') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('rent_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Channel</label>
                        <input name="channel" class="form-control @error('channel') is-invalid @enderror"
                               value="{{ old('channel', $rental->channel) }}">
                        @error('channel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Monthly Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount', $rental->amount) }}" min="0" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                            @foreach(['EUR','USD','GBP'] as $c)
                            <option value="{{ $c }}" @selected(old('currency', $rental->currency) === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $rental->notes) }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <a href="{{ route('assets.rentals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
@endsection
