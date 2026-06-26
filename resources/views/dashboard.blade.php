@extends('layouts.app')

@section('content')
@php
  $fullName = trim(($user->name ?? '') . ' ' . ($user->surname ?? ''));
  $periodLabel = sprintf('%04d-%02d', $currentYear, $currentMonth);
@endphp

<div class="row">
  <div class="col-12 mb-3">
    <div class="alert alert-secondary mb-0">
      <strong>{{ $greeting }} {{ $fullName ?: $user->username }}</strong>
      <div class="text-muted small mt-1">
        Period: {{ $periodLabel }}
      </div>
    </div>
  </div>

  <!-- Total Assets -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Total Assets</div>
            <div class="fs-3 fw-semibold">{{ number_format($totalAssets) }}</div>
          </div>
          <i class="bi bi-buildings fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Total Value -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Total Value</div>
            <div class="fs-3 fw-semibold">€ {{ number_format($totalAssetsValue, 2) }}</div>
            <div class="text-muted small">Sum of purchase prices</div>
          </div>
          <i class="bi bi-cash-coin fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Monthly Income (All records for the period) -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Monthly Income</div>
            <div class="fs-3 fw-semibold">€ {{ number_format((float)$monthlyIncome, 2) }}</div>

            @if(isset($monthlyIncomeByCurrency) && $monthlyIncomeByCurrency->count() > 0)
              <div class="text-muted small mt-1">
                @foreach($monthlyIncomeByCurrency as $row)
                  <span class="me-2">
                    {{ $row->currency }} {{ number_format((float)$row->total, 2) }}
                  </span>
                @endforeach
              </div>
            @else
              <div class="text-muted small mt-1">No income records for this period</div>
            @endif
          </div>
          <i class="bi bi-graph-up-arrow fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Occupied / Vacant -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Occupied / Vacant</div>
            <div class="fs-6 fw-semibold">
              <span class="me-2"><i class="bi bi-door-open"></i> Occupied: {{ number_format($occupiedCount ?? 0) }}</span>
              <span><i class="bi bi-door-closed"></i> Vacant: {{ number_format($vacantCount ?? 0) }}</span>
            </div>

            @if(($otherStatusCount ?? 0) > 0)
              <div class="text-muted small mt-1">
                Other: {{ number_format($otherStatusCount) }}
              </div>
            @endif
          </div>
          <i class="bi bi-house-check fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Active Agreements -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Active Agreements</div>
            <div class="fs-3 fw-semibold">{{ number_format($activeAgreementsCount ?? 0) }}</div>
            <div class="text-muted small">Based on start/end dates</div>
          </div>
          <i class="bi bi-file-earmark-check fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Outstanding payments / arrears -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card {{ ($overduePaymentsCount ?? 0) ? 'border-danger' : '' }}">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Outstanding</div>
            @forelse(($outstandingByCurrency ?? collect()) as $row)
              <div class="fs-5 fw-semibold">{{ $row->currency }} {{ number_format((float) $row->total, 2) }}</div>
            @empty
              <div class="fs-5 fw-semibold text-muted">—</div>
            @endforelse
            <div class="small {{ ($overduePaymentsCount ?? 0) ? 'text-danger' : 'text-muted' }}">
              {{ $overduePaymentsCount ?? 0 }} overdue
              @can('manage_rental_payments') · <a href="{{ route('payments.index') }}">view</a> @endcan
            </div>
          </div>
          <i class="bi bi-wallet2 fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Document expiry reminders -->
  <div class="col-12 col-lg-3 mb-3">
    <div class="card {{ ($expiredDocsCount ?? 0) ? 'border-danger' : (($expiringDocsCount ?? 0) ? 'border-warning' : '') }}">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Documents</div>
            <div class="fs-3 fw-semibold {{ ($expiredDocsCount ?? 0) ? 'text-danger' : '' }}">{{ $expiredDocsCount ?? 0 }}</div>
            <div class="small {{ ($expiringDocsCount ?? 0) ? 'text-warning-emphasis' : 'text-muted' }}">
              {{ $expiredDocsCount ?? 0 }} expired · {{ $expiringDocsCount ?? 0 }} expiring ≤30d
            </div>
          </div>
          <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Monthly Income (Active agreements only) -->
  <div class="col-12 col-lg-6 mb-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted text-uppercase small">Monthly Income (Active Only)</div>
            <div class="fs-3 fw-semibold">€ {{ number_format((float)($monthlyIncomeActiveOnly ?? 0), 2) }}</div>

            @if(isset($monthlyIncomeActiveByCurrency) && $monthlyIncomeActiveByCurrency->count() > 0)
              <div class="text-muted small mt-1">
                @foreach($monthlyIncomeActiveByCurrency as $row)
                  <span class="me-2">
                    {{ $row->currency }} {{ number_format((float)$row->total, 2) }}
                  </span>
                @endforeach
              </div>
            @else
              <div class="text-muted small mt-1">No active-agreement income for this period</div>
            @endif
          </div>
          <i class="bi bi-currency-exchange fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
