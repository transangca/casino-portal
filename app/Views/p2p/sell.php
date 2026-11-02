<?php /** @var array $categories */ ?>
<div class="container py-4" style="max-width:820px">
    <h1 class="page-title">Đăng tin bán</h1>
    <p class="page-sub">Đăng sản phẩm/dịch vụ của bạn lên sàn P2P. Tiền người mua sẽ được giữ trong ví dự phòng cho tới khi giao dịch hoàn tất.</p>

    <form method="POST" action="<?= url('/p2p/create') ?>" class="panel-card mt-4">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="200"
                   placeholder="VD: Tài khoản VIP 60 cấp - bảo hành trọn đời">
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Danh mục</label>
                <select name="category" class="form-select">
                    <?php foreach ($categories as $code => $label): ?>
                        <option value="<?= e($code) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Giá (đ)</label>
                <input type="number" name="price" class="form-control" required min="1" step="1000">
            </div>
            <div class="col-md-3">
                <label class="form-label">Số lượng</label>
                <input type="number" name="quantity" class="form-control" min="1" value="1">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Số tiền tối thiểu</label>
                <input type="number" name="min_amount" class="form-control" min="0" step="1000">
            </div>
            <div class="col-md-6">
                <label class="form-label">Số tiền tối đa</label>
                <input type="number" name="max_amount" class="form-control" min="0" step="1000">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Phương thức thanh toán</label>
            <input type="text" name="payment_method" class="form-control"
                   placeholder="Chuyển khoản ngân hàng / MoMo / Ví điện tử">
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả chi tiết</label>
            <textarea name="description" class="form-control" rows="5"
                      placeholder="Mô tả rõ sản phẩm, điều kiện bàn giao..."></textarea>
        </div>

        <div class="mb-4">
            <label class="form-label">Điều khoản bàn giao</label>
            <textarea name="terms" class="form-control" rows="3"
                      placeholder="VD: Bàn giao trong 5 phút sau khi thanh toán. Không hoàn tiền sau khi đã gửi."></textarea>
        </div>

        <button type="submit" class="btn btn-gold">
            <i class="fas fa-check me-1"></i>Đăng tin bán
        </button>
        <a href="<?= url('/p2p') ?>" class="btn btn-outline-gold ms-2">Huỷ</a>
    </form>
</div>
