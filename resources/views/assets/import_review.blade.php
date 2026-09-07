@extends('layouts.app')

@section('content')
@php
    $v = fn ($key, $default = null) => old($key, $prefill[$key] ?? $default);
    $owners = $deed['owners'] ?? [];
    $valuations = $deed['valuations'] ?? [];
    $plotRef = implode(' / ', array_filter([$deed['sheet'] ?? null, $deed['plan'] ?? null, $deed['section'] ?? null, $deed['plot'] ?? null], fn ($x) => $x !== null && $x !== ''));
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 2);
@endphp

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="mb-0">Review extracted deed</h4>
                <div class="text-muted small">{{ $import->original_name }} · read by {{ $import->model ?? 'model' }}. Correct anything that looks wrong, then create the property.</div>
            </div>
            <form method="POST" action="{{ route('assets.import.destroy', $import) }}" onsubmit="return confirm('Discard this import?');">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Discard</button>
            </form>
        </div>
    </div>

    @if(! empty($deed['warnings']))
        <div class="col-12">
            <div class="alert alert-warning mb-0">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Check these</div>
                <ul class="mb-0">@foreach($deed['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    {{-- Editable asset form --}}
    <div class="col-lg-7">
        <form method="POST" action="{{ route('assets.import.confirm', $import) }}">
            @csrf
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Property</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name *</label>
                        <input name="name" value="{{ $v('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type *</label>
                        <select name="asset_type_id" class="form-select @error('asset_type_id') is-invalid @enderror" required>
                            @foreach($assetTypes as $t)
                                <option value="{{ $t->id }}" @selected((string) $v('asset_type_id') === (string) $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Address</label>
                        <input name="address" value="{{ $v('address') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City / community</label>
                        <input name="city" value="{{ $v('city') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Postcode</label>
                        <input name="postcode" value="{{ $v('postcode') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <input name="country" value="{{ $v('country') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" @selected($v('status') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    @advanced
                    <div class="col-md-4">
                        <label class="form-label">Owner entity</label>
                        <select name="owner_entity_id" class="form-select">
                            <option value="">— none —</option>
                            @foreach($ownerEntities as $o)
                                <option value="{{ $o->id }}" @selected((string) $v('owner_entity_id') === (string) $o->id)>{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endadvanced
                    <div class="col-md-4">
                        <label class="form-label">Ownership %</label>
                        <input type="number" step="0.01" min="0" max="100" name="ownership_percentage" value="{{ $v('ownership_percentage', 100) }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency *</label>
                        <input name="currency" value="{{ $v('currency', 'EUR') }}" maxlength="3" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Title deed &amp; size</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Registration no.</label>
                        <input name="title_deed_number" value="{{ $v('title_deed_number') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Registration date</label>
                        <input type="date" name="title_deed_date" value="{{ $v('title_deed_date') }}" class="form-control">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="hidden" name="parking" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="parking" name="parking" value="1" @checked((string) $v('parking') === '1')>
                            <label class="form-check-label" for="parking">Parking</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Enclosed area (m²)</label>
                        <input type="number" step="0.01" min="0" name="size_sqm" value="{{ $v('size_sqm') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Land / plot (m²)</label>
                        <input type="number" step="0.01" min="0" name="land_sqm" value="{{ $v('land_sqm') }}" class="form-control">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Purchase (optional, not on the deed)</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Purchase date</label>
                        <input type="date" name="purchase_date" value="{{ old('purchase_date') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Purchase price</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price') }}" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="4" class="form-control">{{ $v('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('assets.import.create') }}" class="btn btn-outline-secondary">Back</a>
                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Create property</button>
            </div>
        </form>
    </div>

    {{-- Read-only: what was found on the deed --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text me-1"></i> What the deed says</h6></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Document</dt><dd class="col-7">{{ $deed['document_type'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Registration no.</dt><dd class="col-7">{{ $deed['registration_number'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Registered</dt><dd class="col-7">{{ $deed['registration_date'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">File no.</dt><dd class="col-7">{{ $deed['file_number'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">District</dt><dd class="col-7">{{ $deed['district'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Municipality</dt><dd class="col-7">{{ $deed['municipality_community'] ?? '—' }}@if(! empty($deed['parish'])) · parish {{ $deed['parish'] }}@endif</dd>
                    <dt class="col-5 text-muted">Locality</dt><dd class="col-7">{{ $deed['locality'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Street</dt><dd class="col-7">{{ $deed['street_address'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Building</dt><dd class="col-7">{{ $deed['building_name'] ?? '—' }}@if(! empty($deed['unit_number'])) · No. {{ $deed['unit_number'] }}@endif @if(! empty($deed['floor'])) · {{ $deed['floor'] }}@endif</dd>
                    <dt class="col-5 text-muted">Sheet / plan / section / plot</dt><dd class="col-7">{{ $plotRef ?: '—' }}</dd>
                    <dt class="col-5 text-muted">Type</dt><dd class="col-7">{{ $deed['property_type'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Description</dt><dd class="col-7">{{ $deed['property_description'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Parking / storage</dt><dd class="col-7">{{ $deed['parking_spaces'] ?? 0 }} / {{ $deed['storage_rooms'] ?? 0 }}</dd>
                    <dt class="col-5 text-muted">Enclosed</dt><dd class="col-7">{{ $fmt($deed['enclosed_area_sqm'] ?? null) }} m²</dd>
                    <dt class="col-5 text-muted">Verandas</dt><dd class="col-7">covered {{ $fmt($deed['covered_veranda_sqm'] ?? null) }} · uncovered {{ $fmt($deed['uncovered_veranda_sqm'] ?? null) }} m²</dd>
                    @if(isset($deed['plot_area_sqm']))<dt class="col-5 text-muted">Plot</dt><dd class="col-7">{{ $fmt($deed['plot_area_sqm']) }} m²</dd>@endif
                    <dt class="col-5 text-muted">Common share</dt><dd class="col-7">{{ $deed['common_property_share_pct'] !== null ? $deed['common_property_share_pct'].' %' : '—' }}</dd>
                </dl>

                <h6 class="mt-3">Owners</h6>
                <table class="table table-sm small mb-2">
                    <tbody>
                    @forelse($owners as $o)
                        <tr>
                            <td>{{ $o['name'] ?? '—' }}<div class="text-muted">{{ $o['address'] ?? '' }}</div></td>
                            <td class="text-end text-nowrap">{{ $o['share'] ?? '—' }}@if(isset($o['share_pct'])) ({{ $o['share_pct'] }}%)@endif</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted">None found</td></tr>
                    @endforelse
                    </tbody>
                </table>

                <h6 class="mt-3">General valuations</h6>
                <table class="table table-sm small mb-2">
                    <tbody>
                    @forelse($valuations as $val)
                        <tr><td>{{ $val['date'] ?? '—' }}</td><td class="text-end">{{ $val['currency'] ?? '' }} {{ $fmt($val['amount'] ?? null) }}</td></tr>
                    @empty
                        <tr><td class="text-muted">None found</td></tr>
                    @endforelse
                    </tbody>
                </table>

                @if(! empty($deed['rights_and_encumbrances']))
                    <h6 class="mt-3">Rights / encumbrances</h6>
                    <p class="small mb-0">{{ $deed['rights_and_encumbrances'] }}</p>
                @endif
                @if(! empty($deed['notes']))
                    <h6 class="mt-3">Notes</h6>
                    <p class="small mb-0">{{ $deed['notes'] }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
