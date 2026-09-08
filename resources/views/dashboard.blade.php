@extends('layouts.app')

@section('content')
@php
    $fullName = trim(($user->name ?? '').' '.($user->surname ?? '')) ?: $user->username;
    $periodLabel = \Carbon\Carbon::createFromDate($currentYear, $currentMonth, 1)->format('F Y');
    $money = fn ($n) => $base.' '.number_format((float) $n, 2);
    $byCur = fn ($rows) => collect($rows)->map(fn ($r) => $r->currency.' '.number_format((float) $r->total, 2))->implode(' · ');
    $attentionCount = ($unconfirmedPaymentsCount ?? 0) + ($overduePaymentsCount ?? 0) + ($expiredDocsCount ?? 0) + ($expiringDocsCount ?? 0);
@endphp

<div class="page-head">
    <div>
        <h1>{{ $greeting }}, {{ $fullName }} 👋</h1>
        <div class="sub">Here's your portfolio for {{ $periodLabel }}.</div>
    </div>
    @can('manage_assets')
        <div class="d-flex gap-2">
            <a href="{{ route('assets.import.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import title deed</a>
        </div>
    @endcan
</div>

{{-- Stat tiles --}}
@php $y = now()->year; $reportUrl = auth()->user()->can('view_reports') ? route('reports.index', ['year' => $y]) : null; @endphp
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-buildings" label="Properties" :value="number_format($totalAssets)"
                :sub="number_format($occupiedCount ?? 0).' occupied · '.number_format($vacantCount ?? 0).' vacant'"
                :href="auth()->user()->can('manage_assets') ? route('assets.index') : null" />
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-cash-stack" label="Portfolio value" :value="$money($totalAssetsValue)"
                :sub="'Purchase prices'.(count($unknownCurrencies) ? ' · <span class=\'text-warning-emphasis\'>no FX rate for '.e(implode(', ', $unknownCurrencies)).'</span>' : ' in '.$base)" tone="info" />
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-graph-up-arrow" label="Contracted rent / month" :value="$money($monthlyIncome)"
                :sub="number_format($activeAgreementsCount ?? 0).' active agreement'.(($activeAgreementsCount ?? 0) === 1 ? '' : 's').' · '.$money($year['contracted']).' / year'.($monthlyIncomeByCurrency->count() > 1 ? ' · '.e($byCur($monthlyIncomeByCurrency)) : '')" tone="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-piggy-bank" :label="'Rent received in '.$y" :value="$money($year['income'])"
                :sub="$year['rate'] !== null ? $year['rate'].' % of the '.$money($year['due']).' due so far' : 'Nothing due yet this year'"
                :tone="$year['rate'] !== null && $year['rate'] < 90 ? 'warning' : 'success'" :href="$reportUrl" />
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-wallet2" label="Outstanding"
                :value="$outstandingByCurrency->isNotEmpty() ? $money($outstandingTotal) : '—'"
                :sub="($overduePaymentsCount ?? 0).' overdue · '.($unconfirmedPaymentsCount ?? 0).' to confirm'.($outstandingByCurrency->count() > 1 ? ' · '.e($byCur($outstandingByCurrency)) : '')"
                :tone="($overduePaymentsCount ?? 0) ? 'danger' : (($unconfirmedPaymentsCount ?? 0) ? 'warning' : '')"
                :href="auth()->user()->can('manage_rental_payments') ? route('payments.unconfirmed') : null" />
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <x-stat icon="bi-calculator" :label="'Net '.$y" :value="$money($year['income'] - $year['expenses'])"
                :sub="'received '.$money($year['income']).' · expenses '.$money($year['expenses'])"
                :tone="($year['income'] - $year['expenses']) < 0 ? 'danger' : 'info'" :href="$reportUrl" />
    </div>
</div>

<div class="row g-3">
    {{-- Rent check --}}
    @can('manage_rental_payments')
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <span><i class="bi bi-question-circle me-1"></i> Did the rent arrive?</span>
                <a href="{{ route('payments.unconfirmed') }}" class="small">All</a>
            </div>
            <div class="card-body p-2">
                @forelse($rentToConfirm as $p)
                    <div class="feed-row">
                        <div class="min-w-0">
                            <div class="fw-medium text-truncate">{{ $p->asset?->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $p->tenantName() ?? 'No tenant' }} · {{ $p->periodLabel() }}</div>
                        </div>
                        <div class="text-end text-nowrap">
                            <div class="fw-semibold">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</div>
                            <div class="d-flex gap-1 justify-content-end mt-1">
                                <form method="POST" action="{{ route('payments.markPaid', $p) }}" data-no-loading>@csrf<button class="btn btn-sm btn-success py-0">Yes</button></form>
                                <form method="POST" action="{{ route('payments.markNotReceived', $p) }}" data-no-loading>@csrf<button class="btn btn-sm btn-outline-danger py-0">No</button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">Nothing to confirm 🎉</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Overdue --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100 {{ $overdueRent->isNotEmpty() ? 'border-danger' : '' }}">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <span><i class="bi bi-exclamation-circle me-1 {{ $overdueRent->isNotEmpty() ? 'text-danger' : '' }}"></i> Overdue rent</span>
                <a href="{{ route('payments.index', ['status' => 'overdue']) }}" class="small">All</a>
            </div>
            <div class="card-body p-2">
                @forelse($overdueRent as $p)
                    <div class="feed-row">
                        <div class="min-w-0">
                            <div class="fw-medium text-truncate">{{ $p->asset?->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $p->tenantName() ?? 'No tenant' }} · due {{ optional($p->due_date)->format('d M Y') }}</div>
                        </div>
                        <div class="text-end text-nowrap">
                            <div class="fw-semibold text-danger">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</div>
                            <span class="badge text-bg-danger">{{ $p->daysLate() }}d late</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">No arrears</div>
                @endforelse
            </div>
        </div>
    </div>
    @endcan

    {{-- Documents --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-file-earmark-x me-1"></i> Documents expiring</div>
            <div class="card-body p-2">
                @forelse($expiringDocs as $d)
                    @php $expired = $d->expires_at && \Carbon\Carbon::parse($d->expires_at)->isPast(); @endphp
                    <a class="feed-row text-reset" href="{{ route('assets.show', $d->asset_id) }}">
                        <div class="min-w-0">
                            <div class="fw-medium text-truncate">{{ $d->title ?: $d->original_name }}</div>
                            <div class="small text-muted">{{ $d->asset?->name ?? '—' }} @if($d->doc_type)· {{ $d->doc_type }}@endif</div>
                        </div>
                        <span class="badge {{ $expired ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $expired ? 'expired' : 'expires' }} {{ \Carbon\Carbon::parse($d->expires_at)->format('d M') }}</span>
                    </a>
                @empty
                    <div class="text-center text-muted small py-4">Nothing expiring in the next 30 days</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent activity --}}
    @if($recentActivity->isNotEmpty())
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <span><i class="bi bi-clipboard-data me-1"></i> Recent activity</span>
                @can('manage_audit_logs')<a href="{{ route('audit.index') }}" class="small">Audit log</a>@endcan
            </div>
            <div class="card-body p-2">
                @foreach($recentActivity as $a)
                    <div class="feed-row">
                        <div class="min-w-0">
                            <span class="mono">{{ $a->action }}</span>
                            <span class="small text-muted ms-1">{{ $a->user?->username ?? 'system' }}@if($a->entity) · {{ $a->entity }}@if($a->entity_id) #{{ $a->entity_id }}@endif @endif</span>
                        </div>
                        <span class="when">{{ $a->created_at?->diffForHumans(null, true) }} ago</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
