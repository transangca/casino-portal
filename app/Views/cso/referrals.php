<?php /** @var array $list,$account */ ?>
<div class="container py-4">
    <h1 class="page-title">Người giới thiệu</h1>
    <p class="page-sub">Danh sách người chơi được bạn giới thiệu.</p>

    <div class="panel-card">
        <?php if (!$list): ?>
            <div class="empty-state py-4"><i class="fas fa-users"></i>
                <p>Chưa có người giới thiệu nào.</p>
                <a href="<?= url('/cso') ?>" class="btn btn-outline-secondary btn-sm">Lấy link giới thiệu</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Username</th><th>Họ tên</th><th>Ngày đăng ký</th><th class="text-end">Tổng nạp</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r): ?>
                    <tr>
                        <td><?= e($r['username']) ?></td>
                        <td class="text-secondary"><?= e($r['full_name']) ?></td>
                        <td class="text-secondary"><?= date('d/m/Y', strtotime($r['joined_at'])) ?></td>
                        <td class="text-end"><?= money((float)$r['total_deposit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
