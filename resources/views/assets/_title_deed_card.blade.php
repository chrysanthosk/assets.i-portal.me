@php
    $deed = $asset->title_deed_data ?? null;
@endphp
@if(is_array($deed))
@php
    $plotRef = implode(' / ', array_filter([$deed['sheet'] ?? null, $deed['plan'] ?? null, $deed['section'] ?? null, $deed['plot'] ?? null], fn ($x) => $x !== null && $x !== ''));
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 2);
@endphp
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-1"></i> Title deed</h5>
        <small class="text-muted">imported from scan</small>
    </div>
    <div class="card-body">
        <dl class="row small mb-0">
            <dt class="col-5 text-muted">Registration no.</dt><dd class="col-7">{{ $deed['registration_number'] ?? '—' }} @if(! empty($deed['registration_date']))· {{ $deed['registration_date'] }}@endif</dd>
            <dt class="col-5 text-muted">District / municipality</dt><dd class="col-7">{{ $deed['district'] ?? '—' }} / {{ $deed['municipality_community'] ?? '—' }}</dd>
            <dt class="col-5 text-muted">Sheet / plan / section / plot</dt><dd class="col-7">{{ $plotRef ?: '—' }}</dd>
            @if(! empty($deed['building_name']))<dt class="col-5 text-muted">Building</dt><dd class="col-7">{{ $deed['building_name'] }}@if(! empty($deed['unit_number'])) · No. {{ $deed['unit_number'] }}@endif</dd>@endif
            <dt class="col-5 text-muted">Areas</dt><dd class="col-7">enclosed {{ $fmt($deed['enclosed_area_sqm'] ?? null) }} · verandas {{ $fmt($deed['covered_veranda_sqm'] ?? null) }} m²</dd>
            @if(isset($deed['common_property_share_pct']))<dt class="col-5 text-muted">Common share</dt><dd class="col-7">{{ $deed['common_property_share_pct'] }} %</dd>@endif
            @if(! empty($deed['owners']))
                <dt class="col-5 text-muted">Owners</dt>
                <dd class="col-7">@foreach($deed['owners'] as $o){{ $o['name'] ?? '—' }} ({{ $o['share'] ?? '—' }})@if(! $loop->last), @endif @endforeach</dd>
            @endif
            @if(! empty($deed['valuations']))
                <dt class="col-5 text-muted">General valuation</dt>
                <dd class="col-7">@foreach($deed['valuations'] as $val){{ $val['date'] ?? '' }}: {{ $val['currency'] ?? '' }} {{ $fmt($val['amount'] ?? null) }}@if(! $loop->last)<br>@endif @endforeach</dd>
            @endif
        </dl>
    </div>
</div>
@endif
