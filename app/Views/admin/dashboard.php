<?php
/** @var array $stats,$recentDeposits,$recentWithdrawals,$topPlayers
 *  @var int $onlineSessions,$totalUsers,$newToday,$totalGames,$activePkgs */
$adminPage = 'dashboard';
?>
<h1 class="page-title">Dashboard</h1>
<p class="page-sub">Tổng quan hoạt động của hệ thống.</p>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label mb-1">Tổng số dư</div>
            <div class="stat-value"><?= money((float)$stats['total_balance']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label mb-1">Tổng nạp</div>
            <div class="stat-value"><?= money((float)$stats['total_deposit']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label mb-1">Tổng cược</div>
            <div class="stat-value"><?= money((float)$stats['total_bet']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label mb-1">Tổng thắng</div>
            <div class="stat-value"><?= money((float)$stats['total_win']) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="panel-card mb-0 h-100">
            <div class="game-provider">Người dùng</div>
            <div class="fs-4 fw-bold"><?= (int)$totalUsers ?></div>
            <div class="text-secondary small">Hôm này: +<?= (int)$newToday ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel-card mb-0 h-100">
            <div class="game-provider">Game đang có</div>
            <div class="fs-4 fw-bold"><?= (int)$totalGames ?></div>
            <div class="text-secondary small"><?= (int)$activePkgs ?> gói đang bán</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel-card mb-0 h-100">
            <div class="game-provider">Phiên chơi active</div>
            <div class="fs-4 fw-bold"><?= (int)$onlineSessions ?></div>
            <div class="text-secondary small">Trong 15 phút quá</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel-card mb-0 h-100">
            <div class="game-provider">Chờ xử lý</div>
            <div class="fs-4 fw-bold text-gold">
                <?= (int)$stats['pending_deposits'] ?> / <?= (int)$stats['pending_withdrawals'] ?>
            </div>
            <div class="text-secondary small">Nạp / Rút</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-money-bill-wave text-gold me-2"></i>Nạp tiền gần đây</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>User</th><th>Số tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentDeposits as $d): ?>
                        <tr>
                            <td><?= e($d['username']) ?></td>
                            <td><?= money((float)$d['amount']) ?></td>
                            <td><span class="badge-status st-<?= e($d['status']) ?>"><?= e(strtoupper($d['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-crown text-gold me-2"></i>Top người chơi</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>User</th><th class="text-end">Tổng nạp</th><th class="text-end">Số dư</th></tr></thead>
                    <tbody>
                    <?php foreach ($topPlayers as $u): ?>
                        <tr>
                            <td><?= e($u['username']) ?></td>
                            <td class="text-end"><?= money((float)$u['total_deposit']) ?></td>
                            <td class="text-end"><?= money((float)$u['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
