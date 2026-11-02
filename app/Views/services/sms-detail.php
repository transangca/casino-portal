<?php
/**
 * Chi tiết chiến dịch SMS + nhật ký gửi.
 *
 * @var array $campaign
 * @var array $logs
 * @var int   $sentToday
 */
$st = (string)$campaign['status'];
$cls = match ($st) {
    'running' => 'st-processing',
    'done'    => 'st-success',
    'failed'  => 'st-danger',
    'paused'  => 'st-warning',
    'stopped' => 'st-closed',
    default   => 'st-closed',
};
$label = match ($st) {
    'running' => 'Đang chạy',
    'done'    => 'Hoàn tất',
    'failed'  => 'Có lỗi',
    'paused'  => 'Tạm dừng',
    'stopped' => 'Đã dừng',
    default   => 'Chờ',
};
$total = (int)$campaign['total'];
$sent  = (int)($campaign['sent'] ?? 0);
$fail  = (int)($campaign['failed'] ?? 0);
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="brand-icon brand-icon-lg"><i class="fas fa-comment-sms"></i></span>
        <div class="flex-fill min-w-0">
            <h1 class="page-title mb-1 text-truncate"><?= e((string)$campaign['name']) ?></h1>
            <div class="form-hint">
                Tốc độ: <?= e((string)($campaign['speed'] ?? 'normal')) ?>
                · Giới hạn ngày: <?= (int)($campaign['daily_limit'] ?? 0) ?>
                · Hôm nay đã gửi: <?= (int)$sentToday ?>
            </div>
        </div>
        <span class="badge-status <?= $cls ?>"><?= $label ?></span>

        <form method="POST" action="<?= url('/services/sms/action') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
            <?php if ($st === 'running'): ?>
                <input type="hidden" name="action" value="pause">
                <button class="btn btn-outline-gold btn-sm"><i class="fas fa-pause me-1"></i>Tạm dừng</button>
            <?php else: ?>
                <input type="hidden" name="action" value="start">
                <button class="btn btn-gold btn-sm"><i class="fas fa-play me-1"></i>Chạy</button>
            <?php endif; ?>
        </form>

        <form method="POST" action="<?= url('/services/sms/action') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
            <input type="hidden" name="action" value="stop">
            <button class="btn btn-outline-light btn-sm"><i class="fas fa-stop"></i></button>
        </form>

        <form method="POST" action="<?= url('/services/sms/delete') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
            <button class="btn btn-outline-light btn-sm" data-confirm="Xoá chiến dịch này?"><i class="fas fa-trash"></i></button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Tổng số</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value text-success"><?= $sent ?></div>
                <div class="stat-label">Đã gửi</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value text-danger"><?= $fail ?></div>
                <div class="stat-label">Lỗi</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= money((float)($campaign['cost'] ?? 0)) ?></div>
                <div class="stat-label">Chi phí</div>
            </div>
        </div>
    </div>

    <div class="panel-card mb-4">
        <h6 class="mb-3"><i class="fas fa-comment-dots me-2"></i>Nội dung tin nhắn</h6>
        <div class="info-row">
            <span class="info-label"><i class="fas fa-align-left me-1"></i>Nội dung</span>
            <span class="info-value"><?= e((string)$campaign['message']) ?></span>
        </div>
    </div>

    <div class="panel-card">
        <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Nhật ký gửi (<?= count($logs) ?>)</h6>

        <?php if (empty($logs)): ?>
            <div class="empty-state py-4">
                <i class="fas fa-inbox fs-1 d-block mb-3"></i>
                <p class="mb-0">Chưa có tin nhắn nào được gửi.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Số</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="small text-muted-2 font-numeric">
                                    <?= date('d/m H:i', strtotime((string)$l['created_at'])) ?>
                                </td>
                                <td class="font-numeric small"><?= e((string)$l['phone']) ?></td>
                                <td>
                                    <?php if ((string)$l['status'] === 'sent'): ?>
                                        <span class="badge-status st-success">Đã gửi</span>
                                    <?php else: ?>
                                        <span class="badge-status st-danger">Lỗi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted-2"><?= e((string)($l['error_msg'] ?: '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
