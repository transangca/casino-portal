<?php
/**
 * Dịch vụ gửi tin nhắn SMS hàng loạt.
 *
 * @var array $campaigns
 * @var array $speeds
 * @var float $price
 * @var float $balance
 * @var int   $gateways
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="flex-fill">
            <h1 class="page-title mb-1"><i class="fas fa-comment-sms me-2 text-gold"></i>Gửi tin nhắn SMS hàng loạt</h1>
            <p class="page-sub mb-0">
                Tạo chiến dịch → hệ thống gửi <strong>dần theo giới hạn ngày</strong> qua cổng SMS đã cấu hình,
                chạy nền bằng cron nên không bị treo trang.
            </p>
        </div>
        <div class="d-flex gap-2">
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($price) ?></div>
                <div class="stat-label">Giá / tin</div>
            </div>
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($balance) ?></div>
                <div class="stat-label">Số dư</div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary small">
        <i class="fas fa-circle-info me-1"></i>
        Chỉ gửi tin nhắn tới số bạn có quyền. Hệ thống giới hạn tốc độ và giới hạn ngày để tránh bị khóa đầu số.
        Số cổng SMS đang cấu hình: <strong><?= (int)$gateways ?></strong>.
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-paper-plane me-2"></i>Tạo chiến dịch mới</h6>

                <form method="POST" action="<?= url('/services/sms/create') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="name">Tên chiến dịch</label>
                        <input type="text" id="name" name="name" class="form-control"
                               placeholder="Thông báo mở bán cuối tuần">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="message">Nội dung tin nhắn *</label>
                        <textarea id="message" name="message" class="form-control" rows="4" maxlength="1000"
                                  placeholder="Nội dung bạn muốn gửi tới khách hàng..." required></textarea>
                        <div class="form-hint mt-1">Tối đa 1000 ký tự.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="target_list">Danh sách số điện thoại *</label>
                        <textarea id="target_list" name="target_list" class="form-control font-numeric"
                                  rows="7" placeholder="0912345678|Nguyễn A&#10;0987654321&#10;0905112233"></textarea>
                        <div class="form-hint mt-1">
                            Mỗi dòng 1 số (hoặc ngăn cách bằng dấu phẩy). Có thể thêm tên sau dấu <code>|</code>.
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="speed">Tốc độ gửi</label>
                            <select id="speed" name="speed" class="form-select">
                                <?php foreach ($speeds as $key => $s): ?>
                                    <option value="<?= e((string)$key) ?>"><?= e((string)$s['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="daily_limit">Giới hạn / ngày</label>
                            <input type="number" id="daily_limit" name="daily_limit" class="form-control font-numeric"
                                   value="200" min="1" max="5000" step="10">
                        </div>
                    </div>

                    <button class="btn btn-gold w-100">
                        <i class="fas fa-paper-plane me-1"></i>Tạo &amp; bắt đầu gửi
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-list me-2"></i>Chiến dịch của tôi</h6>

                <?php if (empty($campaigns)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-paper-plane fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có chiến dịch nào.</p>
                    </div>
                <?php else: ?>
                    <div class="aic-order-list">
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
                                'done'    => 'Hoàn tất',
                                'failed'  => 'Có lỗi',
                                'paused'  => 'Tạm dừng',
                                'stopped' => 'Đã dừng',
                                default   => 'Chờ',
                            };
                            $total = (int)$c['total'];
                            $sent  = (int)($c['sent'] ?? 0);
                            $pct   = $total > 0 ? (int)round($sent / $total * 100) : 0;
                            ?>
                            <div class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-comment-sms text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate"><?= e((string)$c['name']) ?></div>
                                        <div class="form-hint text-truncate">
                                            <?= (int)$c['total'] ?> số · đã gửi <?= (int)($c['sent'] ?? 0) ?>
                                            · lỗi <?= (int)($c['failed'] ?? 0) ?>
                                        </div>
                                    </div>
                                    <span class="badge-status <?= $cls ?>"><?= $label ?></span>
                                    <a href="<?= url('/services/sms/detail/' . (int)$c['id']) ?>"
                                       class="btn btn-outline-light btn-sm"><i class="fas fa-chevron-right"></i></a>
                                </div>
                                <div class="progress mt-2" style="height:5px">
                                    <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
