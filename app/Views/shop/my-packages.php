<?php /** @var array $list,$active */ ?>
<div class="container py-4">
    <h1 class="page-title">Gói của tôi</h1>
    <p class="page-sub">Lịch sử các gói bạn đã mua hoặc đã nhận.</p>

    <div class="panel-card">
        <?php if (!$list): ?>
            <div class="empty-state py-4"><i class="fas fa-box-open"></i>
                <p>Bạn chưa có gói nào.</p>
                <a href="<?= url('/packages') ?>" class="btn btn-primary btn-sm">Xem gói chơi</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Gói</th><th>Thời lượng</th><th>Bắt đầu</th><th>Hết hạn</th><th class="text-end">Giá</th><th>Trạng thái</th></tr></thead>
                <tbody>
                <?php foreach ($list as $p): ?>
                    <tr>
                        <td><?= e($p['package_name']) ?></td>
                        <td class="text-secondary"><?= (int)$p['duration_minutes'] ?> phút</td>
                        <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($p['started_at'])) ?></td>
                        <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($p['expires_at'])) ?></td>
                        <td class="text-end"><?= (float)$p['price'] > 0 ? money((float)$p['price']) : 'Miễn phí' ?></td>
                        <td><span class="badge-status st-<?= e($p['status']) ?>"><?= e(strtoupper($p['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
