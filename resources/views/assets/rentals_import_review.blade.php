@extends('layouts.app')
@section('title', 'Review contract')

@section('content')
@php $v = fn ($k, $d = null) => old($k, $prefill[$k] ?? $d); @endphp
<div class="page-head">
    <div>
        <h1>Review extracted contract</h1>
        <div class="sub">{{ $import->original_name }}. Correct anything that looks wrong, then create the agreement.</div>
    </div>
    <form method="POST" action="{{ route('assets.rentals.import.destroy', $import) }}" onsubmit="return confirm('Discard this import?');">@csrf @method('DELETE')
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Discard</button></form>
</div>

@if(! empty($terms['warnings']))
    <div class="alert alert-warning"><div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Check these</div>
        <ul class="mb-0">@foreach($terms['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('assets.rentals.import.confirm', $import) }}">
            @csrf
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Agreement</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Property *</label>
                        <select name="asset_id" class="form-select @error('asset_id') is-invalid @enderror" required>
                            <option value="">— Select —</option>
                            @foreach($assets as $a)<option value="{{ $a->id }}" @selected((string) $v('asset_id') === (string) $a->id)>{{ $a->name }}</option>@endforeach
                        </select>
                        @error('asset_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if(! $v('asset_id'))<div class="form-text text-warning-emphasis">Could not match "{{ $terms['property_reference'] ?? '' }}" to a property — pick it.</div>@endif
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tenant</label>
                        <select name="tenant_id" class="form-select">
                            <option value="">— none —</option>
                            @foreach($tenants as $t)<option value="{{ $t->id }}" @selected((string) $v('tenant_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">…or name</label>
                        <input name="tenant_name" class="form-control" value="{{ $v('tenant_name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start *</label>
                        <input type="date" name="agreement_start_date" class="form-control @error('agreement_start_date') is-invalid @enderror" value="{{ $v('agreement_start_date') }}" required>
                        @error('agreement_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End</label>
                        <input type="date" name="agreement_end_date" class="form-control" value="{{ $v('agreement_end_date') }}">
                        <div class="form-text">Leave empty if it renews automatically.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type *</label>
                        <select name="rent_type" class="form-select" required>
                            @foreach(['Long-term', 'Airbnb', 'Other'] as $t)<option value="{{ $t }}" @selected($v('rent_type') === $t)>{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Active *</label>
                        <select name="is_active" class="form-select" required>
                            <option value="1" @selected((string) $v('is_active', 1) === '1')>Yes</option>
                            <option value="0" @selected((string) $v('is_active', 1) === '0')>No</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Currency *</label>
                        <input name="currency" class="form-control" value="{{ $v('currency', 'EUR') }}" maxlength="3" required>
                    </div>

                    @include('assets._schedule_fields', ['rental' => $rental])

                    <div class="col-md-6">
                        <label class="form-label">Channel / manager</label>
                        <input name="channel" class="form-control" value="{{ $v('channel') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="4" class="form-control">{{ $v('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('assets.rentals.import.create') }}" class="btn btn-outline-secondary">Back</a>
                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Create agreement</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-text me-1"></i> What the contract says</h6></div>
            <div class="card-body small">
                <dl class="row mb-2">
                    <dt class="col-5 text-muted">Counterparty</dt><dd class="col-7">{{ $terms['counterparty'] ?? '—' }} <span class="text-muted">({{ $terms['counterparty_type'] ?? '—' }})</span></dd>
                    <dt class="col-5 text-muted">Property</dt><dd class="col-7">{{ $terms['property_reference'] ?? '—' }}<div class="text-muted">{{ $terms['property_address'] ?? '' }}</div></dd>
                    <dt class="col-5 text-muted">Period</dt><dd class="col-7">{{ $terms['contract_start'] ?? '—' }} → {{ $terms['contract_end'] ?? '—' }} @if(($terms['auto_renews'] ?? null) === 'yes')<span class="badge text-bg-info">renews</span>@endif</dd>
                    <dt class="col-5 text-muted">Signed</dt><dd class="col-7">{{ $terms['signed_on'] ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Schedule</dt><dd class="col-7">{{ $terms['schedule_type'] ?? '—' }}
                        @if(! empty($terms['monthly_amount'])) · {{ $terms['currency'] }} {{ number_format($terms['monthly_amount'], 2) }} / month @endif
                        @if(! empty($terms['annual_amount'])) · {{ $terms['currency'] }} {{ number_format($terms['annual_amount'], 2) }} / year @endif
                        @if(($terms['amounts_exclude_vat'] ?? null) === 'yes') · excl. VAT @endif
                    </dd>
                </dl>
                @if(! empty($terms['installments']))
                    <h6 class="mt-2">First-year payments</h6>
                    <table class="table table-sm mb-2"><tbody>
                        @foreach($terms['installments'] as $i)
                            <tr><td>{{ $i['date'] ?? '—' }}</td><td>{{ $i['label'] ?? '' }}@if(! empty($i['percent'])) <span class="text-muted">{{ $i['percent'] }} %</span>@endif</td><td class="text-end">{{ $i['amount'] !== null ? number_format($i['amount'], 2) : '—' }}</td></tr>
                        @endforeach
                    </tbody></table>
                @endif
                @if(! empty($terms['commission_terms']))<h6 class="mt-2">Commission</h6><p class="mb-2">{{ $terms['commission_terms'] }}</p>@endif
                @if(! empty($terms['obligations']))<h6 class="mt-2">Your obligations</h6><p class="mb-0">{{ $terms['obligations'] }}</p>@endif
            </div>
        </div>
    </div>
</div>
@endsection
