<?php /** @var array $tickets,$status,$counts,$categories,$priorities */ ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý yêu cầu hỗ trợ</h1>
        <p class="text-muted-2 mb-0">Xử lý ticket khách hàng gửi từ trang Hỗ trợ.</p>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?= url('/admin/tickets') ?>" class="btn btn-sm <?= $status === '' ? 'btn-gold' : 'btn-outline-light' ?>">
        Tất cả
    </a>
    <a href="<?= url('/admin/tickets?status=open') ?>" class="btn btn-sm <?= $status === 'open' ? 'btn-gold' : 'btn-outline-light' ?>">
        Chờ xử lý <span class="badge bg-dark"><?= (int)($counts['open'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/admin/tickets?status=processing') ?>" class="btn btn-sm <?= $status === 'processing' ? 'btn-gold' : 'btn-outline-light' ?>">
        Đang xử lý <span class="badge bg-dark"><?= (int)($counts['processing'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/admin/tickets?status=resolved') ?>" class="btn btn-sm <?= $status === 'resolved' ? 'btn-gold' : 'btn-outline-light' ?>">
        Đã xử lý <span class="badge bg-dark"><?= (int)($counts['resolved'] ?? 0) ?></span>
    </a>
    <a href="<?= url('/admin/tickets?status=closed') ?>" class="btn btn-sm <?= $status === 'closed' ? 'btn-gold' : 'btn-outline-light' ?>">
        Đã đóng <span class="badge bg-dark"><?= (int)($counts['closed'] ?? 0) ?></span>
    </a>
</div>

<div class="casino-card">
    <div class="card-body p-0">
        <?php if (!$tickets): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fs-1 text-muted-2 mb-3"></i>
                <p class="text-muted-2 mb-0">Không có yêu cầu nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Khách hàng</th>
                            <th>Tiêu đề</th>
                            <th>Loại</th>
                            <th>Ưu tiên</th>
                            <th>Trạng thái</th>
                            <th>Cập nhật</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $statusMap = [
                            'open'       => ['Chờ xử lý', 'st-processing'],
                            'processing' => ['Đang xử lý', 'st-active'],
                            'resolved'   => ['Đã xử lý', 'st-approved'],
                            'closed'     => ['Đã đóng', 'st-closed'],
                        ];
                        [$label, $cls] = $statusMap[$t['status']] ?? ['—', 'st-closed'];
                        ?>
                        <tr>
                            <td><code class="text-gold"><?= e($t['code']) ?></code></td>
                            <td>
                                <div class="fw-semibold"><?= e($t['full_name'] ?: $t['username']) ?></div>
                                <div class="small text-muted-2"><?= e($t['username']) ?></div>
                            </td>
                            <td class="text-truncate" style="max-width:220px"><?= e($t['subject']) ?></td>
                            <td class="small"><?= e($categories[$t['category']] ?? $t['category']) ?></td>
                            <td class="small"><?= e($priorities[$t['priority']] ?? $t['priority']) ?></td>
                            <td><span class="badge-status <?= e($cls) ?>"><?= e($label) ?></span></td>
                            <td class="small text-muted-2"><?= e(date('d/m/Y H:i', strtotime($t['updated_at']))) ?></td>
                            <td class="text-end">
                                <a href="<?= url('/admin/tickets/' . $t['code']) ?>" class="btn btn-sm btn-outline-gold">
                                    <i class="fas fa-reply me-1"></i>Xử lý
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
