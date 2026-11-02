<?php /** @var array $rows */ $adminPage = 'deposits'; ?>
<h1 class="page-title">Quản lý nạp tiền</h1>
<p class="page-sub">Duyệt các yêu cầu nạp tiền từ người chơi.</p>

<div class="d-flex gap-2 mb-3">
    <a href="<?= url('/admin/deposits') ?>" class="filter-chip <?= ($status ?? '') === '' ? 'active' : '' ?>">Tất cả</a>
    <a href="<?= url('/admin/deposits?status=pending') ?>" class="filter-chip <?= ($status ?? '') === 'pending' ? 'active' : '' ?>">Chờ duyệt</a>
    <a href="<?= url('/admin/deposits?status=approved') ?>" class="filter-chip <?= ($status ?? '') === 'approved' ? 'active' : '' ?>">Đã duyệt</a>
    <a href="<?= url('/admin/deposits?status=rejected') ?>" class="filter-chip <?= ($status ?? '') === 'rejected' ? 'active' : '' ?>">Từ chối</a>
</div>

<div class="panel-card">
    <?php if (!$rows): ?>
        <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Không có yêu cầu nào.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>#</th><th>User</th><th>Mã GD</th><th>Số tiền</th><th>Thưởng</th><th>PP</th><th>Trạng thái</th><th>Thời gian</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $d): ?>
                <tr>
                    <td class="text-secondary"><?= (int)$d['id'] ?></td>
                    <td><?= e($d['username']) ?></td>
                    <td><code><?= e($d['transaction_code']) ?></code></td>
                    <td><?= money((float)$d['amount']) ?></td>
                    <td class="text-gold"><?= (float)$d['bonus_amount'] > 0 ? '+' . money((float)$d['bonus_amount']) : '-' ?></td>
                    <td class="text-secondary"><?= e(strtoupper($d['method'])) ?></td>
                    <td><span class="badge-status st-<?= e($d['status']) ?>"><?= e(strtoupper($d['status'])) ?></span></td>
                    <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                    <td>
                        <?php if ($d['status'] === 'pending'): ?>
                            <div class="d-flex gap-1">
                                <form method="POST" action="<?= url('/admin/deposits/approve') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                    <button class="btn btn-sm btn-primary" data-confirm="Duyet naptien nay?">Duyệt</button>
                                </form>
                                <form method="POST" action="<?= url('/admin/deposits/reject') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" data-confirm="Tu choi naptien nay?">Từ chối</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-secondary small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
