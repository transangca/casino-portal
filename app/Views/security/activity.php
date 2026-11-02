<?php
/**
 * Lịch sử hoạt động: thuê API key, phiên chơi, đăng nhập, giao dịch.
 *
 * @var array $logs
 * @var array $keys
 */
$iconMap = [
    'apikey' => ['fa-key', 'text-gold'],
    'api'    => ['fa-code', 'text-info'],
    'game'   => ['fa-gamepad', 'text-success'],
    'wallet' => ['fa-wallet', 'text-success'],
    'auth'   => ['fa-shield-halved', 'text-warning'],
    'admin'  => ['fa-user-gear', 'text-danger'],
    'system' => ['fa-cog', 'text-muted-2'],
];
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-list-check text-gold me-2"></i>Lịch sử hoạt động</h1>
    <p class="page-sub">Theo dõi các hoạt động của tài khoản: thuê API key, phiên chơi game, giao dịch và đăng nhập.</p>

    <div class="row g-4">
        <!-- API key -->
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-key text-gold me-2"></i>API key đã thuê</h3>
                <?php if (!$keys): ?>
                    <p class="text-muted-2 small mb-3">Bạn chưa thuê API key nào.</p>
                    <a href="<?= url('/api-keys') ?>" class="btn btn-sm btn-outline-gold">
                        <i class="fas fa-plus me-1"></i>Thuê API key
                    </a>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr><th>Tên</th><th>Trạng thái</th><th>Hết hạn</th><th>Đã dùng</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($keys as $k): ?>
                                <tr>
                                    <td>
                                        <?= e($k['name']) ?>
                                        <div class="form-hint"><?= e($k['key_prefix']) ?>••••</div>
                                    </td>
                                    <td>
                                        <span class="badge-status st-<?= $k['status'] === 'active' ? 'active' : 'rejected' ?>">
                                            <?= e(ucfirst($k['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= e($k['expires_at'] ? date('d/m/Y', strtotime($k['expires_at'])) : '—') ?></td>
                                    <td class="small font-numeric">
                                        <?= number_format((int)$k['total_requests']) ?>
                                        <span class="text-muted-2">/ <?= number_format((int)$k['quota_limit']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nhật ký -->
        <div class="col-lg-7">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-clock-rotate-left text-gold me-2"></i>Nhật ký hoạt động</h3>

                <?php if (!$logs): ?>
                    <div class="empty-state py-4"><p class="mb-0">Chưa có hoạt động nào được ghi nhận.</p></div>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <?php [$ic, $cls] = $iconMap[$l['scope']] ?? ['fa-circle', 'text-muted-2']; ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $cls ?>"><i class="fas <?= e($ic) ?>"></i></div>
                            <div class="a-body">
                                <div class="a-title"><?= e(ucwords(str_replace('_', ' ', (string)$l['action']))) ?></div>
                                <?php if (!empty($l['subject'])): ?>
                                    <div class="form-hint"><?= e((string)$l['subject']) ?></div>
                                <?php endif; ?>
                                <div class="a-time">
                                    <i class="fas fa-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?>
                                    <?php if (!empty($l['ip'])): ?>
                                        · <?= e($l['ip']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
