@php
  $types = ['Apartment','House','Land/Plot','Commercial','Other'];
  $statuses = ['Owner-occupied','Rented','Airbnb','Vacant'];
@endphp

<div class="row g-3">

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Core</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Asset Name *</label>
            <input name="name" value="{{ old('name', $asset->name) }}"
              class="form-control @error('name') is-invalid @enderror" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Type *</label>
            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
              @foreach($types as $t)
                <option value="{{ $t }}" @selected(old('type', $asset->type) === $t)>{{ $t }}</option>
              @endforeach
            </select>
            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Address</label>
            <input name="address" value="{{ old('address', $asset->address) }}"
              class="form-control @error('address') is-invalid @enderror">
            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">City</label>
            <input name="city" value="{{ old('city', $asset->city) }}"
              class="form-control @error('city') is-invalid @enderror">
            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Postcode</label>
            <input name="postcode" value="{{ old('postcode', $asset->postcode) }}"
              class="form-control @error('postcode') is-invalid @enderror">
            @error('postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Country</label>
            <input name="country" value="{{ old('country', $asset->country) }}"
              class="form-control @error('country') is-invalid @enderror">
            @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $asset->notes) }}</textarea>
            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Purchase & Ownership</h5></div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-3">
            <label class="form-label">Purchase Date</label>
            <input type="date" name="purchase_date" value="{{ old('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}"
              class="form-control @error('purchase_date') is-invalid @enderror">
            @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Purchase Price</label>
            <input type="number" step="0.01" name="purchase_price" value="{{ old('purchase_price', $asset->purchase_price) }}"
              class="form-control @error('purchase_price') is-invalid @enderror">
            @error('purchase_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Currency *</label>
            <input name="currency" value="{{ old('currency', $asset->currency ?: 'EUR') }}"
              class="form-control @error('currency') is-invalid @enderror" maxlength="3" required>
            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Owner / Entity</label>
            <input name="ownership_entity" value="{{ old('ownership_entity', $asset->ownership_entity) }}"
              class="form-control @error('ownership_entity') is-invalid @enderror">
            @error('ownership_entity') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Ownership %</label>
            <input type="number" step="0.01" name="ownership_percentage" value="{{ old('ownership_percentage', $asset->ownership_percentage) }}"
              class="form-control @error('ownership_percentage') is-invalid @enderror" min="0" max="100">
            @error('ownership_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Title Deed & Legal</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="title_deed" value="1"
                id="title_deed" @checked(old('title_deed', $asset->title_deed))>
              <label class="form-check-label" for="title_deed">Title Deed</label>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Title Deed Number</label>
            <input name="title_deed_number" value="{{ old('title_deed_number', $asset->title_deed_number) }}"
              class="form-control @error('title_deed_number') is-invalid @enderror">
            @error('title_deed_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Title Deed Date</label>
            <input type="date" name="title_deed_date" value="{{ old('title_deed_date', optional($asset->title_deed_date)->format('Y-m-d')) }}"
              class="form-control @error('title_deed_date') is-invalid @enderror">
            @error('title_deed_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Lawyer / Notary</label>
            <input name="lawyer_name" value="{{ old('lawyer_name', $asset->lawyer_name) }}"
              class="form-control @error('lawyer_name') is-invalid @enderror">
            @error('lawyer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Financing</h5></div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="financed" value="1"
                id="financed" @checked(old('financed', $asset->financed))>
              <label class="form-check-label" for="financed">Financed</label>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Bank / Lender</label>
            <input name="lender" value="{{ old('lender', $asset->lender) }}"
              class="form-control @error('lender') is-invalid @enderror">
            @error('lender') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Loan Amount</label>
            <input type="number" step="0.01" name="loan_amount" value="{{ old('loan_amount', $asset->loan_amount) }}"
              class="form-control @error('loan_amount') is-invalid @enderror">
            @error('loan_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Interest Rate %</label>
            <input type="number" step="0.01" name="interest_rate" value="{{ old('interest_rate', $asset->interest_rate) }}"
              class="form-control @error('interest_rate') is-invalid @enderror" min="0" max="100">
            @error('interest_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Loan Start Date</label>
            <input type="date" name="loan_start_date" value="{{ old('loan_start_date', optional($asset->loan_start_date)->format('Y-m-d')) }}"
              class="form-control @error('loan_start_date') is-invalid @enderror">
            @error('loan_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Loan End Date</label>
            <input type="date" name="loan_end_date" value="{{ old('loan_end_date', optional($asset->loan_end_date)->format('Y-m-d')) }}"
              class="form-control @error('loan_end_date') is-invalid @enderror">
            @error('loan_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Monthly Payment</label>
            <input type="number" step="0.01" name="monthly_payment" value="{{ old('monthly_payment', $asset->monthly_payment) }}"
              class="form-control @error('monthly_payment') is-invalid @enderror">
            @error('monthly_payment') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Property Details</h5></div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-3">
            <label class="form-label">Size (m²)</label>
            <input type="number" step="0.01" name="size_sqm" value="{{ old('size_sqm', $asset->size_sqm) }}"
              class="form-control @error('size_sqm') is-invalid @enderror">
            @error('size_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Land size (m²)</label>
            <input type="number" step="0.01" name="land_size_sqm" value="{{ old('land_size_sqm', $asset->land_size_sqm) }}"
              class="form-control @error('land_size_sqm') is-invalid @enderror">
            @error('land_size_sqm') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Bedrooms</label>
            <input type="number" name="bedrooms" value="{{ old('bedrooms', $asset->bedrooms) }}"
              class="form-control @error('bedrooms') is-invalid @enderror">
            @error('bedrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Bathrooms</label>
            <input type="number" name="bathrooms" value="{{ old('bathrooms', $asset->bathrooms) }}"
              class="form-control @error('bathrooms') is-invalid @enderror">
            @error('bathrooms') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="parking" value="1"
                id="parking" @checked(old('parking', $asset->parking))>
              <label class="form-check-label" for="parking">Parking</label>
            </div>
          </div>

          <div class="col-md-2">
            <label class="form-label">Year Built</label>
            <input type="number" name="year_built" value="{{ old('year_built', $asset->year_built) }}"
              class="form-control @error('year_built') is-invalid @enderror">
            @error('year_built') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Rental / Income</h5></div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-3">
            <label class="form-label">Status *</label>
            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
              @foreach($statuses as $s)
                <option value="{{ $s }}" @selected(old('status', $asset->status) === $s)>{{ $s }}</option>
              @endforeach
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Monthly Rent</label>
            <input type="number" step="0.01" name="monthly_rent" value="{{ old('monthly_rent', $asset->monthly_rent) }}"
              class="form-control @error('monthly_rent') is-invalid @enderror">
            @error('monthly_rent') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Tenant Name</label>
            <input name="tenant_name" value="{{ old('tenant_name', $asset->tenant_name) }}"
              class="form-control @error('tenant_name') is-invalid @enderror">
            @error('tenant_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Annual Expenses (estimate)</label>
            <input type="number" step="0.01" name="annual_expenses_estimate" value="{{ old('annual_expenses_estimate', $asset->annual_expenses_estimate) }}"
              class="form-control @error('annual_expenses_estimate') is-invalid @enderror">
            @error('annual_expenses_estimate') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Lease Start</label>
            <input type="date" name="lease_start_date" value="{{ old('lease_start_date', optional($asset->lease_start_date)->format('Y-m-d')) }}"
              class="form-control @error('lease_start_date') is-invalid @enderror">
            @error('lease_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Lease End</label>
            <input type="date" name="lease_end_date" value="{{ old('lease_end_date', optional($asset->lease_end_date)->format('Y-m-d')) }}"
              class="form-control @error('lease_end_date') is-invalid @enderror">
            @error('lease_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Documents</h5></div>
      <div class="card-body">
        <label class="form-label">Upload files (PDF, images, etc.)</label>
        <input type="file" name="documents[]" multiple class="form-control @error('documents.*') is-invalid @enderror">
        @error('documents.*') <div class="invalid-feedback">{{ $message }}</div> @enderror

        <div class="text-muted small mt-2">
          Max 10MB per file.
        </div>
      </div>
    </div>
  </div>

</div>
