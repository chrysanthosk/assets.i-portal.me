{{--
    Payment schedule fields for an agreement form. Expects $rental (nullable).
    Posts: payment_schedule, paid_in_arrears, amount, installments[n][month|day|amount|label]
--}}
@php
    $schedule = old('payment_schedule', $rental?->payment_schedule ?? 'monthly');
    $arrears = (int) old('paid_in_arrears', $rental?->paid_in_arrears ? 1 : 0) === 1;
    $rows = old('installments', $rental?->installmentList() ?? []);
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $uid = 'sched'.($rental?->id ?? 'new');
@endphp

<div class="col-md-4">
    <label class="form-label" for="{{ $uid }}_type">How is it paid?</label>
    <select id="{{ $uid }}_type" name="payment_schedule" class="form-select" data-schedule-type="{{ $uid }}">
        <option value="monthly" @selected($schedule === 'monthly')>Monthly rent</option>
        <option value="installments" @selected($schedule === 'installments')>Fixed instalments each year</option>
    </select>
</div>

<div class="col-md-4" data-schedule-monthly="{{ $uid }}">
    <label class="form-label" for="{{ $uid }}_amount">Monthly amount</label>
    <input id="{{ $uid }}_amount" type="number" step="0.01" min="0" name="amount" class="form-control @error('amount') is-invalid @enderror"
           value="{{ old('amount', $rental?->amount ?? 0) }}">
    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="col-md-4 d-flex align-items-end" data-schedule-monthly="{{ $uid }}">
    <div class="form-check form-switch mb-2">
        <input type="hidden" name="paid_in_arrears" value="0">
        <input class="form-check-input" type="checkbox" role="switch" id="{{ $uid }}_arrears" name="paid_in_arrears" value="1" @checked($arrears)>
        <label class="form-check-label" for="{{ $uid }}_arrears">Paid the month after <span class="text-muted small">(e.g. a manager pays August in September)</span></label>
    </div>
</div>

<div class="col-12" data-schedule-installments="{{ $uid }}" @if($schedule !== 'installments') hidden @endif>
    <div class="border rounded p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Instalments (repeat every year)</div>
                <div class="form-text">Day and month of each payment, e.g. an annual guarantee split 15 % on 15 Apr, 15 % on 31 May … Amounts excluding VAT. Use 0 for variable items such as year-end commission.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-installment="{{ $uid }}"><i class="bi bi-plus-lg"></i> Add row</button>
        </div>
        <table class="table table-sm align-middle mb-1" id="{{ $uid }}_table">
            <thead><tr><th style="width:110px">Day</th><th style="width:140px">Month</th><th style="width:170px">Amount</th><th>Label</th><th style="width:40px"></th></tr></thead>
            <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td><input type="number" min="1" max="31" name="installments[{{ $i }}][day]" class="form-control form-control-sm" value="{{ $r['day'] ?? '' }}" required></td>
                    <td><select name="installments[{{ $i }}][month]" class="form-select form-select-sm" required>
                        @foreach($months as $mi => $mn)<option value="{{ $mi + 1 }}" @selected((int) ($r['month'] ?? 0) === $mi + 1)>{{ $mn }}</option>@endforeach
                    </select></td>
                    <td><input type="number" step="0.01" min="0" name="installments[{{ $i }}][amount]" class="form-control form-control-sm" value="{{ $r['amount'] ?? '' }}" required></td>
                    <td><input name="installments[{{ $i }}][label]" class="form-control form-control-sm" value="{{ $r['label'] ?? '' }}" placeholder="e.g. 15 % of guarantee"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-installment aria-label="Remove"><i class="bi bi-x"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="small text-muted">Total per year: <strong data-installment-total="{{ $uid }}">0.00</strong></div>
        @error('installments') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
</div>

<script>
(function () {
    const uid = @json($uid);
    const type = document.querySelector('[data-schedule-type="' + uid + '"]');
    if (!type) return;
    const months = @json($months);
    const table = document.getElementById(uid + '_table').querySelector('tbody');
    const total = document.querySelector('[data-installment-total="' + uid + '"]');
    const amountInput = document.getElementById(uid + '_amount');

    function sync() {
        const inst = type.value === 'installments';
        document.querySelectorAll('[data-schedule-monthly="' + uid + '"]').forEach((el) => { el.hidden = inst; });
        document.querySelectorAll('[data-schedule-installments="' + uid + '"]').forEach((el) => { el.hidden = !inst; });
        // the monthly amount is not required when instalments drive the schedule
        if (amountInput) amountInput.required = !inst;
        table.querySelectorAll('input, select').forEach((el) => { el.required = inst; });
        recalc();
    }
    function recalc() {
        let sum = 0;
        table.querySelectorAll('input[name$="[amount]"]').forEach((el) => { sum += parseFloat(el.value || 0); });
        if (total) total.textContent = sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function addRow(day = '', month = 1, amount = '', label = '') {
        const i = table.querySelectorAll('tr').length;
        const opts = months.map((m, k) => '<option value="' + (k + 1) + '"' + ((k + 1) === month ? ' selected' : '') + '>' + m + '</option>').join('');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="number" min="1" max="31" name="installments[' + i + '][day]" class="form-control form-control-sm" value="' + day + '" required></td>'
            + '<td><select name="installments[' + i + '][month]" class="form-select form-select-sm" required>' + opts + '</select></td>'
            + '<td><input type="number" step="0.01" min="0" name="installments[' + i + '][amount]" class="form-control form-control-sm" value="' + amount + '" required></td>'
            + '<td><input name="installments[' + i + '][label]" class="form-control form-control-sm" value="' + label + '"></td>'
            + '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-installment aria-label="Remove"><i class="bi bi-x"></i></button></td>';
        table.appendChild(tr);
        sync();
    }
    document.querySelector('[data-add-installment="' + uid + '"]').addEventListener('click', () => addRow());
    table.addEventListener('click', (e) => { const b = e.target.closest('[data-remove-installment]'); if (b) { b.closest('tr').remove(); recalc(); } });
    table.addEventListener('input', recalc);
    type.addEventListener('change', sync);
    sync();
})();
</script>
