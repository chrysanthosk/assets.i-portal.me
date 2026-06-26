@extends('layouts.app')

@section('content')
<div class="row g-3">

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Expenses</h5>
                    <small class="text-muted">Maintenance, tax, insurance and other property costs</small>
                </div>
                <form method="GET" action="{{ route('expenses.index') }}" class="d-flex gap-2 align-items-center ms-auto">
                    <select name="asset_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 160px;">
                        <option value="">All assets</option>
                        @foreach($assets as $a)
                            <option value="{{ $a->id }}" @selected((int) $assetId === $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 150px;">
                        <option value="">All categories</option>
                        @foreach($categories as $c)
                            <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="year" value="{{ $year }}" placeholder="Year"
                           class="form-control form-control-sm" style="width: 90px;" onchange="this.form.submit()">
                </form>
            </div>

            <div class="card-body">

                {{-- Totals --}}
                <div class="mb-3">
                    <span class="text-muted small me-2">Total (filtered):</span>
                    @forelse($totalsByCurrency as $row)
                        <span class="badge text-bg-secondary me-1">{{ $row->currency }} {{ number_format((float) $row->total, 2) }}</span>
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                </div>

                {{-- Add --}}
                <form method="POST" action="{{ route('expenses.store') }}" class="row g-2 mb-4">
                    @csrf
                    <div class="col-12"><h6 class="mb-0">Record an expense</h6><hr class="mt-1"></div>

                    <div class="col-md-4">
                        <label class="form-label">Asset *</label>
                        <select name="asset_id" class="form-select @error('asset_id') is-invalid @enderror" required>
                            <option value="">— Select asset —</option>
                            @foreach($assets as $a)
                                <option value="{{ $a->id }}" @selected((string) old('asset_id') === (string) $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Date *</label>
                        <input type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}"
                               class="form-control @error('spent_on') is-invalid @enderror" required>
                        @error('spent_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                            @foreach($categories as $c)
                                <option value="{{ $c }}" @selected(old('category') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

                    <div class="col-md-6">
                        <label class="form-label">Vendor</label>
                        <input type="text" name="vendor" value="{{ old('vendor') }}"
                               class="form-control @error('vendor') is-invalid @enderror">
                        @error('vendor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" value="{{ old('description') }}"
                               class="form-control @error('description') is-invalid @enderror">
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Record Expense</button>
                    </div>
                </form>

                {{-- List --}}
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Asset</th>
                            <th scope="col">Category</th>
                            <th scope="col">Vendor</th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($expenses as $e)
                            <tr>
                                <td>{{ optional($e->spent_on)->format('Y-m-d') }}</td>
                                <td>{{ $e->asset?->name ?? '—' }}</td>
                                <td>{{ $e->category }}</td>
                                <td>{{ $e->vendor ?: '—' }}</td>
                                <td class="text-end">{{ $e->currency }} {{ number_format((float) $e->amount, 2) }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('expenses.destroy', $e) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this expense?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete expense"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No expenses recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <small class="text-muted">{{ $expenses->total() }} total</small>
                    {{ $expenses->links() }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
