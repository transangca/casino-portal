<?php
/**
 * Form đăng bán tài nguyên.
 *
 * @var array $cats
 */
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="page-title"><i class="fas fa-plus-circle text-gold me-2"></i><?= t('market.sell') ?></h1>
            <p class="page-sub">Đăng bán tài khoản, tool hoặc dịch vụ của bạn. Tiền bán được chuyển thẳng vào ví.</p>

            <div class="panel-card">
                <form method="POST" action="<?= url('/market/sell') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Tên sản phẩm</label>
                        <input type="text" class="form-control" id="title" name="title" required maxlength="200"
                               placeholder="VD: Tài khoản PGSoft trải nghiệm 1 tháng">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="category">Danh mục</label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($cats as $code => $label): ?>
                                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="price">Giá</label>
                            <div class="input-group">
                                <input type="number" class="form-control font-numeric" id="price" name="price" min="0" step="1000" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="stock">Số lượng</label>
                            <input type="number" class="form-control font-numeric" id="stock" name="stock" min="-1" value="-1">
                            <div class="form-hint mt-1">-1 = không giới hạn</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Mô tả chi tiết</label>
                        <textarea class="form-control" id="description" name="description" rows="5"
                                  placeholder="Mô tả rõ ràng tài nguyên, điều kiện bàn giao..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="image">Ảnh minh hoạ (URL)</label>
                            <input type="text" class="form-control" id="image" name="image" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact">Thông tin liên hệ</label>
                            <input type="text" class="form-control" id="contact" name="contact"
                                   placeholder="Telegram / Zalo / Email">
                        </div>
                    </div>

                    <button class="btn btn-gold py-2 fw-bold">
                        <i class="fas fa-upload me-2"></i>Đăng bán
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
