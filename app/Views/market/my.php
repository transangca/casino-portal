<?php
/**
 * Sàn của tôi: tin đăng + đơn hàng đã mua.
 *
 * @var array $items
 * @var array $orders
 * @var array $cats
 */
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-list-check text-gold me-2"></i><?= t('market.my_items') ?></h1>
    <p class="page-sub">Quản lý tin đăng bán và theo dõi các đơn hàng của bạn.</p>

    <div class="row g-4">
        <!-- Tin đăng -->
        <div class="col-lg-6">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-tags text-gold me-2"></i>Tin đăng của tôi</h3>

                <?php if (!$items): ?>
                    <div class="empty-state py-4"><p class="mb-0">Chưa có tin đăng nào.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Tên</th><th>Giá</th><th>Đã bán</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('/market/item/' . $it['slug']) ?>" class="text-decoration-none">
                                            <?= e($it['title']) ?>
                                        </a>
                                        <div class="form-hint"><?= e($cats[$it['category']] ?? $it['category']) ?></div>
                                    </td>
                                    <td class="font-numeric"><?= money((float)$it['price']) ?></td>
                                    <td><?= (int)$it['order_count'] ?></td>
                                    <td>
                                        <span class="badge-status st-<?= $it['is_active'] ? 'active' : 'closed' ?>">
                                            <?= $it['is_active'] ? 'Đang bán' : 'Đã ẩn' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Đơn hàng -->
        <div class="col-lg-6">
            <div class="panel-card">
                <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-shopping-bag text-gold me-2"></i>Đơn hàng đã mua</h3>

                <?php if (!$orders): ?>
                    <div class="empty-state py-4"><p class="mb-0">Chưa có đơn hàng nào.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Sản phẩm</th><th>Người bán</th><th>Giá</th><th>Liên hệ</th></tr></thead>
                            <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><?= e($o['title']) ?></td>
                                    <td><?= e($o['seller_name']) ?></td>
                                    <td class="font-numeric"><?= money((float)$o['amount']) ?></td>
                                    <td class="small"><?= e($o['contact'] ?: '—') ?></td>
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
