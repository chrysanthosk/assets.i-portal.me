<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rent confirmation — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width: 560px;">
    @php
        $received = $answer === 'received';
        $amount = $payment->currency.' '.number_format((float) $payment->amount, 2);
        $who = $payment->tenantName();
    @endphp

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3"><i class="bi bi-house-door me-1"></i> {{ $payment->asset?->name ?? 'Property' }}</h4>

            <dl class="row mb-4 small">
                <dt class="col-5 text-muted">Rent for</dt><dd class="col-7">{{ $payment->periodLabel() }}</dd>
                <dt class="col-5 text-muted">Amount</dt><dd class="col-7 fw-semibold">{{ $amount }}</dd>
                @if($who)<dt class="col-5 text-muted">Tenant</dt><dd class="col-7">{{ $who }}</dd>@endif
                <dt class="col-5 text-muted">Due date</dt><dd class="col-7">{{ optional($payment->due_date)->format('d M Y') }}</dd>
            </dl>

            @if(! empty($done))
                @if($payment->isPaid())
                    <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> Recorded as <strong>received</strong> on {{ optional($payment->paid_date)->format('d M Y') }}. Thanks!</div>
                @else
                    <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Recorded as <strong>not received</strong>. It now shows under overdue payments.</div>
                @endif
            @elseif($alreadyAnswered)
                <div class="alert alert-info mb-0">
                    This payment was already answered: it is marked
                    <strong>{{ $payment->isPaid() ? 'received' : 'not received' }}</strong>.
                    Change it from the Payments page in the portal if that is wrong.
                </div>
            @else
                <p class="mb-3">You are about to record this rent as
                    <strong class="{{ $received ? 'text-success' : 'text-danger' }}">{{ $received ? 'received' : 'not received' }}</strong>.
                </p>
                <form method="POST" action="{{ url()->full() }}">
                    @csrf
                    <button class="btn btn-lg w-100 {{ $received ? 'btn-success' : 'btn-danger' }}">
                        <i class="bi {{ $received ? 'bi-check2-circle' : 'bi-x-circle' }} me-1"></i>
                        {{ $received ? 'Yes, I received '.$amount : 'No, it has not arrived' }}
                    </button>
                </form>
            @endif
        </div>
    </div>
    <p class="text-center text-muted small mt-3">{{ config('app.name') }}</p>
</div>
</body>
</html>
