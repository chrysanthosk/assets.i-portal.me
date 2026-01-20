@extends('layouts.app')

@section('content')
@php
$titleDeed = (bool)($asset->title_deed ?? false);
$financed  = (bool)($asset->financed ?? false);
$parking   = (bool)($asset->parking ?? false);

$purchasePrice = $asset->purchase_price !== null ? number_format((float)$asset->purchase_price, 2) : null;
$currency = $asset->currency ?: 'EUR';

$status = $asset->status ?: '—';
$statusBadge =
strtolower($status) === 'occupied' ? 'text-bg-success' :
(strtolower($status) === 'vacant' ? 'text-bg-secondary' : 'text-bg-info');

// FK-aware labels
$assetTypeName   = optional($asset->assetType)->name ?: ($asset->type ?? '—');
$ownerEntityName = optional($asset->ownerEntity)->name ?: ($asset->owner_entity ?? '—');

// documents collection (controller eager-loads, but safe fallback)
$documents = $asset->relationLoaded('documents') ? $asset->documents : ($asset->documents ?? collect());
@endphp

<div class="row">
    <div class="col-12 mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0">{{ $asset->name }}</h4>
                <div class="text-muted">
                    {{ $assetTypeName }}
                    @if($asset->city) • {{ $asset->city }} @endif
                    @if($asset->country) • {{ $asset->country }} @endif
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>

                @can('manage_assets')
                <a href="{{ route('assets.edit', $asset) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Edit
                </a>

                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAssetModal">
                    <i class="bi bi-trash"></i> Delete
                </button>
                @endcan
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7 mb-3">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Asset Details</h5>
                    <small class="text-muted">General information</small>
                </div>
                <span class="badge {{ $statusBadge }}">{{ $status }}</span>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="text-muted small">Address</div>
                        <div class="fw-semibold">{{ $asset->address ?: '—' }}</div>
                        <div class="text-muted">
                            {{ $asset->postcode ?: '' }}
                            @if($asset->postcode && $asset->city) • @endif
                            {{ $asset->city ?: '' }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Purchase</div>
                        <div class="fw-semibold">
                            @if($purchasePrice)
                            {{ $currency }} {{ $purchasePrice }}
                            @else
                            —
                            @endif
                        </div>
                        <div class="text-muted">
                            @if($asset->purchase_date)
                            {{ \Illuminate\Support\Carbon::parse($asset->purchase_date)->format('Y-m-d') }}
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Asset Type</div>
                        <div class="fw-semibold">{{ $assetTypeName }}</div>
                        <div class="text-muted small">
                            @if($asset->asset_type_id)
                            FK: #{{ $asset->asset_type_id }}
                            @else
                            Legacy string
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Owner Entity</div>
                        <div class="fw-semibold">{{ $ownerEntityName }}</div>
                        <div class="text-muted small">
                            @if($asset->owner_entity_id)
                            FK: #{{ $asset->owner_entity_id }}
                            @else
                            Legacy string
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Ownership %</div>
                        <div class="fw-semibold">
                            {{ $asset->ownership_percentage !== null
                            ? rtrim(rtrim(number_format((float)$asset->ownership_percentage, 2), '0'), '.') . '%'
                            : '—'
                            }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Title Deed</div>
                        <div class="fw-semibold">
                            @if($titleDeed)
                            <span class="badge text-bg-success">Yes</span>
                            @else
                            <span class="badge text-bg-secondary">No</span>
                            @endif
                        </div>
                        <div class="text-muted">
                            @if($asset->title_deed_number) #{{ $asset->title_deed_number }} @endif
                            @if($asset->title_deed_date)
                            @if($asset->title_deed_number) • @endif
                            {{ \Illuminate\Support\Carbon::parse($asset->title_deed_date)->format('Y-m-d') }}
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Financed</div>
                        <div class="fw-semibold">
                            @if($financed)
                            <span class="badge text-bg-warning">Yes</span>
                            @else
                            <span class="badge text-bg-secondary">No</span>
                            @endif
                        </div>
                        <div class="text-muted">
                            @if($asset->lender) {{ $asset->lender }} @endif
                            @if($asset->loan_amount !== null)
                            @if($asset->lender) • @endif
                            {{ $currency }} {{ number_format((float)$asset->loan_amount, 2) }}
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Size</div>
                        <div class="fw-semibold">
                            {{ $asset->size_sqm !== null ? number_format((float)$asset->size_sqm, 2) . ' sqm' : '—' }}
                        </div>
                        <div class="text-muted">
                            Land: {{ $asset->land_sqm !== null ? number_format((float)$asset->land_sqm, 2) . ' sqm' : '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Rooms</div>
                        <div class="fw-semibold">
                            Bedrooms: {{ $asset->bedrooms !== null ? (int)$asset->bedrooms : '—' }}
                        </div>
                        <div class="text-muted">
                            Bathrooms: {{ $asset->bathrooms !== null ? (int)$asset->bathrooms : '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Parking / Built</div>
                        <div class="fw-semibold">
                            Parking:
                            @if($parking)
                            <span class="badge text-bg-success">Yes</span>
                            @else
                            <span class="badge text-bg-secondary">No</span>
                            @endif
                        </div>
                        <div class="text-muted">
                            Year built: {{ $asset->year_built ?: '—' }}
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small mb-1">Tags</div>
                        @if($asset->tags && $asset->tags->count())
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($asset->tags as $t)
                            <span class="badge text-bg-dark">{{ $t->name }}</span>
                            @endforeach
                        </div>
                        @else
                        <div class="text-muted">—</div>
                        @endif
                    </div>

                    <div class="col-12">
                        <div class="text-muted small mb-1">Notes</div>
                        <div class="text-muted">{!! nl2br(e($asset->notes ?: '—')) !!}</div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5 mb-3">

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Rental History</h5>
                    <small class="text-muted">Last 24 entries</small>
                </div>

                @can('manage_asset_rentals')
                <a href="{{ route('assets.rentals.index', ['asset_id' => $asset->id]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg"></i> Add / Edit
                </a>
                @endcan
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-end">Amount</th>
                            <th>Channel</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($asset->rentals as $r)
                        <tr>
                            <td>{{ sprintf('%04d-%02d', (int)$r->year, (int)$r->month) }}</td>
                            <td class="text-end">{{ number_format((float)$r->amount, 2) }} {{ $r->currency }}</td>
                            <td>{{ $r->channel ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">No rental records yet.</td>
                        </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer text-muted small">
                Tip: use the Rental Income page to add/update monthly entries for this asset.
            </div>
        </div>

        {{-- =========================
        ASSET DOCUMENTS
        ========================== --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Documents</h5>
                    <small class="text-muted">Upload deeds, contracts, invoices, etc.</small>
                </div>
            </div>

            <div class="card-body">
                @can('manage_assets')
                <form method="POST"
                      action="{{ route('assets.documents.store', $asset) }}"
                      enctype="multipart/form-data"
                      class="row g-2 mb-3">
                    @csrf

                    <div class="col-12">
                        <label class="form-label mb-1">File</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Allowed: pdf, jpg, png, doc, docx, xls, xlsx. Max size depends on server.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label mb-1">Notes (optional)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control @error('notes') is-invalid @enderror" maxlength="500">
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-outline-primary">
                            <i class="bi bi-upload"></i> Upload
                        </button>
                    </div>
                </form>
                @endcan

                @if($documents && $documents->count())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>File</th>
                            <th class="text-end">Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($documents as $doc)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $doc->original_name }}</div>
                                <div class="text-muted small">
                                    {{ $doc->mime_type ?: ($doc->mime ?: '—') }}
                                    • {{ $doc->created_at?->format('Y-m-d H:i') }}
                                    @if($doc->notes) • {{ $doc->notes }} @endif
                                </div>
                            </td>
                            <td class="text-end text-muted">
                                @php $bytes = $doc->size_bytes ?: $doc->size; @endphp
                                @if($bytes)
                                {{ number_format($bytes / 1024, 1) }} KB
                                @else
                                —
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('assets.documents.download', ['asset' => $asset->id, 'document' => $doc->id]) }}">
                                    <i class="bi bi-download"></i>
                                </a>

                                @can('manage_assets')
                                <form method="POST"
                                      action="{{ route('assets.documents.destroy', ['asset' => $asset->id, 'document' => $doc->id]) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this document?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-muted">No documents uploaded yet.</div>
                @endif
            </div>
        </div>

    </div>
</div>

@can('manage_assets')
<!-- Delete Asset Modal -->
<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <b>{{ e($asset->name) }}</b>?<br>
                <span class="text-muted">This will also remove its tag links and rental history.</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('assets.destroy', $asset) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection
