@extends('layouts.app')

@section('content')
<div class="row g-3">

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-question-circle me-1"></i> Awaiting confirmation
                    <span class="badge text-bg-warning ms-1">{{ $payments->count() }}</span>
                </h5>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('payments.generate') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" title="Create this month's expected payments now">
                            <i class="bi bi-calendar-plus me-1"></i> Generate {{ now()->format('M Y') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('payments.sendReminders') }}"
                          onsubmit="return confirm('Email a confirmation request for every item below?');">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary" @disabled($payments->isEmpty())>
                            <i class="bi bi-envelope me-1"></i> Send reminders now
                        </button>
                    </form>
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary">All payments</a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Rent that is due but nobody has said yet whether it arrived. Reminders are
                    <strong>{{ $remindersEnabled ? 'on' : 'off' }}</strong>
                    @if($recipients)
                        and go to <strong>{{ implode(', ', $recipients) }}</strong>, repeating every {{ $repeatDays }} day(s) until answered.
                    @else
                        but <strong class="text-danger">no recipient is configured</strong>.
                    @endif
                    @can('manage_portal_settings') <a href="{{ route('settings.portal.edit') }}">Change</a>. @endcan
                </p>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Asset</th>
                            <th scope="col">Tenant</th>
                            <th scope="col">Period</th>
                            <th scope="col">Due</th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col">Reminders</th>
                            <th scope="col" class="text-end">Did it arrive?</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($payments as $p)
                            <tr class="{{ $p->isOverdue() ? 'table-warning' : '' }}">
                                <td>{{ $p->asset?->name ?? '—' }}</td>
                                <td>{{ $p->tenantName() ?? '—' }}</td>
                                <td>{{ $p->periodLabel() }}</td>
                                <td>{{ optional($p->due_date)->format('Y-m-d') }}
                                    @if($p->isOverdue())<span class="badge text-bg-danger ms-1">{{ $p->due_date->diffInDays(now()) }}d late</span>@endif
                                </td>
                                <td class="text-end">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="small text-muted">
                                    @if($p->reminder_count)
                                        {{ $p->reminder_count }} sent, last {{ $p->last_reminded_at?->diffForHumans() }}
                                    @else
                                        none yet
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('payments.markPaid', $p) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Yes</button>
                                    </form>
                                    <form method="POST" action="{{ route('payments.markNotReceived', $p) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> No</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Nothing awaiting confirmation 🎉</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($notReceived->isNotEmpty())
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header"><h5 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Reported as not received
                <span class="badge text-bg-danger ms-1">{{ $notReceived->count() }}</span></h5></div>
            <div class="card-body">
                <p class="text-muted small mb-3">You answered "No" for these. Chase the tenant, then mark them paid once the money lands.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr>
                            <th scope="col">Asset</th><th scope="col">Tenant</th><th scope="col">Period</th><th scope="col">Due</th>
                            <th scope="col" class="text-end">Amount</th><th scope="col" class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                        @foreach($notReceived as $p)
                            <tr>
                                <td>{{ $p->asset?->name ?? '—' }}</td>
                                <td>{{ $p->tenantName() ?? '—' }}</td>
                                <td>{{ $p->periodLabel() }}</td>
                                <td>{{ optional($p->due_date)->format('Y-m-d') }}</td>
                                <td class="text-end">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('payments.markPaid', $p) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i> Paid now</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
