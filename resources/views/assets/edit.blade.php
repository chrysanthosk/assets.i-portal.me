@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-12">

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Edit Asset</h5>
      </div>

      <div class="card-body">
        <form method="POST" action="{{ route('assets.update', $asset) }}" class="row g-3">
          @csrf
          @method('PUT')

          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input
              class="form-control @error('name') is-invalid @enderror"
              name="name"
              value="{{ old('name', $asset->name) }}"
              required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select class="form-select @error('type') is-invalid @enderror" name="type" required>
              @foreach(['Apartment','House','Land','Commercial','Other'] as $t)
                <option value="{{ $t }}" @selected(old('type', $asset->type) === $t)>{{ $t }}</option>
              @endforeach
            </select>
            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select @error('status') is-invalid @enderror" name="status" required>
              @foreach(['Vacant','Owner-occupied','Rented (long-term)','Airbnb/Short-term'] as $s)
                <option value="{{ $s }}" @selected(old('status', $asset->status) === $s)>{{ $s }}</option>
              @endforeach
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea class="form-control @error('address') is-invalid @enderror" name="address" rows="2">{{ old('address', $asset->address) }}</textarea>
            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Purchase Date</label>
            <input type="date" class="form-control @error('purchase_date') is-invalid @enderror" name="purchase_date" value="{{ old('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}">
            @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Purchase Price</label>
            <input type="number" step="0.01" class="form-control @error('purchase_price') is-invalid @enderror" name="purchase_price" value="{{ old('purchase_price', $asset->purchase_price) }}">
            @error('purchase_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label">Currency</label>
            <select class="form-select @error('currency') is-invalid @enderror" name="currency" required>
              @foreach(['EUR','USD','GBP'] as $c)
                <option value="{{ $c }}" @selected(old('currency', $asset->currency) === $c)>{{ $c }}</option>
              @endforeach
            </select>
            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Owner Entity</label>
            <input class="form-control @error('owner_entity') is-invalid @enderror" name="owner_entity" value="{{ old('owner_entity', $asset->owner_entity) }}">
            @error('owner_entity') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" value="1" id="title_deed" name="title_deed" @checked(old('title_deed', $asset->title_deed))>
              <label class="form-check-label" for="title_deed">Title Deed Available</label>
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Title Deed Number</label>
            <input class="form-control @error('title_deed_number') is-invalid @enderror" name="title_deed_number" value="{{ old('title_deed_number', $asset->title_deed_number) }}">
            @error('title_deed_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label">Title Deed Date</label>
            <input type="date" class="form-control @error('title_deed_date') is-invalid @enderror" name="title_deed_date" value="{{ old('title_deed_date', optional($asset->title_deed_date)->format('Y-m-d')) }}">
            @error('title_deed_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12">
            <label class="form-label">Tags</label>
            <div class="d-flex flex-wrap gap-2">
              @forelse($tags as $tag)
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="tags[]" value="{{ $tag->id }}" id="tag_{{ $tag->id }}"
                    @checked(collect(old('tags', $asset->tags->pluck('id')->all()))->contains($tag->id))>
                  <label class="form-check-label" for="tag_{{ $tag->id }}">{{ $tag->name }}</label>
                </div>
              @empty
                <span class="text-muted">No tags yet. Create some in Assets → Tags.</span>
              @endforelse
            </div>
            @error('tags') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3">{{ old('notes', $asset->notes) }}</textarea>
            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">Save Changes</button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>
@endsection
