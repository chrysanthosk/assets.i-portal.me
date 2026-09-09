@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Assets</h5>
      <small class="text-muted">Manage your purchased assets</small>
    </div>

    <div class="d-flex flex-wrap gap-2 ms-auto align-items-center hdr-tools">
      <form method="GET" action="{{ route('assets.index') }}" class="d-flex gap-2 align-items-center hdr-search">
        <input
          type="text"
          name="q"
          value="{{ request('q') }}"
          class="form-control form-control-sm"
          placeholder="Search..."
          aria-label="Search assets"
        >
        <button class="btn btn-sm btn-outline-secondary" title="Search" aria-label="Search">
          <i class="bi bi-search"></i>
        </button>
      </form>

      @can('manage_assets')
        <div class="d-flex gap-2 hdr-actions">
          <a href="{{ route('assets.import.create') }}" class="btn btn-sm btn-primary text-nowrap">
            <i class="bi bi-file-earmark-arrow-up"></i> Import title deed
          </a>
          <a href="{{ route('assets.create') }}" class="btn btn-sm btn-outline-primary text-nowrap">
            <i class="bi bi-plus-lg"></i> Add manually
          </a>
        </div>
      @endcan
    </div>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Type</th>
            <th scope="col">City</th>
            <th scope="col" class="text-end">Purchase Price</th>
            <th scope="col">Title Deed</th>
            <th scope="col" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($assets as $asset)
            <tr>
              <td class="fw-semibold">{{ $asset->name }}</td>
              <td>{{ $asset->assetType?->name ?? '—' }}</td>
              <td>{{ $asset->city ?: '—' }}</td>
              <td class="text-end">
                {{ $asset->currency ?? 'EUR' }} {{ number_format((float)$asset->purchase_price, 2) }}
              </td>
              <td>
                @if($asset->title_deed)
                  <span class="badge text-bg-success">Yes</span>
                @else
                  <span class="badge text-bg-secondary">No</span>
                @endif
              </td>
              <td class="text-end">
                <a href="{{ route('assets.show', $asset) }}" class="btn btn-sm btn-outline-secondary">View</a>
                @can('manage_assets')
                  <a href="{{ route('assets.edit', $asset) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                @endcan
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
                @if(request('q'))
                  No assets match “{{ request('q') }}”.
                  <a href="{{ route('assets.index') }}">Clear search</a>.
                @else
                  <div class="mb-2">No assets yet.</div>
                  @can('manage_assets')
                    <a href="{{ route('assets.create') }}" class="btn btn-sm btn-primary">
                      <i class="bi bi-plus-lg"></i> Add your first asset
                    </a>
                  @endcan
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      {{ $assets->links() }}
    </div>
  </div>
</div>
@endsection
