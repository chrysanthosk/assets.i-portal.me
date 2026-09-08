{{--
    Shared property form (create + edit).
    Expects: $asset (nullable), $assetTypes, $ownerEntities, $tags
    Sections: Property · Purchase & title deed · Details (collapsed) · Loan (collapsed unless financed) · Notes
--}}
@php
    $v = fn (string $key, $default = null) => old($key, $asset?->{$key} ?? $default);
    $on = fn (string $key) => (int) old($key, $asset?->{$key} ? 1 : 0) === 1;
    $dateVal = fn (string $key) => old($key, $asset?->{$key} ? \Illuminate\Support\Carbon::parse($asset->{$key})->format('Y-m-d') : null);
    $financed = $on('financed') || $errors->hasAny(['lender', 'loan_amount', 'interest_rate', 'loan_start_date', 'loan_end_date', 'monthly_payment']);
    $detailsOpen = $asset !== null || $errors->hasAny(['size_sqm', 'land_sqm', 'bedrooms', 'bathrooms', 'year_built', 'estimated_annual_expenses']);
    $statuses = \App\Http\Requests\AssetRules::STATUSES;
    $currencies = \App\Support\Fx::currencies();
    $ownershipPct = $v('ownership_percentage', 100);
@endphp

{{-- ========== Property ========== --}}
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-house-door me-1"></i> Property</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="f_name">Name *</label>
            <input id="f_name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ $v('name') }}" required autofocus>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_type">Type *</label>
            <select id="f_type" class="form-select @error('asset_type_id') is-invalid @enderror" name="asset_type_id" required>
                <option value="">— Select —</option>
                @foreach($assetTypes ?? [] as $t)
                    <option value="{{ $t->id }}" @selected((string) $v('asset_type_id') === (string) $t->id)>{{ $t->name }}@if(! $t->is_active) (inactive)@endif</option>
                @endforeach
            </select>
            @error('asset_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_status">Status *</label>
            <select id="f_status" class="form-select @error('status') is-invalid @enderror" name="status" required>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected($v('status', 'Vacant') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-5">
            <label class="form-label" for="f_address">Address</label>
            <input id="f_address" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ $v('address') }}">
            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_city">City</label>
            <input id="f_city" class="form-control @error('city') is-invalid @enderror" name="city" value="{{ $v('city') }}">
            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="f_postcode">Postcode</label>
            <input id="f_postcode" class="form-control @error('postcode') is-invalid @enderror" name="postcode" value="{{ $v('postcode') }}">
            @error('postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="f_country">Country</label>
            <input id="f_country" class="form-control @error('country') is-invalid @enderror" name="country" value="{{ $v('country', $asset ? null : 'Cyprus') }}">
            @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-2">
            <label class="form-label" for="f_currency">Currency *</label>
            <select id="f_currency" class="form-select @error('currency') is-invalid @enderror" name="currency" required>
                @foreach($currencies as $c)
                    <option value="{{ $c }}" @selected($v('currency', 'EUR') === $c)>{{ $c }}</option>
                @endforeach
            </select>
            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="f_ownership">My share %</label>
            <input id="f_ownership" type="number" step="0.01" min="0" max="100" class="form-control @error('ownership_percentage') is-invalid @enderror"
                   name="ownership_percentage" value="{{ $ownershipPct }}">
            @error('ownership_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @advanced
        <div class="col-md-4">
            <label class="form-label" for="f_owner">Owner entity</label>
            <select id="f_owner" class="form-select @error('owner_entity_id') is-invalid @enderror" name="owner_entity_id">
                <option value="">—</option>
                @foreach($ownerEntities ?? [] as $oe)
                    <option value="{{ $oe->id }}" @selected((string) $v('owner_entity_id') === (string) $oe->id)>{{ $oe->name }}@if(! $oe->is_active) (inactive)@endif</option>
                @endforeach
            </select>
            @error('owner_entity_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @endadvanced
    </div>
</div>

{{-- ========== Purchase & title deed ========== --}}
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text me-1"></i> Purchase &amp; title deed</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label" for="f_purchase_date">Purchase date</label>
            <input id="f_purchase_date" type="date" class="form-control @error('purchase_date') is-invalid @enderror" name="purchase_date" value="{{ $dateVal('purchase_date') }}">
            @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_purchase_price">Purchase price</label>
            <input id="f_purchase_price" type="number" step="0.01" min="0" class="form-control @error('purchase_price') is-invalid @enderror" name="purchase_price" value="{{ $v('purchase_price') }}">
            @error('purchase_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="f_lawyer">Lawyer / notary</label>
            <input id="f_lawyer" class="form-control @error('lawyer_notary') is-invalid @enderror" name="lawyer_notary" value="{{ $v('lawyer_notary') }}">
            @error('lawyer_notary') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <input type="hidden" name="title_deed" value="0">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" value="1" id="f_title_deed" name="title_deed" @checked($on('title_deed'))>
                <label class="form-check-label" for="f_title_deed">Title deed in hand</label>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_deed_no">Registration no.</label>
            <input id="f_deed_no" class="form-control @error('title_deed_number') is-invalid @enderror" name="title_deed_number" value="{{ $v('title_deed_number') }}" placeholder="e.g. 0/8443">
            @error('title_deed_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="f_deed_date">Registration date</label>
            <input id="f_deed_date" type="date" class="form-control @error('title_deed_date') is-invalid @enderror" name="title_deed_date" value="{{ $dateVal('title_deed_date') }}">
            @error('title_deed_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @if($asset === null)
        <div class="col-md-3 d-flex align-items-end">
            <a href="{{ route('assets.import.create') }}" class="btn btn-outline-primary btn-sm mb-2">
                <i class="bi bi-magic me-1"></i> Fill from deed scan
            </a>
        </div>
        @endif
    </div>
</div>

{{-- ========== Details (collapsible) ========== --}}
<div class="card mb-3">
    <div class="card-header p-0">
        <button class="btn w-100 text-start d-flex align-items-center justify-content-between px-3 py-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#sec_details" aria-expanded="{{ $detailsOpen ? 'true' : 'false' }}" aria-controls="sec_details">
            <h6 class="mb-0"><i class="bi bi-rulers me-1"></i> Details <span class="text-muted fw-normal small">— size, rooms, year</span></h6>
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div id="sec_details" class="collapse {{ $detailsOpen ? 'show' : '' }}">
        <div class="card-body row g-3">
            <div class="col-md-2">
                <label class="form-label" for="f_size">Size (m²)</label>
                <input id="f_size" type="number" step="0.01" min="0" class="form-control @error('size_sqm') is-invalid @enderror" name="size_sqm" value="{{ $v('size_sqm') }}">
                @error('size_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_land">Land (m²)</label>
                <input id="f_land" type="number" step="0.01" min="0" class="form-control @error('land_sqm') is-invalid @enderror" name="land_sqm" value="{{ $v('land_sqm') }}">
                @error('land_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_bed">Bedrooms</label>
                <input id="f_bed" type="number" min="0" max="50" class="form-control @error('bedrooms') is-invalid @enderror" name="bedrooms" value="{{ $v('bedrooms') }}">
                @error('bedrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_bath">Bathrooms</label>
                <input id="f_bath" type="number" min="0" max="50" class="form-control @error('bathrooms') is-invalid @enderror" name="bathrooms" value="{{ $v('bathrooms') }}">
                @error('bathrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_year">Year built</label>
                <input id="f_year" type="number" min="1800" max="2100" class="form-control @error('year_built') is-invalid @enderror" name="year_built" value="{{ $v('year_built') }}">
                @error('year_built') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <input type="hidden" name="parking" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" value="1" id="f_parking" name="parking" @checked($on('parking'))>
                    <label class="form-check-label" for="f_parking">Parking</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="f_exp">Estimated annual expenses</label>
                <input id="f_exp" type="number" step="0.01" min="0" class="form-control @error('estimated_annual_expenses') is-invalid @enderror" name="estimated_annual_expenses" value="{{ $v('estimated_annual_expenses') }}">
                <div class="form-text">Rough yearly running cost, used for planning only.</div>
                @error('estimated_annual_expenses') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- ========== Loan ========== --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h6 class="mb-0"><i class="bi bi-bank me-1"></i> Loan</h6>
        <div class="form-check form-switch mb-0">
            <input type="hidden" name="financed" value="0">
            <input class="form-check-input" type="checkbox" role="switch" value="1" id="f_financed" name="financed" @checked($financed)
                   data-bs-toggle="collapse" data-bs-target="#sec_loan" aria-expanded="{{ $financed ? 'true' : 'false' }}" aria-controls="sec_loan">
            <label class="form-check-label" for="f_financed">Financed with a loan</label>
        </div>
    </div>
    <div id="sec_loan" class="collapse {{ $financed ? 'show' : '' }}">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="f_lender">Lender</label>
                <input id="f_lender" class="form-control @error('lender') is-invalid @enderror" name="lender" value="{{ $v('lender') }}">
                @error('lender') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_loan_amount">Loan amount</label>
                <input id="f_loan_amount" type="number" step="0.01" min="0" class="form-control @error('loan_amount') is-invalid @enderror" name="loan_amount" value="{{ $v('loan_amount') }}">
                @error('loan_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_rate">Interest %</label>
                <input id="f_rate" type="number" step="0.01" min="0" class="form-control @error('interest_rate') is-invalid @enderror" name="interest_rate" value="{{ $v('interest_rate') }}">
                @error('interest_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_loan_start">Start</label>
                <input id="f_loan_start" type="date" class="form-control @error('loan_start_date') is-invalid @enderror" name="loan_start_date" value="{{ $dateVal('loan_start_date') }}">
                @error('loan_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f_loan_end">End</label>
                <input id="f_loan_end" type="date" class="form-control @error('loan_end_date') is-invalid @enderror" name="loan_end_date" value="{{ $dateVal('loan_end_date') }}">
                @error('loan_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f_monthly">Monthly payment</label>
                <input id="f_monthly" type="number" step="0.01" min="0" class="form-control @error('monthly_payment') is-invalid @enderror" name="monthly_payment" value="{{ $v('monthly_payment') }}">
                @error('monthly_payment') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

@advanced
{{-- ========== Tags (advanced) ========== --}}
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-tags me-1"></i> Tags</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3">
            @forelse($tags ?? [] as $tag)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tags[]" value="{{ $tag->id }}" id="tag_{{ $tag->id }}"
                           @checked(collect(old('tags', $asset?->tags?->pluck('id')->all() ?? []))->contains($tag->id))>
                    <label class="form-check-label" for="tag_{{ $tag->id }}">{{ $tag->name }}</label>
                </div>
            @empty
                <span class="text-muted">No tags yet. Create some under Settings → Tags.</span>
            @endforelse
        </div>
        @error('tags') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>
@endadvanced

{{-- ========== Notes ========== --}}
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-journal-text me-1"></i> Notes</h6></div>
    <div class="card-body">
        <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3" aria-label="Notes">{{ $v('notes') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
