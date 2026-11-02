<?php /** @var array $products @var array $orders */ $adminPage = 'market'; ?>
<h1 class="page-title">Chợ tài nguyên</h1>
<p class="page-sub">Quản lý tin đăng bán và đơn hàng của sàn giao dịch nội bộ.</p>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-tags text-gold me-2"></i>Tin đăng</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Sản phẩm</th><th>Người bán</th><th class="text-end">Giá</th><th>Đã bán</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$products): ?>
                        <tr><td colspan="6" class="text-center text-muted-2 py-4">Chưa có tin đăng nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($p['title']) ?></div>
                                <div class="form-hint"><?= e(\App\Services\MarketService::categoryLabel((string)$p['category'])) ?></div>
                            </td>
                            <td class="small"><?= e($p['username']) ?></td>
                            <td class="text-end font-numeric"><?= money((float)$p['price']) ?></td>
                            <td class="font-numeric"><?= (int)$p['sold_count'] ?></td>
                            <td>
                                <span class="badge-status st-<?= $p['is_active'] ? 'active' : 'closed' ?>">
                                    <?= $p['is_active'] ? 'Active' : 'Ẩn' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= url('/admin/market/toggle') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-sm btn-soft"><i class="fas fa-eye"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card">
            <h3><i class="fas fa-shopping-bag text-gold me-2"></i>Đơn hàng</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Sản phẩm</th><th>Mua / Bán</th><th class="text-end">Tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="4" class="text-center text-muted-2 py-4">Chưa có đơn hàng nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small"><?= e($o['title']) ?></td>
                            <td class="small"><?= e($o['buyer']) ?> → <?= e($o['seller']) ?></td>
                            <td class="text-end font-numeric"><?= money((float)$o['amount']) ?></td>
                            <td>
                                <span class="badge-status st-<?= $o['status'] === 'paid' ? 'approved' : 'closed' ?>">
                                    <?= e(ucfirst((string)$o['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
