<?php
/**
 * Danh sách VPS của khách hàng.
 *
 * @var array $instances
 * @var array $orders
 */
use App\Services\XvpsService;
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-gauge-high me-2 text-gold"></i>VPS của tôi</h1>
            <p class="page-sub mb-0">Quản lý toàn bộ máy chủ ảo đã thuê.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <form method="POST" action="<?= url('/services/vps/sync') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-gold btn-sm">
                    <i class="fas fa-rotate me-1"></i>Đồng bộ
                </button>
            </form>
            <a href="<?= url('/services/vps') ?>" class="btn btn-gold btn-sm">
                <i class="fas fa-plus me-1"></i>Thuê thêm
            </a>
        </div>
    </div>

    <div class="panel-card mb-4">
        <h6 class="mb-3"><i class="fas fa-server me-2"></i>Danh sách máy chủ</h6>

        <?php if (!$instances): ?>
            <div class="empty-state">
                <i class="fas fa-server fs-1 d-block mb-3"></i>
                <p class="mb-2">Bạn chưa có VPS nào.</p>
                <a href="<?= url('/services/vps') ?>" class="btn btn-gold btn-sm">
                    <i class="fas fa-plus me-1"></i>Thuê VPS ngay
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>VPS ID</th>
                            <th>Gói</th>
                            <th>IP</th>
                            <th>Cấu hình</th>
                            <th>Hạn</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instances as $v): ?>
                            <tr>
                                <td class="font-numeric"><?= e((string)$v['vps_id']) ?></td>
                                <td>
                                    <div class="fw-semibold small"><?= e((string)($v['product_name'] ?: '—')) ?></div>
                                    <div class="form-hint"><?= e((string)($v['os'] ?: '')) ?></div>
                                </td>
                                <td class="font-numeric"><?= e((string)($v['ip'] ?: '—')) ?></td>
                                <td class="font-numeric small">
                                    <?= (int)$v['cpu'] ?>C / <?= (int)$v['ram'] ?>M / <?= (int)$v['disk'] ?>G
                                </td>
                                <td class="font-numeric small"><?= (int)$v['day_left'] ?> ngày</td>
                                <td>
                                    <span class="badge-status st-<?= e(XvpsService::vpsStatusBadge((string)$v['vps_status'])) ?>">
                                        <?= e(XvpsService::vpsStatusLabel((string)$v['vps_status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('/services/vps/manage/' . rawurlencode((string)$v['vps_id'])) ?>"
                                       class="btn btn-outline-gold btn-sm">
                                        <i class="fas fa-gauge-high me-1"></i>Quản lý
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel-card">
        <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Lịch sử đơn</h6>

        <?php if (!$orders): ?>
            <p class="text-muted-2 small mb-0">Chưa có đơn nào.</p>
        <?php else: ?>
            <div class="aic-order-list">
                <?php foreach ($orders as $o): ?>
                    <a href="<?= url('/services/vps/order/' . (int)$o['id']) ?>" class="aic-order-item">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-server text-gold"></i>
                            <div class="min-w-0 flex-fill">
                                <div class="small fw-semibold text-truncate">
                                    <?= e((string)($o['product_name'] ?: 'VPS')) ?>
                                </div>
                                <div class="form-hint font-numeric">
                                    <?= $o['remote_order'] ? '#' . e((string)$o['remote_order']) : 'Chưa có mã' ?>
                                    · <?= e(date('d/m/Y H:i', strtotime((string)$o['created_at']))) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                <span class="badge-status st-<?= e(XvpsService::statusBadge((string)$o['status'])) ?>">
                                    <?= e(XvpsService::statusLabel((string)$o['status'])) ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
