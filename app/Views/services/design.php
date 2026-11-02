<?php /** @var array $category,$categories,$schema,$variants,$projects */ ?>
<div class="container py-4">
    <h1 class="page-title">Thiết kế ảnh</h1>
    <p class="page-sub">Nhập thông tin → hệ thống tự dựng ảnh, xem trước và tải về.</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ($categories as $code => $label): ?>
            <a href="<?= url('/services/design?category=' . urlencode($code)) ?>"
               class="filter-chip <?= $category === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-sliders me-2"></i>Thông tin thiết kế</h6>
                <form method="POST" action="<?= url('/services/design/create') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category" value="<?= e($category) ?>">

                    <div class="mb-3">
                        <label class="form-label">Tên dự án</label>
                        <input type="text" name="name" class="form-control" placeholder="VD: Poster khai trương" maxlength="150">
                    </div>

                    <?php foreach ($schema as $f): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <?= e($f['label']) ?>
                                <?php if (!empty($f['required'])): ?><span class="text-danger">*</span><?php endif; ?>
                            </label>
                            <?php if ($f['type'] === 'textarea'): ?>
                                <textarea name="<?= e($f['key']) ?>" class="form-control" rows="4" <?= !empty($f['required']) ? 'required' : '' ?>></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($f['key']) ?>" class="form-control"
                                       <?= !empty($f['required']) ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã biến thể chữ</label>
                            <select name="variant_code" class="form-select">
                                <option value="">— Mặc định —</option>
                                <?php foreach ($variants as $v): ?>
                                    <option value="<?= e($v['code']) ?>">
                                        <?= e($v['label']) ?> (<?= e($v['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Chiều rộng</label>
                            <input type="number" name="width" class="form-control" value="1080" min="200" max="4000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Chiều cao</label>
                            <input type="number" name="height" class="form-control" value="1080" min="200" max="4000">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Màu chủ đạo</label>
                            <input type="text" name="accent" class="form-control" value="#f5c542">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="price" class="form-control" value="0" min="0" step="1000">
                            <div class="form-text">Để 0 để hệ thống tính theo mẫu.</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold mt-4">
                        <i class="fas fa-wand-magic-sparkles me-1"></i>Tạo &amp; xem trước
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-images me-2"></i>Dự án của tôi</h6>
                <?php if (!$projects): ?>
                    <p class="small text-muted-2 mb-0">Chưa có dự án nào.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($projects as $p): ?>
                            <div class="list-group-item bg-transparent text-reset d-flex justify-content-between align-items-center">
                                <div class="min-w-0">
                                    <strong class="small d-block text-truncate"><?= e($p['name']) ?></strong>
                                    <span class="small text-muted-2">
                                        <?= e($categories[$p['category']] ?? $p['category']) ?> · <?= money((float)$p['price']) ?>
                                    </span>
                                </div>
                                <a href="<?= url('/services/design/preview/' . $p['id']) ?>" class="btn btn-sm btn-outline-gold">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
