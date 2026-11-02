<?php /** @var array $platforms,$prices,$projects */ ?>
<div class="container py-4">
    <h1 class="page-title">Thiết kế website</h1>
    <p class="page-sub">Đăng yêu cầu thiết kế website theo nền tảng bạn muốn. Thanh toán trước khi vào phần tạo.</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-laptop-code me-2"></i>Yêu cầu dự án</h6>
                <form method="POST" action="<?= url('/services/website-builder/create') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Tên dự án</label>
                        <input type="text" name="name" class="form-control" required maxlength="150" placeholder="VD: Website bán hàng thời trang">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nền tảng</label>
                        <select name="platform" class="form-select" required>
                            <?php foreach ($platforms as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?> — <?= money((float)($prices[$code] ?? 0)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Domain dự kiến</label>
                            <input type="text" name="domain" class="form-control" placeholder="tencongty.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email liên hệ</label>
                            <input type="email" name="email" class="form-control" placeholder="contact@tencongty.com">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Telegram</label>
                            <input type="text" name="telegram" class="form-control" placeholder="@username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control" placeholder="09xx xxx xxx">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Facebook</label>
                            <input type="text" name="facebook" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zalo</label>
                            <input type="text" name="zalo" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả yêu cầu</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Mô tả tính năng, giao diện mong muốn, tham khảo..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-gold">
                        <i class="fas fa-paper-plane me-1"></i>Gửi yêu cầu
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-tags me-2"></i>Bảng giá</h6>
                <?php foreach ($platforms as $code => $label): ?>
                    <div class="d-flex justify-content-between py-1 small border-bottom border-opacity-25">
                        <span><?= e($label) ?></span>
                        <strong class="text-gold"><?= money((float)($prices[$code] ?? 0)) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-folder-open me-2"></i>Dự án của tôi</h6>
                <?php if (!$projects): ?>
                    <p class="small text-muted-2 mb-0">Chưa có dự án nào.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($projects as $p): ?>
                            <div class="list-group-item bg-transparent text-reset d-flex justify-content-between align-items-center">
                                <div class="min-w-0">
                                    <strong class="small d-block text-truncate"><?= e($p['name']) ?></strong>
                                    <span class="small text-muted-2">
                                        <?= e($platforms[$p['platform']] ?? $p['platform']) ?> · <?= money((float)$p['price']) ?>
                                    </span>
                                </div>
                                <a href="<?= url('/services/website-builder/project/' . $p['id']) ?>" class="btn btn-sm btn-outline-gold">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
