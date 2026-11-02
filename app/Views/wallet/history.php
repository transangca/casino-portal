<?php /** @var array $rounds,$transactions */ ?>
<div class="container py-4">
    <h1 class="page-title">Lịch sử</h1>
    <p class="page-sub">Toàn bộ lịch sử cược và giao dịch của bạn.</p>

    <div class="panel-card">
        <h3><i class="fas fa-dice text-gold me-2"></i>Vòng cược gần đây</h3>
        <?php if (!$rounds): ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có vòng cược nào.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Game</th><th>Cược</th><th>Thắng</th><th class="text-end">Số dư</th><th>Thời gian</th></tr></thead>
                <tbody>
                <?php foreach ($rounds as $r): ?>
                    <tr>
                        <td class="text-secondary"><?= e($r['game_name']) ?></td>
                        <td class="amount-neg"><?= (float)$r['bet'] > 0 ? '-' . money((float)$r['bet']) : '-' ?></td>
                        <td class="amount-pos"><?= (float)$r['win'] > 0 ? '+' . money((float)$r['win']) : '-' ?></td>
                        <td class="text-end"><?= money((float)$r['balance_after']) ?></td>
                        <td class="text-secondary"><?= date('d/m/Y H:i:s', strtotime($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel-card">
        <h3><i class="fas fa-receipt text-gold me-2"></i>Giao dịch</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Thời gian</th><th>Loại</th><th>Mô tả</th><th class="text-end">Số tiền</th><th class="text-end">Số dư</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                        <td><span class="badge-status st-<?= $t['type'] === 'bet' ? 'processing' : 'approved' ?>"><?= e(strtoupper($t['type'])) ?></span></td>
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
    </div>
</div>
