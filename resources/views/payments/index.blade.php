@extends('layouts.app')

@section('content')
<div class="row g-3">

    {{-- Arrears summary --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-2">Outstanding (pending)</h6>
                @forelse($outstandingByCurrency as $row)
                    <div class="fs-5 fw-semibold">{{ $row->currency }} {{ number_format((float) $row->total, 2) }}</div>
                @empty
                    <div class="text-muted">Nothing outstanding 🎉</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 {{ $overdueCount ? 'border-danger' : '' }}">
            <div class="card-body">
                <h6 class="text-muted mb-2">Overdue payments</h6>
                <div class="fs-5 fw-semibold {{ $overdueCount ? 'text-danger' : '' }}">{{ $overdueCount }}</div>
                @if($overdueCount)
                    <a href="{{ route('payments.index', ['status' => 'overdue']) }}" class="small">View overdue</a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Rental Payments</h5>
                <form method="GET" action="{{ route('payments.index') }}" class="d-flex gap-2 align-items-center ms-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 160px;">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="overdue" @selected($status === 'overdue')>Overdue</option>
                        <option value="paid" @selected($status === 'paid')>Paid</option>
                    </select>
                </form>
            </div>

            <div class="card-body">

                {{-- Record a payment --}}
                <form method="POST" action="{{ route('payments.store') }}" class="row g-2 mb-4">
                    @csrf
                    <div class="col-12"><h6 class="mb-0">Record / schedule a payment</h6><hr class="mt-1"></div>

                    <div class="col-md-5">
                        <label class="form-label">Agreement *</label>
                        <select name="asset_rental_id" class="form-select @error('asset_rental_id') is-invalid @enderror" required>
                            <option value="">— Select agreement —</option>
                            @foreach($rentals as $r)
                                <option value="{{ $r->id }}" @selected((string) old('asset_rental_id') === (string) $r->id)>
                                    {{ $r->asset?->name ?? 'Asset #'.$r->asset_id }}
                                    — {{ $r->tenant?->name ?? $r->tenant_name ?? 'No tenant' }}
                                    ({{ $r->currency }} {{ number_format((float) $r->amount, 0) }})
                                </option>
                            @endforeach
                        </select>
                        @error('asset_rental_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Due date *</label>
                        <input type="date" name="due_date" value="{{ old('due_date', now()->toDateString()) }}"
                               class="form-control @error('due_date') is-invalid @enderror" required>
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" name="amount" value="{{ old('amount') }}"
                               class="form-control @error('amount') is-invalid @enderror" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Cur.</label>
                        <input type="text" name="currency" value="{{ old('currency', 'EUR') }}" maxlength="3"
                               class="form-control @error('currency') is-invalid @enderror" required>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Paid date</label>
                        <input type="date" name="paid_date" value="{{ old('paid_date') }}"
                               class="form-control @error('paid_date') is-invalid @enderror">
                        <div class="form-text">Leave blank if not yet paid.</div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Record Payment</button>
                    </div>
                </form>

                {{-- List --}}
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Asset</th>
                            <th scope="col">Tenant</th>
                            <th scope="col">Due</th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($payments as $p)
                            @php $overdue = $p->status === 'pending' && $p->due_date && $p->due_date->isPast(); @endphp
                            <tr class="{{ $overdue ? 'table-danger' : '' }}">
                                <td>{{ $p->asset?->name ?? '—' }}</td>
                                <td>{{ $p->rental?->tenant?->name ?? $p->rental?->tenant_name ?? '—' }}</td>
                                <td>{{ optional($p->due_date)->format('Y-m-d') }}</td>
                                <td class="text-end">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td>
                                    @if($p->status === 'paid')
                                        <span class="badge text-bg-success">Paid</span>
                                    @elseif($overdue)
                                        <span class="badge text-bg-danger">Overdue</span>
                                    @else
                                        <span class="badge text-bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if($p->status !== 'paid')
                                        <form method="POST" action="{{ route('payments.markPaid', $p) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success" aria-label="Mark paid"><i class="bi bi-check2"></i> Paid</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('payments.destroy', $p) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this payment record?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete payment"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <small class="text-muted">{{ $payments->total() }} total</small>
                    {{ $payments->links() }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
