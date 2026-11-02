<?php
/**
 * Chuyển tiền nội bộ giữa các thành viên.
 *
 * @var float $balance
 * @var array $history
 * @var float $feeRate
 * @var float $min
 */
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-right-left text-gold me-2"></i>Chuyển tiền nội bộ</h1>
    <p class="page-sub">Số dư: <strong class="text-gold"><?= money($balance) ?></strong> · Tối thiểu <?= money($min) ?> · Phí <?= number_format($feeRate * 100, 2) ?>%</p>

    <div class="row g-4">
        <!-- Form -->
        <div class="col-lg-5">
            <div class="transfer-box">
                <form method="POST" action="<?= url('/wallet/transfer') ?>" id="transferForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="recipient">Người nhận (username hoặc email)</label>
                        <input type="text" class="form-control" id="recipient" name="recipient"
                               placeholder="VD: player01" required autocomplete="off">
                        <div id="recipientHints" class="mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="amount">Số tiền</label>
                        <div class="input-group">
                            <input type="number" class="form-control font-numeric" id="amount" name="amount"
                                   min="<?= (int)$min ?>" step="1" required>
                            <span class="input-group-text">đ</span>
                        </div>
                        <div class="quick-amounts mt-2">
                            <?php foreach ([50000, 100000, 200000, 500000, 1000000, 5000000] as $q): ?>
                                <button type="button" data-amount="<?= $q ?>"><?= number_format($q) ?>đ</button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="note">Ghi chú (không bắt buộc)</label>
                        <input type="text" class="form-control" id="note" name="note" maxlength="255"
                               placeholder="VD: Mượn tiền cuối tuần">
                    </div>

                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calculator me-2"></i>Người nhận nhận</span>
                        <strong id="netAmount" class="font-numeric">—</strong>
                    </div>

                    <button class="btn btn-gold w-100 py-2 fw-bold"
                            data-confirm="Xác nhận chuyển tiền cho người nhận đã chọn?">
                        <i class="fas fa-paper-plane me-2"></i>Chuyển tiền
                    </button>
                </form>
            </div>
        </div>

        <!-- Lịch sử -->
        <div class="col-lg-7">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-history text-gold me-2"></i>Lịch sử chuyển tiền</h3>

                <?php if (!$history): ?>
                    <div class="empty-state py-4"><p class="mb-0">Chưa có giao dịch chuyển tiền nào.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr><th>Người nhận / Người gửi</th><th>Số tiền</th><th>Ghi chú</th><th>Thời gian</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $t): ?>
                                <?php $out = (int)$t['from_user_id'] === (int)\App\Core\Auth::id(); ?>
                                <tr>
                                    <td>
                                        <i class="fas <?= $out ? 'fa-arrow-up text-danger' : 'fa-arrow-down text-success' ?> me-1"></i>
                                        <?= e($out ? $t['to_username'] : $t['from_username']) ?>
                                    </td>
                                    <td class="font-numeric fw-semibold <?= $out ? 'amount-neg' : 'amount-pos' ?>">
                                        <?= ($out ? '-' : '+') . money((float)$t['amount']) ?>
                                    </td>
                                    <td class="small text-muted-2"><?= e($t['note'] ?: '—') ?></td>
                                    <td class="small"><?= e(date('d/m/Y H:i', strtotime($t['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var feeRate = <?= json_encode($feeRate) ?>;
    var amountEl = document.getElementById('amount');
    var netEl = document.getElementById('netAmount');
    var recEl = document.getElementById('recipient');

    function fmt(n) {
        return Math.round(Number(n) || 0).toLocaleString('vi-VN');
    }
    function updateNet() {
        var v = Number(amountEl.value) || 0;
        netEl.textContent = v > 0 ? fmt(v * (1 - feeRate)) + ' đ' : '—';
    }
    amountEl.addEventListener('input', updateNet);
    Array.prototype.forEach.call(document.querySelectorAll('[data-amount]'), function (b) {
        b.addEventListener('click', function () {
            amountEl.value = b.getAttribute('data-amount');
            updateNet();
        });
    });
    updateNet();

    // Gợi ý người nhận
    var hints = document.getElementById('recipientHints');
    var timer = null;
    recEl.addEventListener('input', function () {
        clearTimeout(timer);
        var q = recEl.value.trim();
        if (q.length < 2) { hints.innerHTML = ''; return; }
        timer = setTimeout(function () {
            fetch(APP.baseUrl + 'wallet/transfer/lookup?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res || !res.success || !res.data.length) { hints.innerHTML = ''; return; }
                var html = '';
                res.data.forEach(function (u) {
                    html += '<div class="small text-muted-2">' +
                        '<i class="fas fa-user me-1"></i>' + u.username +
                        (u.full_name ? ' (' + u.full_name + ')' : '') +
                        (u.verified ? ' <i class="fas fa-check-circle text-info"></i>' : '') +
                        '</div>';
                });
                hints.innerHTML = html;
            }).catch(function () {});
        }, 260);
    });
})();
</script>
