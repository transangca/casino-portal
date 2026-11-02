<?php /** @var array $balance,$history,$deposits,$withdrawals */ ?>
<div class="container py-4">
    <h1 class="page-title">Ví của tôi</h1>
    <p class="page-sub">Quản lý số dư, lịch sử giao dịch và yêu cầu nạp/rút tiền.</p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label mb-1">Số dư khả dụng</div>
                <div class="stat-value"><?= money($balance) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label mb-1">Tổng đã nạp</div>
                <div class="stat-value"><?= money(\App\Core\Wallet::totalDeposited((int)\App\Core\Auth::id())) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label mb-1">Tổng cược</div>
                <div class="stat-value">
                    <?= money((float)\App\Core\Database::fetchColumn('SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type = "bet"', [\App\Core\Auth::id()])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= url('/wallet/deposit') ?>" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Nạp tiền</a>
        <a href="<?= url('/wallet/withdraw') ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-up me-2"></i>Rút tiền</a>
        <a href="<?= url('/wallet/history') ?>" class="btn btn-outline-secondary"><i class="fas fa-history me-2"></i>Lịch sử</a>
        <a href="<?= url('/packages') ?>" class="btn btn-outline-secondary"><i class="fas fa-box-open me-2"></i>Mua gói chơi</a>
    </div>

    <div class="panel-card mb-4">
        <h3><i class="fas fa-receipt text-gold me-2"></i>Giao dịch gần đây</h3>
        <?php if (!$history): ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có giao dịch nào.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Thời gian</th><th>Loại</th><th>Mô tả</th>
                        <th class="text-end">Số tiền</th><th class="text-end">Số dư</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $t): ?>
                    <tr>
                        <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                        <td><span class="badge-status <?= $t['type'] === 'bet' ? 'st-processing' : 'st-approved' ?>"><?= e(strtoupper($t['type'])) ?></span></td>
                        <td class="text-secondary"><?= e($t['description']) ?></td>
                        <td class="text-end <?= (float)$t['amount'] >= 0 ? 'amount-pos' : 'amount-neg' ?>">
                            <?= (float)$t['amount'] >= 0 ? '+' : '' ?><?= money((float)$t['amount']) ?>
                        </td>
                        <td class="text-end"><?= money((float)$t['balance_after']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($deposits): ?>
    <div class="panel-card mb-4">
        <h3><i class="fas fa-money-bill-wave text-gold me-2"></i>Yêu cầu nạp tiền</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Mã GD</th><th>Số tiền</th><th>Thưởng</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach ($deposits as $d): ?>
                    <tr>
                        <td><code><?= e((string)($d['transaction_code'] ?? '—')) ?></code></td>
                        <td><?= money((float)$d['amount']) ?></td>
                        <td class="text-gold"><?= (float)$d['bonus_amount'] > 0 ? '+' . money((float)$d['bonus_amount']) : '-' ?></td>
                        <td class="text-secondary"><?= e(strtoupper((string)$d['method'])) ?></td>
                        <td><span class="badge-status st-<?= e($d['status']) ?>"><?= e(strtoupper((string)$d['status'])) ?></span></td>
                        <td class="text-secondary"><?= date('d/m/Y H:i', strtotime((string)$d['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($withdrawals): ?>
    <div class="panel-card mb-0">
        <h3><i class="fas fa-money-bill-transfer text-gold me-2"></i>Yêu cầu rút tiền</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Số tiền</th><th>Phí</th><th>Nhận</th><th>Ngân hàng</th><th>Số tài khoản</th><th>Trạng thái</th></tr></thead>
                <tbody>
                <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td><?= money((float)$w['amount']) ?></td>
                        <td class="amount-neg">-<?= money((float)$w['fee']) ?></td>
                        <td class="amount-pos"><?= money((float)$w['net_amount']) ?></td>
                        <td class="text-secondary"><?= e($w['bank_name']) ?></td>
                        <td class="text-secondary"><?= e($w['account_number']) ?></td>
                        <td><span class="badge-status st-<?= e($w['status']) ?>"><?= e(strtoupper($w['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
