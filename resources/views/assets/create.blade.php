@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add Asset</h5>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('assets.store') }}" class="row g-3">
                    @csrf

                    {{-- =========================
                    BASIC
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Basic</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input
                            class="form-control @error('name') is-invalid @enderror"
                            name="name"
                            value="{{ old('name') }}"
                            required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select @error('type') is-invalid @enderror" name="type" required>
                            @forelse($assetTypes ?? [] as $t)
                            <option value="{{ $t }}" @selected(old('type') === $t)>{{ $t }}</option>
                            @empty
                            <option value="">No types configured</option>
                            @endforelse
                        </select>
                        <div class="form-text">
                            Configure types in Settings → Asset Types.
                        </div>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select @error('status') is-invalid @enderror" name="status" required>
                            @foreach(['Vacant','Owner-occupied','Rented (long-term)','Airbnb/Short-term'] as $s)
                            <option value="{{ $s }}" @selected(old('status') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" name="address" rows="2">{{ old('address') }}</textarea>
                        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    PURCHASE
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Purchase</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date"
                               class="form-control @error('purchase_date') is-invalid @enderror"
                               name="purchase_date"
                               value="{{ old('purchase_date') }}">
                        @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01"
                               class="form-control @error('purchase_price') is-invalid @enderror"
                               name="purchase_price"
                               value="{{ old('purchase_price') }}">
                        @error('purchase_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Currency</label>
                        <select class="form-select @error('currency') is-invalid @enderror" name="currency" required>
                            @foreach(['EUR','USD','GBP'] as $c)
                            <option value="{{ $c }}" @selected(old('currency', 'EUR') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Owner Entity</label>
                        <select class="form-select @error('owner_entity') is-invalid @enderror" name="owner_entity">
                            <option value="">—</option>
                            @forelse($ownerEntities ?? [] as $oe)
                            <option value="{{ $oe }}" @selected(old('owner_entity') === $oe)>{{ $oe }}</option>
                            @empty
                            <option value="">No owner entities configured</option>
                            @endforelse
                        </select>
                        <div class="form-text">
                            Configure owners in Settings → Owner Entities.
                        </div>
                        @error('owner_entity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Ownership %</label>
                        <input type="number" step="0.01" min="0" max="100"
                               class="form-control @error('ownership_percentage') is-invalid @enderror"
                               name="ownership_percentage"
                               value="{{ old('ownership_percentage', 100) }}">
                        @error('ownership_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    TITLE DEED
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Title Deed</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-3">
                        <input type="hidden" name="title_deed" value="0">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="title_deed" name="title_deed"
                                   @checked((int)old('title_deed', 0) === 1)>
                            <label class="form-check-label" for="title_deed">Title Deed Available</label>
                        </div>
                        @error('title_deed') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Title Deed Number</label>
                        <input class="form-control @error('title_deed_number') is-invalid @enderror"
                               name="title_deed_number"
                               value="{{ old('title_deed_number') }}">
                        @error('title_deed_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Title Deed Date</label>
                        <input type="date"
                               class="form-control @error('title_deed_date') is-invalid @enderror"
                               name="title_deed_date"
                               value="{{ old('title_deed_date') }}">
                        @error('title_deed_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Lawyer / Notary</label>
                        <input class="form-control @error('lawyer_notary') is-invalid @enderror"
                               name="lawyer_notary"
                               value="{{ old('lawyer_notary') }}">
                        @error('lawyer_notary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    FINANCE
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Finance</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-3">
                        <input type="hidden" name="financed" value="0">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="financed" name="financed"
                                   @checked((int)old('financed', 0) === 1)>
                            <label class="form-check-label" for="financed">Financed (Loan)</label>
                        </div>
                        @error('financed') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Lender</label>
                        <input class="form-control @error('lender') is-invalid @enderror"
                               name="lender"
                               value="{{ old('lender') }}">
                        @error('lender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Loan Amount</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('loan_amount') is-invalid @enderror"
                               name="loan_amount"
                               value="{{ old('loan_amount') }}">
                        @error('loan_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('interest_rate') is-invalid @enderror"
                               name="interest_rate"
                               value="{{ old('interest_rate') }}">
                        @error('interest_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Loan Start Date</label>
                        <input type="date"
                               class="form-control @error('loan_start_date') is-invalid @enderror"
                               name="loan_start_date"
                               value="{{ old('loan_start_date') }}">
                        @error('loan_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Loan End Date</label>
                        <input type="date"
                               class="form-control @error('loan_end_date') is-invalid @enderror"
                               name="loan_end_date"
                               value="{{ old('loan_end_date') }}">
                        @error('loan_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Monthly Payment</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('monthly_payment') is-invalid @enderror"
                               name="monthly_payment"
                               value="{{ old('monthly_payment') }}">
                        @error('monthly_payment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    SIZE & ROOMS
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Size & Rooms</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Size (sqm)</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('size_sqm') is-invalid @enderror"
                               name="size_sqm"
                               value="{{ old('size_sqm') }}">
                        @error('size_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Land (sqm)</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('land_sqm') is-invalid @enderror"
                               name="land_sqm"
                               value="{{ old('land_sqm') }}">
                        @error('land_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Bedrooms</label>
                        <input type="number" min="0" max="50"
                               class="form-control @error('bedrooms') is-invalid @enderror"
                               name="bedrooms"
                               value="{{ old('bedrooms') }}">
                        @error('bedrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Bathrooms</label>
                        <input type="number" min="0" max="50"
                               class="form-control @error('bathrooms') is-invalid @enderror"
                               name="bathrooms"
                               value="{{ old('bathrooms') }}">
                        @error('bathrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <input type="hidden" name="parking" value="0">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="parking" name="parking"
                                   @checked((int)old('parking', 0) === 1)>
                            <label class="form-check-label" for="parking">Parking Available</label>
                        </div>
                        @error('parking') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Year Built</label>
                        <input type="number" min="1800" max="2100"
                               class="form-control @error('year_built') is-invalid @enderror"
                               name="year_built"
                               value="{{ old('year_built') }}">
                        @error('year_built') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    EXPENSES
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Expenses</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Estimated Annual Expenses</label>
                        <input type="number" step="0.01" min="0"
                               class="form-control @error('estimated_annual_expenses') is-invalid @enderror"
                               name="estimated_annual_expenses"
                               value="{{ old('estimated_annual_expenses') }}">
                        @error('estimated_annual_expenses') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    TAGS
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Tags</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($tags as $tag)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tags[]" value="{{ $tag->id }}" id="tag_{{ $tag->id }}"
                                       @checked(collect(old('tags', []))->contains($tag->id))>
                                <label class="form-check-label" for="tag_{{ $tag->id }}">{{ $tag->name }}</label>
                            </div>
                            @empty
                            <span class="text-muted">No tags yet. Create some in Assets → Tags.</span>
                            @endforelse
                        </div>
                        @error('tags') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- =========================
                    NOTES
                    ========================= --}}
                    <div class="col-12">
                        <h6 class="mb-0">Notes</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-12">
                        <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary">Create Asset</button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
@endsection
