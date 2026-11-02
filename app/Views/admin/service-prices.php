<?php
/**
 * Quản trị bảng giá dịch vụ.
 *
 * @var array $prices
 * @var array $groups
 */
$adminPage = 'service-prices';
?>
<h1 class="page-title">Bảng giá dịch vụ</h1>
<p class="page-sub">
    Quy định giá niêm yết cho từng dịch vụ. Giá được áp dụng tức thì cho khách hàng.
</p>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <h3><i class="fas fa-plus-circle text-gold me-2"></i>Thêm / sửa giá</h3>

            <form method="POST" action="<?= url('/admin/service-prices/save') ?>" id="priceForm">
                <?= csrf_field() ?>
                <input type="hidden" name="code" id="pCode" value="">

                <div class="mb-3">
                    <label class="form-label" for="pCodeText">Mã dịch vụ *</label>
                    <input type="text" class="form-control font-numeric" id="pCodeText"
                           placeholder="vd: phone-check" required>
                    <div class="form-hint">Chỉ chữ, số, gạch dưới, gạch ngang. Không thể sửa mã cũ.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pName">Tên hiển thị *</label>
                    <input type="text" class="form-control" id="pName" placeholder="Check số điện thoại" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="pGroup">Nhóm</label>
                        <input type="text" class="form-control" id="pGroup" placeholder="Tiện ích" value="Tiện ích">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="pUnit">Đơn vị</label>
                        <input type="text" class="form-control" id="pUnit" placeholder="lần" value="lần">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pPrice">Giá (đ) *</label>
                    <input type="number" class="form-control font-numeric" id="pPrice" value="0" min="0" step="100" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pDesc">Mô tả</label>
                    <input type="text" class="form-control" id="pDesc" placeholder="Ghi chú nội bộ">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="pSort">Thứ tự</label>
                        <input type="number" class="form-control font-numeric" id="pSort" value="0" step="1">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="pActive" value="1" checked>
                            <label class="form-check-label" for="pActive">Kích hoạt</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu giá</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card">
            <h3><i class="fas fa-list text-gold me-2"></i>Bảng giá hiện tại</h3>

            <?php if (empty($prices)): ?>
                <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có bản ghi giá.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mã</th><th>Tên</th><th>Nhóm</th><th>Giá</th><th>Đơn vị</th><th>Trạng thái</th><th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prices as $p): ?>
                            <tr>
                                <td><code class="small"><?= e((string)$p['code']) ?></code></td>
                                <td class="small fw-semibold"><?= e((string)$p['name']) ?></td>
                                <td class="small text-muted-2"><?= e((string)$p['group_name']) ?></td>
                                <td class="text-gold font-numeric small"><?= money((float)$p['price']) ?></td>
                                <td class="small text-muted-2"><?= e((string)$p['unit']) ?></td>
                                <td>
                                    <span class="badge-status st-<?= (int)$p['is_active'] === 1 ? 'active' : 'closed' ?>">
                                        <?= (int)$p['is_active'] === 1 ? 'Bật' : 'Tắt' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-gold edit-price"
                                            data-code="<?= e((string)$p['code']) ?>"
                                            data-name="<?= e((string)$p['name']) ?>"
                                            data-group="<?= e((string)$p['group_name']) ?>"
                                            data-unit="<?= e((string)$p['unit']) ?>"
                                            data-price="<?= e((string)$p['price']) ?>"
                                            data-desc="<?= e((string)($p['description'] ?? '')) ?>"
                                            data-sort="<?= (int)($p['sort_order'] ?? 0) ?>"
                                            data-active="<?= (int)$p['is_active'] ?>">Sửa</button>

                                        <form method="POST" action="<?= url('/admin/service-prices/delete') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" data-confirm="Xoá giá này?"><i class="fas fa-trash"></i></button>
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
    </div>
</div>

<script>
(function () {
    var codeInput = document.getElementById('pCode');
    var codeText  = document.getElementById('pCodeText');

    document.querySelectorAll('.edit-price').forEach(function (btn) {
        btn.addEventListener('click', function () {
            codeText.value = btn.dataset.code;
            codeInput.value = btn.dataset.code;
            document.getElementById('pName').value  = btn.dataset.name || '';
            document.getElementById('pGroup').value = btn.dataset.group || '';
            document.getElementById('pUnit').value  = btn.dataset.unit || '';
            document.getElementById('pPrice').value = btn.dataset.price || 0;
            document.getElementById('pDesc').value  = btn.dataset.desc || '';
            document.getElementById('pSort').value  = btn.dataset.sort || 0;
            document.getElementById('pActive').checked = btn.dataset.active === '1';
            codeText.readOnly = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Khi đổi mã → đồng bộ field ẩn
    codeText.addEventListener('input', function () {
        codeInput.value = codeText.value.trim();
    });

    document.getElementById('priceForm').addEventListener('submit', function () {
        codeInput.value = codeText.value.trim();
    });
})();
</script>
