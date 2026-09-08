@extends('layouts.app')
@section('title', $asset->name)

@section('content')
@php
    $cur = $asset->currency ?: 'EUR';
    $fmt = fn ($n, $c = null) => ($c ?? $cur).' '.number_format((float) $n, 2);
    $status = $asset->status ?: '—';
    $statusTone = str_contains(strtolower($status), 'rent') || str_contains(strtolower($status), 'airbnb') ? 'success'
        : (str_contains(strtolower($status), 'vacant') ? 'secondary' : 'info');
    $documents = $asset->documents ?? collect();
    $tab = request('tab', 'overview');
    $tenantName = $currentRental?->tenant?->name ?? $currentRental?->tenant_name;
    $deed = $asset->title_deed_data;
    $expiringDocs = $documents->filter(fn ($d) => $d->expires_at && $d->expires_at->lte(now()->addDays(30)))->count();
@endphp

{{-- ===== Header ===== --}}
<div class="page-head">
    <div class="min-w-0">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="text-truncate">{{ $asset->name }}</h1>
            <span class="badge text-bg-{{ $statusTone }}">{{ $status }}</span>
        </div>
        <div class="sub">{{ implode(' · ', array_filter([$asset->assetType?->name, $asset->address, $asset->city])) }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline"> Back</span></a>
        @can('manage_assets')
            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAssetModal" aria-label="Delete"><i class="bi bi-trash"></i></button>
        @endcan
    </div>
</div>

{{-- ===== Key figures ===== --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <x-stat icon="bi-cash-coin" label="Monthly rent"
                :value="$currentRental ? $fmt($currentRental->amount, $currentRental->currency) : '—'"
                :sub="$currentRental ? e($tenantName ?? 'No tenant').($currentRental->agreement_end_date ? ' · until '.$currentRental->agreement_end_date->format('d M Y') : ' · open-ended') : 'No active agreement'"
                :tone="$currentRental ? 'success' : ''" />
    </div>
    <div class="col-6 col-xl-3">
        <x-stat icon="bi-wallet2" label="Outstanding" :value="$ytd['outstanding'] > 0 ? $fmt($ytd['outstanding']) : '—'"
                :sub="$ytd['outstanding'] > 0 ? 'Unpaid or unconfirmed rent' : 'All rent received'"
                :tone="$ytd['outstanding'] > 0 ? 'danger' : ''" />
    </div>
    <div class="col-6 col-xl-3">
        <x-stat icon="bi-graph-up-arrow" label="This year" :value="$fmt($ytd['income'] - $ytd['expenses'])"
                :sub="'in '.$fmt($ytd['income']).' · out '.$fmt($ytd['expenses'])" tone="info" />
    </div>
    <div class="col-6 col-xl-3">
        <x-stat icon="bi-tag" label="Purchase" :value="$asset->purchase_price !== null ? $fmt($asset->purchase_price) : '—'"
                :sub="$asset->purchase_date ? \Illuminate\Support\Carbon::parse($asset->purchase_date)->format('d M Y') : 'No purchase price recorded'" />
    </div>
</div>

{{-- ===== Tabs ===== --}}
<div class="card">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs px-2 pt-2" role="tablist">
            <li class="nav-item"><button class="nav-link {{ $tab === 'overview' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">Overview</button></li>
            <li class="nav-item"><button class="nav-link {{ $tab === 'documents' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button" role="tab">
                Documents <span class="badge {{ $expiringDocs ? 'text-bg-warning' : 'text-bg-light' }} ms-1">{{ $documents->count() }}</span></button></li>
            @can('manage_asset_rentals')
                <li class="nav-item"><button class="nav-link {{ $tab === 'agreements' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-agreements" type="button" role="tab">Agreements</button></li>
            @endcan
            @can('manage_rental_payments')
                <li class="nav-item"><button class="nav-link {{ $tab === 'payments' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button" role="tab">Payments</button></li>
            @endcan
            @can('manage_asset_expenses')
                <li class="nav-item"><button class="nav-link {{ $tab === 'expenses' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-expenses" type="button" role="tab">Expenses</button></li>
            @endcan
        </ul>
    </div>

    <div class="tab-content">

        {{-- ---------- Overview ---------- --}}
        <div class="tab-pane fade {{ $tab === 'overview' ? 'show active' : '' }}" id="tab-overview" role="tabpanel">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h6 class="text-muted text-uppercase small mb-3">Details</h6>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted fw-normal">Address</dt>
                            <dd class="col-sm-8">{{ $asset->address ?: '—' }}@if($asset->postcode), {{ $asset->postcode }}@endif @if($asset->city)<br>{{ $asset->city }}@endif @if($asset->country), {{ $asset->country }}@endif</dd>

                            <dt class="col-sm-4 text-muted fw-normal">Size</dt>
                            <dd class="col-sm-8">
                                {{ $asset->size_sqm ? number_format((float) $asset->size_sqm, 0).' m²' : '—' }}
                                @if($asset->land_sqm) · land {{ number_format((float) $asset->land_sqm, 0) }} m² @endif
                                @if($asset->bedrooms !== null || $asset->bathrooms !== null) · {{ (int) $asset->bedrooms }} bed / {{ (int) $asset->bathrooms }} bath @endif
                                @if($asset->parking) · parking @endif
                                @if($asset->year_built) · built {{ $asset->year_built }} @endif
                            </dd>

                            <dt class="col-sm-4 text-muted fw-normal">Ownership</dt>
                            <dd class="col-sm-8">{{ number_format((float) ($asset->ownership_percentage ?? 100), 2) }} %
                                @advanced @if($asset->ownerEntity) · {{ $asset->ownerEntity->name }} @endif @endadvanced
                            </dd>

                            <dt class="col-sm-4 text-muted fw-normal">Title deed</dt>
                            <dd class="col-sm-8">
                                @if($asset->title_deed)
                                    <span class="badge text-bg-success">In hand</span>
                                    @if($asset->title_deed_number) <span class="mono ms-1">{{ $asset->title_deed_number }}</span>@endif
                                    @if($asset->title_deed_date) · {{ \Illuminate\Support\Carbon::parse($asset->title_deed_date)->format('d M Y') }}@endif
                                @else
                                    <span class="badge text-bg-secondary">Not yet</span>
                                @endif
                                @if($asset->lawyer_notary)<div class="text-muted">Lawyer: {{ $asset->lawyer_notary }}</div>@endif
                            </dd>

                            <dt class="col-sm-4 text-muted fw-normal">Loan</dt>
                            <dd class="col-sm-8">
                                @if($asset->financed)
                                    {{ $asset->lender ?: 'Financed' }}
                                    @if($asset->loan_amount) · {{ $fmt($asset->loan_amount) }} @endif
                                    @if($asset->interest_rate !== null) · {{ $asset->interest_rate }} % @endif
                                    @if($asset->monthly_payment) · {{ $fmt($asset->monthly_payment) }}/month @endif
                                    @if($asset->loan_end_date) · until {{ \Illuminate\Support\Carbon::parse($asset->loan_end_date)->format('M Y') }} @endif
                                @else
                                    No loan
                                @endif
                            </dd>

                            @if($asset->estimated_annual_expenses)
                                <dt class="col-sm-4 text-muted fw-normal">Est. yearly costs</dt>
                                <dd class="col-sm-8">{{ $fmt($asset->estimated_annual_expenses) }}</dd>
                            @endif

                            @advanced
                            @if($asset->tags && $asset->tags->count())
                                <dt class="col-sm-4 text-muted fw-normal">Tags</dt>
                                <dd class="col-sm-8">@foreach($asset->tags as $t)<span class="badge text-bg-dark me-1">{{ $t->name }}</span>@endforeach</dd>
                            @endif
                            @endadvanced

                            @if($asset->notes)
                                <dt class="col-sm-4 text-muted fw-normal">Notes</dt>
                                <dd class="col-sm-8" style="white-space: pre-line">{{ $asset->notes }}</dd>
                            @endif
                        </dl>
                    </div>

                    <div class="col-lg-5">
                        @if(is_array($deed))
                            @php
                                $plotRef = implode(' / ', array_filter([$deed['sheet'] ?? null, $deed['plan'] ?? null, $deed['section'] ?? null, $deed['plot'] ?? null], fn ($x) => $x !== null && $x !== ''));
                            @endphp
                            <h6 class="text-muted text-uppercase small mb-3"><i class="bi bi-file-earmark-text me-1"></i> From the title deed</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted fw-normal">Registration</dt><dd class="col-7"><span class="mono">{{ $deed['registration_number'] ?? '—' }}</span> @if(! empty($deed['registration_date'])) · {{ $deed['registration_date'] }}@endif</dd>
                                <dt class="col-5 text-muted fw-normal">District / municipality</dt><dd class="col-7">{{ $deed['district'] ?? '—' }} / {{ $deed['municipality_community'] ?? '—' }}</dd>
                                <dt class="col-5 text-muted fw-normal">Sheet / plan / section / plot</dt><dd class="col-7"><span class="mono">{{ $plotRef ?: '—' }}</span></dd>
                                @if(! empty($deed['building_name']))<dt class="col-5 text-muted fw-normal">Building</dt><dd class="col-7">{{ $deed['building_name'] }}@if(! empty($deed['unit_number'])) · No. {{ $deed['unit_number'] }}@endif</dd>@endif
                                <dt class="col-5 text-muted fw-normal">Areas</dt><dd class="col-7">enclosed {{ isset($deed['enclosed_area_sqm']) ? number_format((float) $deed['enclosed_area_sqm'], 0) : '—' }} m² · verandas {{ isset($deed['covered_veranda_sqm']) ? number_format((float) $deed['covered_veranda_sqm'], 0) : '—' }} m²</dd>
                                @if(isset($deed['common_property_share_pct']))<dt class="col-5 text-muted fw-normal">Common share</dt><dd class="col-7">{{ $deed['common_property_share_pct'] }} %</dd>@endif
                                @if(! empty($deed['owners']))<dt class="col-5 text-muted fw-normal">Owners</dt><dd class="col-7">@foreach($deed['owners'] as $o){{ $o['name'] ?? '—' }} ({{ $o['share'] ?? '—' }})@if(! $loop->last), @endif @endforeach</dd>@endif
                                @if(! empty($deed['valuations']))<dt class="col-5 text-muted fw-normal">Valuation</dt><dd class="col-7">@foreach($deed['valuations'] as $val){{ $val['date'] ?? '' }}: {{ $val['currency'] ?? '' }} {{ isset($val['amount']) ? number_format((float) $val['amount'], 0) : '—' }}@if(! $loop->last)<br>@endif @endforeach</dd>@endif
                            </dl>
                        @else
                            <div class="border rounded p-3 text-center text-muted small">
                                <i class="bi bi-file-earmark-text fs-3 d-block mb-1"></i>
                                No deed data on file.
                                @can('manage_assets')<div class="mt-2"><a href="{{ route('assets.import.create') }}" class="btn btn-sm btn-outline-primary">Import a deed scan</a></div>@endcan
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Documents ---------- --}}
        <div class="tab-pane fade {{ $tab === 'documents' ? 'show active' : '' }}" id="tab-documents" role="tabpanel">
            <div class="card-body">
                @can('manage_assets')
                    <form method="POST" action="{{ route('assets.documents.store', $asset) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-3 border rounded p-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label mb-1" for="docFile">Upload a document</label>
                            <input id="docFile" type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1" for="docType">Type</label>
                            <select id="docType" name="doc_type" class="form-select @error('doc_type') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\AssetDocument::TYPES as $dt)
                                    <option value="{{ $dt }}" @selected(old('doc_type') === $dt)>{{ $dt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1" for="docExp">Expires</label>
                            <input id="docExp" type="date" name="expires_at" value="{{ old('expires_at') }}" class="form-control @error('expires_at') is-invalid @enderror">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-outline-primary"><i class="bi bi-upload"></i> Upload</button>
                        </div>
                        <div class="col-12">
                            <input type="text" name="notes" value="{{ old('notes') }}" class="form-control form-control-sm" maxlength="500" placeholder="Notes (optional)" aria-label="Document notes">
                        </div>
                    </form>
                @endcan

                @if($documents->count())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th scope="col">File</th><th scope="col">Type</th><th scope="col">Expires</th><th scope="col" class="text-end">Actions</th></tr></thead>
                            <tbody>
                            @foreach($documents as $doc)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $doc->title ?: $doc->original_name }}</div>
                                        <div class="small text-muted">{{ $doc->created_at?->format('d M Y') }} · {{ number_format(($doc->size_bytes ?? 0) / 1024, 0) }} KB @if($doc->notes) · {{ $doc->notes }} @endif</div>
                                    </td>
                                    <td>{{ $doc->doc_type ?: '—' }}</td>
                                    <td>
                                        @if($doc->expires_at)
                                            {{ $doc->expires_at->format('d M Y') }}
                                            @if($doc->expires_at->isPast())<span class="badge text-bg-danger ms-1">Expired</span>
                                            @elseif($doc->expires_at->lte(now()->addDays(30)))<span class="badge text-bg-warning ms-1">Soon</span>@endif
                                        @else — @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('assets.documents.download', ['asset' => $asset->id, 'document' => $doc->id]) }}" aria-label="Download"><i class="bi bi-download"></i></a>
                                        @can('manage_assets')
                                            <form method="POST" action="{{ route('assets.documents.destroy', ['asset' => $asset->id, 'document' => $doc->id]) }}" class="d-inline" onsubmit="return confirm('Delete this document?');">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted small py-4">No documents yet.</div>
                @endif
            </div>
        </div>

        {{-- ---------- Agreements ---------- --}}
        @can('manage_asset_rentals')
        <div class="tab-pane fade {{ $tab === 'agreements' ? 'show active' : '' }}" id="tab-agreements" role="tabpanel">
            <div class="card-body">
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('assets.rentals.index', ['asset_id' => $asset->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> New agreement</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Tenant</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Type</th><th scope="col" class="text-end">Rent / month</th><th scope="col" class="text-end"></th></tr></thead>
                        <tbody>
                        @forelse($asset->rentals as $r)
                            <tr>
                                <td>{{ $r->tenant?->name ?? $r->tenant_name ?? '—' }} @if($r->is_active)<span class="badge text-bg-success ms-1">active</span>@endif</td>
                                <td>{{ optional($r->agreement_start_date)->format('d M Y') ?? '—' }}</td>
                                <td>{{ optional($r->agreement_end_date)->format('d M Y') ?? 'open' }}</td>
                                <td>{{ $r->rent_type ?: '—' }}</td>
                                <td class="text-end">{{ $r->currency }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td class="text-end"><a href="{{ route('assets.rentals.edit', $r) }}" class="btn btn-sm btn-outline-secondary" aria-label="Edit"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No agreements yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endcan

        {{-- ---------- Payments ---------- --}}
        @can('manage_rental_payments')
        <div class="tab-pane fade {{ $tab === 'payments' ? 'show active' : '' }}" id="tab-payments" role="tabpanel">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted small">
                        Monthly payments are generated from the active agreement. If a manager pays you a variable amount,
                        upload their monthly statement: the month is read from it and the net payout is proposed.
                    </div>
                    <form method="POST" action="{{ route('payments.statementForAsset', $asset) }}" enctype="multipart/form-data" id="assetStatementForm">@csrf
                        <input type="file" name="file" accept=".pdf,image/*" class="d-none" onchange="this.form.requestSubmit()">
                        <button type="button" class="btn btn-sm btn-primary text-nowrap" onclick="document.querySelector('#assetStatementForm input[type=file]').click()">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload statement
                        </button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Period</th><th scope="col">Due</th><th scope="col">Tenant</th><th scope="col" class="text-end">Amount</th><th scope="col">Status</th><th scope="col" class="text-end"></th></tr></thead>
                        <tbody>
                        @forelse($payments as $p)
                            <tr class="{{ $p->isOverdue() ? 'table-danger' : '' }}">
                                <td>{{ $p->periodLabel() ?: '—' }}</td>
                                <td>{{ optional($p->due_date)->format('d M Y') }}</td>
                                <td>{{ $p->tenantName() ?? '—' }}</td>
                                <td class="text-end">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td>
                                    @if($p->isPaid())<span class="badge text-bg-success">Paid {{ optional($p->paid_date)->format('d M') }}</span>
                                    @elseif($p->status === \App\Models\RentalPayment::STATUS_NOT_RECEIVED)<span class="badge text-bg-danger">Not received</span>
                                    @elseif($p->isOverdue())<span class="badge text-bg-danger">Overdue</span>
                                    @else<span class="badge text-bg-warning">Pending</span>@endif
                                </td>
                                <td class="text-end">@include('payments._actions', ['p' => $p])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments recorded. They are generated monthly from the active agreement.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-2"><a href="{{ route('payments.index', ['rental_id' => $currentRental?->id]) }}" class="small">All payments</a></div>
            </div>
        </div>
        @endcan

        {{-- ---------- Expenses ---------- --}}
        @can('manage_asset_expenses')
        <div class="tab-pane fade {{ $tab === 'expenses' ? 'show active' : '' }}" id="tab-expenses" role="tabpanel">
            <div class="card-body">
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('expenses.index', ['asset_id' => $asset->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Add expense</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Date</th><th scope="col">Category</th><th scope="col">Vendor / description</th><th scope="col" class="text-end">Amount</th></tr></thead>
                        <tbody>
                        @forelse($expenses as $x)
                            <tr>
                                <td>{{ optional($x->spent_on)->format('d M Y') }}</td>
                                <td>{{ $x->category }}</td>
                                <td>{{ $x->vendor ?: '' }}@if($x->vendor && $x->description) · @endif{{ $x->description ?: '' }}</td>
                                <td class="text-end">{{ $x->currency }} {{ number_format((float) $x->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No expenses recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endcan
    </div>
</div>

@can('manage_rental_payments')
@include('payments._adjust_modal')
@endcan

@can('manage_assets')
<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Delete <b>{{ $asset->name }}</b>?<br>
                <span class="text-muted">Its agreements, payments, expenses and documents go with it.</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('assets.destroy', $asset) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection
