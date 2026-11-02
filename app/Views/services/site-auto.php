<?php
/**
 * Dịch vụ tạo website tự động A-Z.
 *
 * @var array  $projects
 * @var array  $categories
 * @var float  $price
 * @var float  $balance
 */
$themes = [
    'dark'  => 'Tối (vàng)',
    'gold'  => 'Tối (vàng kim)',
    'blue'  => 'Tối (xanh dương)',
    'red'   => 'Tối (đỏ)',
    'green' => 'Tối (xanh lá)',
    'light' => 'Sáng',
];
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="flex-fill">
            <h1 class="page-title mb-1"><i class="fas fa-wand-sparkles me-2 text-gold"></i>Tạo website tự động A-Z</h1>
            <p class="page-sub mb-0">
                Nhập thông tin → hệ thống sinh ra <strong>website hoàn chỉnh</strong>: 6 trang HTML,
                CSS responsive, JS menu + slider, SEO, sitemap, robots.txt — đóng gói ZIP sẵn sàng đưa lên host.
            </p>
        </div>
        <div class="d-flex gap-2">
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($price) ?></div>
                <div class="stat-label">Giá / dự án</div>
            </div>
            <div class="stat-card text-center" style="min-width:130px">
                <div class="stat-value" style="font-size:1.3rem"><?= money($balance) ?></div>
                <div class="stat-label">Số dư</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-sliders me-2"></i>Thông tin website</h6>

                <form method="POST" action="<?= url('/services/site-auto/create') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="name">Tên website *</label>
                        <input type="text" id="name" name="name" class="form-control"
                               placeholder="Cổng game của tôi" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="domain">Tên miền</label>
                        <input type="text" id="domain" name="domain" class="form-control font-numeric"
                               placeholder="example.com">
                        <div class="form-hint mt-1">Dùng để tạo sitemap.xml và robots.txt.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="category">Chủ đề</label>
                            <select id="category" name="category" class="form-select">
                                <?php foreach ($categories as $code => $label): ?>
                                    <option value="<?= e((string)$code) ?>"><?= e((string)$label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="theme">Màu giao diện</label>
                            <select id="theme" name="theme" class="form-select">
                                <?php foreach ($themes as $code => $label): ?>
                                    <option value="<?= e((string)$code) ?>"><?= e((string)$label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="keywords">Từ khoá</label>
                        <input type="text" id="keywords" name="keywords" class="form-control"
                               placeholder="casino, game, nạp tiền, rút tiền">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Mô tả ngắn về website của bạn..."></textarea>
                    </div>

                    <button class="btn btn-gold w-100">
                        <i class="fas fa-magic me-1"></i>Tạo website (<?= money($price) ?>)
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-list-check me-2"></i>Website sẽ gồm</h6>
                <div class="row g-2">
                    <?php
                    $items = [
                        ['Trang chủ', 'Hero + slider + dịch vụ + CTA'],
                        ['Giới thiệu', 'Về chúng tôi, sứ mệnh, cam kết'],
                        ['Dịch vụ', 'Danh sách dịch vụ dạng card'],
                        ['Bài viết', 'Lưới bài viết mẫu'],
                        ['Liên hệ', 'Form liên hệ có validate'],
                        ['Chính sách', 'Bảo mật & điều khoản'],
                    ];
                    foreach ($items as $it): ?>
                        <div class="col-sm-6">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-file me-1"></i><?= e($it[0]) ?></span>
                                <span class="info-value"><?= e($it[1]) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-folder-open me-2"></i>Dự án của tôi</h6>

                <?php if (empty($projects)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-folder-plus fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có dự án nào.</p>
                    </div>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($projects as $p): ?>
                            <?php
                            $st = (string)$p['status'];
                            $cls = match ($st) {
                                'done'    => 'st-success',
                                'failed'  => 'st-danger',
                                'building'=> 'st-processing',
                                default   => 'st-closed',
                            };
                            $label = match ($st) {
                                'done'    => 'Xong',
                                'failed'  => 'Lỗi',
                                'building'=> 'Đang tạo',
                                default   => 'Chờ',
                            };
                            ?>
                            <div class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-globe text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate"><?= e((string)$p['name']) ?></div>
                                        <div class="form-hint text-truncate">
                                            <?= e((string)($p['domain'] ?: $p['category'])) ?>
                                        </div>
                                    </div>
                                    <span class="badge-status <?= $cls ?>"><?= $label ?></span>
                                    <a href="<?= url('/services/site-auto/project/' . (int)$p['id']) ?>"
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
