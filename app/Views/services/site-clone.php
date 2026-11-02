<?php
/**
 * Dịch vụ copy 1-1 trang web.
 *
 * @var array  $projects
 * @var float  $price
 * @var float  $balance
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="flex-fill">
            <h1 class="page-title mb-1"><i class="fas fa-clone me-2 text-gold"></i>Copy 1-1 trang web</h1>
            <p class="page-sub mb-0">
                Dán link website → hệ thống tải <strong>toàn bộ index.html + CSS + ảnh + script + font</strong>,
                viết lại liên kết nội bộ và gom thành <strong>1 website hoàn chỉnh</strong> tải về dưới dạng ZIP.
            </p>
        </div>
        <div class="d-flex gap-2">
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($price) ?></div>
                <div class="stat-label">Giá / lần</div>
            </div>
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($balance) ?></div>
                <div class="stat-label">Số dư</div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary small">
        <i class="fas fa-circle-info me-1"></i>
        Chỉ sao chép website bạn có quyền sử dụng. Bản quyền nội dung thuộc về chủ sở hữu website gốc.
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-link me-2"></i>Link website cần sao chép</h6>

                <form method="POST" action="<?= url('/services/site-clone/create') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="url">Đường dẫn</label>
                        <input type="text" id="url" name="url" class="form-control font-numeric"
                               placeholder="https://example.com/" required>
                        <div class="form-hint mt-1">
                            Hệ thống sẽ tự lấy các trang con cùng domain và tải asset kèm theo.
                        </div>
                    </div>
                    <button class="btn btn-gold w-100">
                        <i class="fas fa-download me-1"></i>Bắt đầu sao chép (<?= money($price) ?>)
                    </button>
                </form>

                <hr class="footer-divider">

                <h6 class="mb-2 small"><i class="fas fa-list me-1"></i>Quy trình</h6>
                <ol class="small text-muted-2 ps-3 mb-0">
                    <li>Tải HTML trang chính</li>
                    <li>Dò link → tải các trang con cùng domain</li>
                    <li>Tải về toàn bộ CSS, ảnh, sprite, font, script</li>
                    <li>Viết lại link về file cục bộ</li>
                    <li>Đóng gói ZIP — mở là chạy</li>
                </ol>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-folder-open me-2"></i>Dự án của tôi</h6>

                <?php if (empty($projects)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-folder-plus fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có dự án nào. Dán link website rồi bấm "Bắt đầu sao chép".</p>
                    </div>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($projects as $p): ?>
                            <?php
                            $st = (string)$p['status'];
                            $cls = match ($st) {
                                'done'    => 'st-success',
                                'failed'  => 'st-danger',
                'scanning'=> 'st-processing',
                                default   => 'st-closed',
                            };
                            $label = match ($st) {
                                'done'    => 'Xong',
                                'failed'  => 'Lỗi',
                                'scanning'=> 'Đang chạy',
                                default   => 'Chờ',
                            };
                            ?>
                            <div class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-globe text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate"><?= e((string)($p['title'] ?: $p['host'])) ?></div>
                                        <div class="form-hint text-truncate"><?= e((string)$p['source_url']) ?></div>
                                    </div>
                                    <span class="badge-status <?= $cls ?>"><?= $label ?></span>
                                    <a href="<?= url('/services/site-clone/project/' . (int)$p['id']) ?>"
                                       class="btn btn-outline-light btn-sm"><i class="fas fa-chevron-right"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
