{{-- One modal per page; filled by the [data-adjust] buttons or by an imported statement --}}
<div class="modal fade" id="adjustPaymentModal" tabindex="-1" aria-labelledby="adjustPaymentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="adjustPaymentForm" class="modal-content">
            @csrf
            <input type="hidden" name="statement" id="adj_statement">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustPaymentTitle">Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3" id="adj_label"></div>

                <div id="adj_statement_box" class="border rounded p-2 mb-3 small" hidden>
                    <div class="fw-semibold mb-1"><i class="bi bi-file-earmark-text me-1"></i> From the statement <span id="adj_stmt_company" class="text-muted fw-normal"></span></div>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><td>Gross income</td><td class="text-end" id="adj_stmt_gross"></td></tr>
                        <tr><td>Management fee</td><td class="text-end" id="adj_stmt_fee"></td></tr>
                        <tr><td>Other expenses</td><td class="text-end" id="adj_stmt_exp"></td></tr>
                        <tr class="fw-semibold"><td>Payable to you</td><td class="text-end" id="adj_stmt_net"></td></tr>
                        </tbody>
                    </table>
                    <div id="adj_stmt_warnings" class="text-warning-emphasis mt-1"></div>
                </div>

                <div class="row g-2">
                    <div class="col-8">
                        <label class="form-label" for="adj_amount">Amount</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount" id="adj_amount" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="adj_currency">Currency</label>
                        <input class="form-control" name="currency" id="adj_currency" maxlength="3">
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="received" value="0">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="received" value="1" id="adj_received">
                            <label class="form-check-label" for="adj_received">Mark as received</label>
                        </div>
                    </div>
                    <div class="col-6" id="adj_paid_wrap">
                        <label class="form-label" for="adj_paid_date">Received on</label>
                        <input type="date" class="form-control" name="paid_date" id="adj_paid_date">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="adj_notes">Notes</label>
                        <textarea class="form-control" name="notes" id="adj_notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

@php $stmt = session('statement'); @endphp
<script>
(function () {
    const modalEl = document.getElementById('adjustPaymentModal');
    if (!modalEl) return;
    const form = document.getElementById('adjustPaymentForm');
    const $ = (id) => document.getElementById(id);
    const fmt = (n, c) => n == null ? '—' : (c ? c + ' ' : '') + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const today = new Date().toISOString().slice(0, 10);

    function open(opts) {
        form.action = opts.url;
        $('adj_label').textContent = opts.label || '';
        $('adj_amount').value = opts.amount ?? '';
        $('adj_currency').value = opts.currency || '';
        $('adj_notes').value = opts.notes || '';
        $('adj_received').checked = !!opts.received;
        $('adj_paid_date').value = opts.received ? today : '';
        $('adj_statement').value = opts.statement ? JSON.stringify(opts.statement) : '';
        const box = $('adj_statement_box');
        box.hidden = !opts.statement;
        if (opts.statement) {
            const s = opts.statement;
            $('adj_stmt_company').textContent = [s.management_company, s.period_start && s.period_end ? s.period_start + ' → ' + s.period_end : ''].filter(Boolean).join(' · ');
            $('adj_stmt_gross').textContent = fmt(s.gross_income, s.currency);
            $('adj_stmt_fee').textContent = fmt(s.management_fee, s.currency);
            $('adj_stmt_exp').textContent = fmt(s.expenses, s.currency);
            $('adj_stmt_net').textContent = fmt(s.net_payable, s.currency);
            $('adj_stmt_warnings').textContent = (s.warnings || []).join(' · ');
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.querySelectorAll('[data-adjust]').forEach((btn) => btn.addEventListener('click', () => open({
        url: btn.dataset.url, label: btn.dataset.label, amount: btn.dataset.amount, currency: btn.dataset.currency,
        notes: btn.dataset.notes, received: btn.dataset.paid === '1',
    })));

    @if($stmt)
        (function () {
            const s = @json($stmt['figures']);
            const btn = document.querySelector('[data-adjust][data-url$="/{{ (int) $stmt['payment_id'] }}/adjust"]');
            if (!btn) return;
            const notes = 'Statement' + (s.management_company ? ' from ' + s.management_company : '') +
                (s.period_start ? ' for ' + s.period_start + ' → ' + s.period_end : '') +
                ': gross ' + fmt(s.gross_income) + ', fee ' + fmt(s.management_fee) + ', expenses ' + fmt(s.expenses) + ', net ' + fmt(s.net_payable) + '.';
            open({ url: btn.dataset.url, label: btn.dataset.label, amount: s.net_payable ?? btn.dataset.amount,
                   currency: s.currency || btn.dataset.currency, notes: notes, received: true, statement: s });
        })();
    @endif
})();
</script>
