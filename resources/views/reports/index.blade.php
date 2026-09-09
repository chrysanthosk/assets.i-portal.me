@extends('layouts.app')

@section('content')
@php
    $m = fn ($n) => $base.' '.number_format((float) $n, 2);
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
@endphp

<div class="page-head">
    <div>
        <h1>Profit &amp; loss {{ $year }}</h1>
        <div class="sub">Rent actually received minus expenses{{ $multiCurrency ? ', converted to '.$base : '' }}.</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="GET" action="{{ route('reports.index') }}" class="d-flex gap-2 align-items-center">
            <label class="text-muted small mb-0" for="reportYear">Year</label>
            <select id="reportYear" name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('reports.export', ['year' => $year]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> CSV</a>
    </div>
</div>

@if(! empty($unknownCurrencies))
    <div class="alert alert-warning">
        No FX rate configured for <strong>{{ implode(', ', $unknownCurrencies) }}</strong>, so those amounts are excluded.
        @can('manage_fx_rates')<a href="{{ route('settings.currencies.edit') }}" class="alert-link">Add rates</a>.@endcan
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3"><x-stat icon="bi-arrow-down-left-circle" label="Income" :value="$m($totals['income'])" tone="success" /></div>
    <div class="col-12 col-sm-6 col-xl-3"><x-stat icon="bi-arrow-up-right-circle" label="Expenses" :value="$m($totals['expenses'])" tone="danger" /></div>
    <div class="col-12 col-sm-6 col-xl-3"><x-stat icon="bi-piggy-bank" label="Net" :value="$m($totals['net'])" :tone="$totals['net'] < 0 ? 'danger' : 'info'" /></div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-stat icon="bi-percent" label="Rent collected"
                :value="$collection['rate'] !== null ? $collection['rate'].' %' : '—'"
                :sub="$collection['expected'] > 0 ? $m($collection['collected']).' of '.$m($collection['expected']).' due' : 'No rent due this year'"
                :tone="$collection['rate'] !== null && $collection['rate'] < 90 ? 'warning' : ''" />
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-buildings me-1"></i> Per property</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <th scope="col"ead><tr>
                        <th scope="col">Property</th>
                        <th scope="col" class="text-end">Income</th>
                        <th scope="col" class="text-end">Expenses</th>
                        <th scope="col" class="text-end">Net</th>
                    </tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="fw-medium">{{ $row['asset'] }}</td>
                            <td class="text-end">{{ number_format($row['income'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['expenses'], 2) }}</td>
                            <td class="text-end fw-semibold {{ $row['net'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['net'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No properties yet.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot><tr class="fw-semibold">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($totals['income'], 2) }}</td>
                        <td class="text-end">{{ number_format($totals['expenses'], 2) }}</td>
                        <td class="text-end {{ $totals['net'] < 0 ? 'text-danger' : '' }}">{{ number_format($totals['net'], 2) }}</td>
                    </tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar3 me-1"></i> By month</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <th scope="col"ead><tr><th scope="col">Month</th><th scope="col" class="text-end">Income</th><th scope="col" class="text-end">Expenses</th><th scope="col" class="text-end">Net</th></tr></thead>
                    <tbody>
                    @foreach($months as $i => $mo)
                        @php $net = $mo['income'] - $mo['expenses']; $empty = $mo['income'] == 0 && $mo['expenses'] == 0; @endphp
                        <tr class="{{ $empty ? 'text-muted' : '' }}">
                            <td>{{ $monthNames[$i - 1] }}</td>
                            <td class="text-end">{{ $empty ? '—' : number_format($mo['income'], 2) }}</td>
                            <td class="text-end">{{ $empty ? '—' : number_format($mo['expenses'], 2) }}</td>
                            <td class="text-end {{ $net < 0 ? 'text-danger' : '' }}">{{ $empty ? '—' : number_format($net, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
