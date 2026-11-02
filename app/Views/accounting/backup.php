<?php /** @var array $backups,$drivers, array|null $search, string $docNo */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Sao lưu &amp; khôi phục dữ liệu</h1>
    </div>
</div>

<div class="p-3 p-lg-4">
    <div class="row g-3">
        <div class="col-md-5">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-cloud-arrow-up me-2 text-gold"></i>Tạo bản sao lưu</h6>
                <p class="small text-muted-2">
                    Hệ thống tự động sao lưu mỗi 1–2 giờ vào storage driver đang cấu hình.
                    Mỗi phiếu có mã số riêng để tra cứu khi khách hàng mất dữ liệu.
                </p>
                <table class="table table-sm small">
                    <tbody>
                        <?php foreach ($drivers as $d): ?>
                            <tr>
                                <td><i class="fas fa-database me-2 text-gold"></i><?= e((string)$d['name']) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-secondary bg-opacity-25"><?= e((string)$d['kind']) ?></span>
                                    <?php if ((int)$d['is_default'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-25">mặc định</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$drivers): ?>
                            <tr><td class="text-muted-2">Chưa cấu hình driver. Đang dùng local storage.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <form method="POST" action="<?= url('/services/accounting/backup/run') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-gold w-100"><i class="fas fa-database me-2"></i>Sao lưu ngay</button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="panel-card mb-3">
                <h6 class="mb-3"><i class="fas fa-magnifying-glass me-2 text-gold"></i>Tra cứu phiếu theo mã số</h6>
                <form method="GET" class="row g-2">
                    <div class="col-8">
                        <input type="text" name="doc_no" class="form-control form-control-sm" placeholder="VD: KT251101A1B2C3" value="<?= e($docNo) ?>">
                    </div>
                    <div class="col-4">
                        <button class="btn btn-gold btn-sm w-100">Tra cứu</button>
                    </div>
                </form>
                <?php if (!empty($search)): ?>
                    <table class="table table-sm small mt-3 mb-0">
                        <thead><tr><th>Mã phiếu</th><th>Loại</th><th>Ngày</th><th>Đối tượng</th><th class="text-end">Tổng</th></tr></thead>
                        <tbody>
                            <?php foreach ($search as $s): ?>
                                <tr>
                                    <td class="font-monospace"><?= e((string)$s['doc_no']) ?></td>
                                    <td><?= e((string)$s['doc_type']) ?></td>
                                    <td><?= e(date('d/m/Y', strtotime((string)$s['doc_date']))) ?></td>
                                    <td><?= e((string)($s['partner'] ?: '—')) ?></td>
                                    <td class="text-end"><?= money((float)$s['total_debit']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2 text-gold"></i>Lịch sử sao lưu</h6>
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Thời điểm</th><th>Nhãn</th><th>Driver</th><th class="text-end">Dung lượng</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><?= e(date('d/m/Y H:i', strtotime((string)$b['created_at']))) ?></td>
                                <td><?= e((string)$b['label']) ?></td>
                                <td><?= e((string)$b['driver']) ?></td>
                                <td class="text-end"><?= number_format(((int)$b['size_bytes']) / 1024, 1) ?> KB</td>
                                <td>
                                    <?php if ((string)$b['status'] === 'ok'): ?>
                                        <span class="badge bg-success bg-opacity-25 text-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-25 text-danger">Lỗi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$backups): ?>
                            <tr><td colspan="5" class="text-muted-2 text-center py-4">Chưa có bản sao lưu nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
