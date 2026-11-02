<?php
/**
 * Quản trị chiến dịch SMS.
 *
 * @var array $campaigns
 * @var array $stats
 */
$adminPage = 'sms-campaigns';
?>
<h1 class="page-title">Chiến dịch tin nhắn</h1>
<p class="page-sub">
    Giám sát &amp; điều khiển các chiến dịch gửi SMS hàng loạt. Cron <code>database/cron_sms.php</code> xử lý phần gửi.
</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['campaigns'] ?></div><div class="stat-label">Chiến dịch</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['running'] ?></div><div class="stat-label">Đang chạy</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value"><?= (int)$stats['messages'] ?></div><div class="stat-label">Tin đã gửi</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-value text-gold"><?= money((float)$stats['revenue']) ?></div><div class="stat-label">Doanh thu</div></div>
    </div>
</div>

<div class="panel-card">
    <h3><i class="fas fa-comment-sms text-gold me-2"></i>Danh sách chiến dịch</h3>

    <?php if (empty($campaigns)): ?>
        <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có chiến dịch nào.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>User</th><th>Chiến dịch</th><th>Tổng</th>
                        <th>Đã gửi</th><th>Lỗi</th><th>Trạng thái</th><th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($campaigns as $c): ?>
                    <?php
                    $st = (string)$c['status'];
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
                        'done'    => 'Xong',
                        'failed'  => 'Lỗi',
                        'paused'  => 'Tạm dừng',
                        'stopped' => 'Dừng',
                        default   => 'Chờ',
                    };
                    ?>
                    <tr>
                        <td class="text-secondary small"><?= (int)$c['id'] ?></td>
                        <td class="small"><?= e((string)($c['username'] ?? '—')) ?></td>
                        <td class="small fw-semibold text-truncate" style="max-width:200px"><?= e((string)$c['name']) ?></td>
                        <td class="small font-numeric"><?= (int)$c['total'] ?></td>
                        <td class="small font-numeric text-success"><?= (int)($c['sent'] ?? 0) ?></td>
                        <td class="small font-numeric text-danger"><?= (int)($c['failed'] ?? 0) ?></td>
                        <td><span class="badge-status <?= $cls ?>"><?= $label ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ((string)$c['status'] === 'running'): ?>
                                    <form method="POST" action="<?= url('/admin/sms-campaigns/action') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="action" value="pause">
                                        <button class="btn btn-sm btn-outline-gold" title="Tạm dừng"><i class="fas fa-pause"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= url('/admin/sms-campaigns/action') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="action" value="start">
                                        <button class="btn btn-sm btn-primary" title="Chạy"><i class="fas fa-play"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= url('/admin/sms-campaigns/action') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="action" value="stop">
                                    <button class="btn btn-sm btn-outline-danger" title="Dừng"><i class="fas fa-stop"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
