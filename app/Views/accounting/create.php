<?php /** @var array $types,$accounts */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Lập phiếu kế toán</h1>
    </div>
</div>

<div class="p-3 p-lg-4">
    <form method="POST" action="<?= url('/services/accounting/store') ?>" class="panel-card">
        <?= csrf_field() ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Loại phiếu</label>
                <select name="doc_type" class="form-select form-select-sm">
                    <?php foreach ($types as $code => $label): ?>
                        <option value="<?= e($code) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ngày</label>
                <input type="date" name="doc_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Đối tượng</label>
                <input type="text" name="partner" class="form-control form-control-sm" placeholder="Khách hàng / nhà cung cấp">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" id="accLines">
                <thead>
                    <tr>
                        <th style="width:36%">Tài khoản</th>
                        <th>Nội dung</th>
                        <th style="width:140px">Nợ</th>
                        <th style="width:140px">Có</th>
                        <th style="width:44px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr>
                            <td>
                                <select name="lines[<?= $i ?>][account_code]" class="form-select form-select-sm">
                                    <option value="">-- Chọn tài khoản --</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= e((string)$a['code']) ?>"><?= e((string)$a['code']) ?> · <?= e((string)$a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="lines[<?= $i ?>][note]" class="form-control form-control-sm"></td>
                            <td><input type="number" step="0.01" min="0" name="lines[<?= $i ?>][debit]" class="form-control form-control-sm text-end" value="0"></td>
                            <td><input type="number" step="0.01" min="0" name="lines[<?= $i ?>][credit]" class="form-control form-control-sm text-end" value="0"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger acc-remove-row" title="Xoá dòng">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="2" class="text-end">Tổng</td>
                        <td class="text-end" id="accTotalDebit">0 đ</td>
                        <td class="text-end" id="accTotalCredit">0 đ</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-gold" id="accAddRow">
                <i class="fas fa-plus me-1"></i>Thêm dòng
            </button>
            <span class="small text-muted-2 ms-auto" id="accBalanceHint">Chênh lệch: 0 đ</span>
        </div>

        <div class="mt-3">
            <label class="form-label">Diễn giải</label>
            <textarea name="note" rows="2" class="form-control form-control-sm"></textarea>
        </div>

        <div class="text-end mt-3">
            <button class="btn btn-gold px-4"><i class="fas fa-save me-2"></i>Lưu phiếu</button>
        </div>
    </form>
</div>

<script>
(function () {
    const tbody = document.querySelector('#accLines tbody');
    const hint  = document.getElementById('accBalanceHint');
    const fmt   = (n) => new Intl.NumberFormat('vi-VN').format(n) + ' đ';

    function recalc() {
        let d = 0, c = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            d += parseFloat(tr.querySelector('[name$="[debit]"]')?.value || 0) || 0;
            c += parseFloat(tr.querySelector('[name$="[credit]"]')?.value || 0) || 0;
        });
        document.getElementById('accTotalDebit').textContent  = fmt(d);
        document.getElementById('accTotalCredit').textContent = fmt(c);
        const diff = d - c;
        hint.textContent = 'Chênh lệch: ' + fmt(diff);
        hint.classList.toggle('text-danger', Math.abs(diff) > 1);
        hint.classList.toggle('text-muted-2', Math.abs(diff) <= 1);
    }

    tbody.addEventListener('input', recalc);

    document.getElementById('accAddRow').addEventListener('click', () => {
        const last = tbody.querySelector('tr:last-child');
        const clone = last.cloneNode(true);
        clone.querySelectorAll('input').forEach((i) => {
            i.value = i.name.includes('[debit]') || i.name.includes('[credit]') ? '0' : '';
        });
        const idx = tbody.querySelectorAll('tr').length;
        clone.querySelectorAll('input,select').forEach((el) => {
            el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
        });
        tbody.appendChild(clone);
        recalc();
    });

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.acc-remove-row');
        if (!btn) return;
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 1) {
            btn.closest('tr').remove();
            recalc();
        }
    });

    recalc();
})();
</script>
