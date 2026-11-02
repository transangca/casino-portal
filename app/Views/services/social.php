<?php /** @var array $platforms,$services,$orders, string $currentPlatform */ ?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-share-nodes me-2"></i>Dịch vụ mạng xã hội &amp; Buff</h1>
    <p class="page-sub">Tăng lượt thích, follow, view và tương tác cho Facebook, TikTok, Instagram, YouTube, Website.</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= url('/services/social') ?>" class="filter-chip <?= $currentPlatform === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($platforms as $code => $label): ?>
            <a href="<?= url('/services/social?platform=' . urlencode($code)) ?>" class="filter-chip <?= $currentPlatform === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-3">
                <?php foreach ($services as $s): ?>
                    <div class="col-md-6">
                        <div class="panel-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= e((string)$s['name']) ?></strong>
                                    <div class="small text-muted-2">
                                        <?= e($platforms[$s['platform']] ?? (string)$s['platform']) ?>
                                    </div>
                                </div>
                                <span class="text-gold fw-semibold"><?= money((float)$s['unit_price']) ?></span>
                            </div>
                            <p class="small text-muted-2 mb-3"><?= e((string)($s['description'] ?: '')) ?></p>

                            <form method="POST" action="<?= url('/services/social/order') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="platform" value="<?= e((string)$s['platform']) ?>">
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="url" name="target_url" class="form-control form-control-sm" placeholder="https://facebook.com/..." required>
                                    </div>
                                    <div class="col-4">
                                        <input type="number" name="quantity" class="form-control form-control-sm"
                                               min="<?= (int)$s['min_qty'] ?>" max="<?= (int)$s['max_qty'] ?>"
                                               value="<?= (int)$s['min_qty'] ?>" required>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <input type="number" name="note" class="form-control form-control-sm d-none" placeholder="Ghi chú">
                                    <button class="btn btn-gold btn-sm flex-grow-1">
                                        <i class="fas fa-bolt me-1"></i>Đặt dịch vụ
                                    </button>
                                </div>
                            </form>
                            <div class="small text-muted-2 mt-2">
                Tối thiểu <?= (int)$s['min_qty'] ?> · Tối đa <?= (int)$s['max_qty'] ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$services): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-share-nodes fs-1 d-block mb-3"></i>
                            <p class="mb-0">Hiện chưa có dịch vụ nào được bật. Admin sẽ cấu hình giá tại trang quản trị.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-receipt me-2 text-gold"></i>Đơn của tôi</h6>
                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Chưa có đơn dịch vụ nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Dịch vụ</th><th>SL</th><th class="text-end">Tiền</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><?= e((string)($o['service_name'] ?? '—')) ?></td>
                                        <td><?= (int)$o['quantity'] ?></td>
                                        <td class="text-end"><?= money((float)$o['amount']) ?></td>
                                        <td>
                                            <?php
                                            $cls = ['pending' => 'bg-warning', 'running' => 'bg-info', 'done' => 'bg-success', 'failed' => 'bg-danger', 'refunded' => 'bg-secondary'][$o['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $cls ?> bg-opacity-25"><?= e((string)$o['status']) ?></span>
                                        </td>
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
