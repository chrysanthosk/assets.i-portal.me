@extends('layouts.app')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Base Currency</h5></div>
            <div class="card-body">
                <p class="text-muted small">Reports and consolidated totals are expressed in this currency.</p>
                <form method="POST" action="{{ route('settings.currencies.updateBase') }}" class="row g-2">
                    @csrf
                    <div class="col-8">
                        <input type="text" name="base_currency" value="{{ old('base_currency', $baseCurrency) }}"
                               maxlength="3" class="form-control text-uppercase @error('base_currency') is-invalid @enderror" required>
                        @error('base_currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-4 d-grid">
                        <button class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">FX Rates</h5></div>
            <div class="card-body">
                <p class="text-muted small">
                    1 unit of the currency equals this many <strong>{{ $baseCurrency }}</strong>.
                    (e.g. USD → 0.92 means 1 USD = 0.92 {{ $baseCurrency }}.)
                </p>

                <form method="POST" action="{{ route('settings.currencies.storeRate') }}" class="row g-2 mb-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" value="{{ old('currency') }}" maxlength="3"
                               class="form-control text-uppercase @error('currency') is-invalid @enderror" placeholder="USD" required>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Rate to {{ $baseCurrency }}</label>
                        <input type="number" step="0.00000001" name="rate_to_base" value="{{ old('rate_to_base') }}"
                               class="form-control @error('rate_to_base') is-invalid @enderror" required>
                        @error('rate_to_base') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 d-grid align-self-end">
                        <button class="btn btn-primary">Save Rate</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Currency</th>
                            <th scope="col" class="text-end">Rate → {{ $baseCurrency }}</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rates as $rate)
                            <tr>
                                <td class="fw-semibold">{{ $rate->currency }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $rate->rate_to_base, 8), '0'), '.') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('settings.currencies.destroyRate', $rate) }}" class="d-inline"
                                          onsubmit="return confirm('Delete rate for {{ $rate->currency }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete rate"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No rates yet. The base currency is always 1:1.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
