<?php
/**
 * Quản trị nhật ký check số điện thoại.
 *
 * @var array  $checks
 * @var array  $stats
 * @var string $phone
 */
$adminPage = 'phone-checks';
?>
<h1 class="page-title">Nhật ký check số điện thoại</h1>
<p class="page-sub">Toàn bộ lần tra cứu của khách hàng — dùng để đối soát doanh thu và kiểm tra lạm dụng.</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['total'] ?></div><div class="stat-label">Tổng lượt</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['found'] ?></div><div class="stat-label">Có dữ liệu</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['today'] ?></div><div class="stat-label">Hôm nay</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value text-gold"><?= money((float)$stats['revenue']) ?></div><div class="stat-label">Doanh thu</div></div>
    </div>
</div>

<div class="panel-card">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h3 class="mb-0"><i class="fas fa-address-book text-gold me-2"></i>Lịch sử tra cứu</h3>

        <form method="GET" action="<?= url('/admin/phone-checks') ?>" class="ms-auto d-flex gap-2">
            <input type="text" name="phone" class="form-control form-control-sm font-numeric"
                   placeholder="Tìm theo số..." value="<?= e($phone) ?>" style="width:200px">
            <button class="btn btn-sm btn-outline-gold">Lọc</button>
        </form>
    </div>

    <?php if (empty($checks)): ?>
        <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có lượt tra cứu nào.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Thời gian</th><th>User</th><th>Số</th><th>Nhà mạng</th>
                        <th>Họ tên</th><th>CCCD</th><th>Địa chỉ</th><th>Phí</th><th>Nguồn</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr>
                        <td class="small text-muted-2 font-numeric">
                            <?= date('d/m/Y H:i', strtotime((string)$c['created_at'])) ?>
                        </td>
                        <td class="small"><?= e((string)($c['username'] ?? '—')) ?></td>
                        <td class="font-numeric small"><?= e((string)$c['phone']) ?></td>
                        <td class="small"><?= e((string)($c['carrier'] ?: '—')) ?></td>
                        <td class="small"><?= e((string)($c['full_name'] ?: '—')) ?></td>
                        <td class="font-numeric small"><?= e((string)($c['id_card'] ?: '—')) ?></td>
                        <td class="small text-truncate" style="max-width:200px"><?= e((string)($c['address'] ?: '—')) ?></td>
                        <td class="small text-gold font-numeric"><?= money((float)($c['cost'] ?? 0)) ?></td>
                        <td>
                            <span class="badge-status st-<?= (string)$c['status'] === 'found' ? 'success' : 'closed' ?>">
                                <?= e((string)$c['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
