{{-- Row actions for a rental payment: Yes / No / Amount… / Statement (upload) --}}
@php $stmtId = 'stmt-'.$p->id; @endphp
<div class="d-inline-flex gap-1 flex-wrap justify-content-end">
    @if(! $p->isPaid())
        <form method="POST" action="{{ route('payments.markPaid', $p) }}" class="d-inline" data-no-loading>@csrf
            <button class="btn btn-sm btn-success" title="Received as expected"><i class="bi bi-check2"></i><span class="d-none d-md-inline"> Yes</span></button>
        </form>
        @if($p->status === \App\Models\RentalPayment::STATUS_PENDING)
            <form method="POST" action="{{ route('payments.markNotReceived', $p) }}" class="d-inline" data-no-loading>@csrf
                <button class="btn btn-sm btn-outline-danger" title="Not received"><i class="bi bi-x"></i><span class="d-none d-md-inline"> No</span></button>
            </form>
        @endif
    @endif
    <button type="button" class="btn btn-sm btn-outline-secondary" title="Correct the amount"
            data-adjust
            data-url="{{ route('payments.adjust', $p) }}"
            data-label="{{ $p->asset?->name }} · {{ $p->periodLabel() }}"
            data-amount="{{ (float) $p->amount }}"
            data-currency="{{ $p->currency }}"
            data-paid="{{ $p->isPaid() ? '1' : '0' }}"
            data-notes="{{ $p->notes }}">
        <i class="bi bi-pencil"></i><span class="d-none d-md-inline"> Amount</span>
    </button>
    <form method="POST" action="{{ route('payments.statement', $p) }}" enctype="multipart/form-data" class="d-inline" id="{{ $stmtId }}">@csrf
        <input type="file" name="file" accept=".pdf,image/*" class="d-none" onchange="this.form.requestSubmit()">
        <button type="button" class="btn btn-sm btn-outline-primary" title="Upload the manager's statement and take the payout from it"
                onclick="document.querySelector('#{{ $stmtId }} input[type=file]').click()">
            <i class="bi bi-file-earmark-arrow-up"></i><span class="d-none d-md-inline"> Statement</span>
        </button>
    </form>
</div>
