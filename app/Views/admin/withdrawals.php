<?php /** @var array $rows */ $adminPage = 'withdrawals'; ?>
<h1 class="page-title">Quản lý rút tiền</h1>
<p class="page-sub">Xử lý các yêu cầu rút tiền.</p>

<div class="panel-card">
    <?php if (!$rows): ?>
        <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Không có yêu cầu nào.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>#</th><th>User</th><th>Số tiền</th><th>Phí</th><th>Nhận</th><th>Ngân hàng</th><th>Số TK</th><th>Chủ TK</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $w): ?>
                <tr>
                    <td class="text-secondary"><?= (int)$w['id'] ?></td>
                    <td><?= e($w['username']) ?></td>
                    <td><?= money((float)$w['amount']) ?></td>
                    <td class="amount-neg">-<?= money((float)$w['fee']) ?></td>
                    <td class="amount-pos"><?= money((float)$w['net_amount']) ?></td>
                    <td class="text-secondary"><?= e($w['bank_name']) ?></td>
                    <td class="text-secondary"><?= e($w['account_number']) ?></td>
                    <td class="text-secondary"><?= e($w['account_name']) ?></td>
                    <td><span class="badge-status st-<?= e($w['status']) ?>"><?= e(strtoupper($w['status'])) ?></span></td>
                    <td>
                        <?php if ($w['status'] === 'pending'): ?>
                            <div class="d-flex gap-1">
                                <form method="POST" action="<?= url('/admin/withdrawals/approve') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                    <button class="btn btn-sm btn-primary" data-confirm="Duyet rut tien? Tien se bi tru khoi vi.">Duyệt</button>
                                </form>
                                <form method="POST" action="<?= url('/admin/withdrawals/reject') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" data-confirm="Tu choi rut tien?">Từ chối</button>
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
