@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Profit &amp; Loss</h5>
            <small class="text-muted">Realized rental income minus expenses, in {{ $base }}</small>
        </div>
        <div class="d-flex gap-2 align-items-center ms-auto">
            <form method="GET" action="{{ route('reports.index') }}" class="d-flex gap-2 align-items-center">
                <label class="text-muted small mb-0">Year</label>
                <input type="number" name="year" value="{{ $year }}" class="form-control form-control-sm"
                       style="width: 100px;" onchange="this.form.submit()">
            </form>
            <a href="{{ route('reports.export', ['year' => $year]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="card-body">

        @if(!empty($unknownCurrencies))
            <div class="alert alert-warning py-2">
                No FX rate configured for: <strong>{{ implode(', ', $unknownCurrencies) }}</strong>.
                Those amounts are counted at face value.
                <a href="{{ route('settings.currencies.edit') }}">Add rates</a>.
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card bg-body-tertiary"><div class="card-body">
                    <div class="text-muted text-uppercase small">Income</div>
                    <div class="fs-4 fw-semibold">{{ $base }} {{ number_format($totals['income'], 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card bg-body-tertiary"><div class="card-body">
                    <div class="text-muted text-uppercase small">Expenses</div>
                    <div class="fs-4 fw-semibold">{{ $base }} {{ number_format($totals['expenses'], 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card {{ $totals['net'] < 0 ? 'border-danger' : 'border-success' }}"><div class="card-body">
                    <div class="text-muted text-uppercase small">Net</div>
                    <div class="fs-4 fw-semibold {{ $totals['net'] < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $base }} {{ number_format($totals['net'], 2) }}
                    </div>
                </div></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th scope="col">Asset</th>
                    <th scope="col" class="text-end">Income ({{ $base }})</th>
                    <th scope="col" class="text-end">Expenses ({{ $base }})</th>
                    <th scope="col" class="text-end">Net ({{ $base }})</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['asset'] }}</td>
                        <td class="text-end">{{ number_format($row['income'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['expenses'], 2) }}</td>
                        <td class="text-end {{ $row['net'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No assets.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-semibold border-top">
                    <td>Total</td>
                    <td class="text-end">{{ number_format($totals['income'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['expenses'], 2) }}</td>
                    <td class="text-end {{ $totals['net'] < 0 ? 'text-danger' : '' }}">{{ number_format($totals['net'], 2) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>

        <p class="text-muted small mb-0">
            Income = rental payments marked <em>paid</em> with a paid date in {{ $year }}. Expenses = costs dated in {{ $year }}.
        </p>
    </div>
</div>
@endsection
