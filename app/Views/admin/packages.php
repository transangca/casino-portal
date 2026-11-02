<?php /** @var array $packages */ $adminPage = 'packages'; ?>
<h1 class="page-title">Quản lý gói chơi</h1>
<p class="page-sub">Tạo và chỉnh sửa các gói trải nghiệm / thuê theo thời gian.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-plus-circle text-gold me-2"></i>Thêm gói mới</h3>
            <form method="POST" action="<?= url('/admin/packages/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="0">
                <div class="mb-3">
                    <label class="form-label" for="name">Tên gói</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả</label>
                    <input type="text" class="form-control" id="description" name="description">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="duration_minutes">Thời lượng (phút)</label>
                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="1" value="60" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="price">Giá (đ)</label>
                        <input type="number" class="form-control" id="price" name="price" min="0" step="1000" value="0" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="original_price">Giá gốc</label>
                        <input type="number" class="form-control" id="original_price" name="original_price" min="0" step="1000" value="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="sort_order">Thứ tự</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="provider_code">Giới hạn sảnh (để trống = tất cả)</label>
                    <input type="text" class="form-control" id="provider_code" name="provider_code" placeholder="VD: pgsoft">
                </div>
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" id="is_free_trial" name="is_free_trial" value="1" checked>
                    <label class="form-check-label" for="is_free_trial">Gói trải nghiệm (miễn phí)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="trial_limit_per_user">Lượt miễn phí mỗi user</label>
                    <input type="number" class="form-control" id="trial_limit_per_user" name="trial_limit_per_user" min="1" value="1">
                </div>
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">Đang bán</label>
                </div>
                <button class="btn btn-primary w-100">Tạo gói</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <h3><i class="fas fa-box-open text-gold me-2"></i>Danh sách gói</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Tên</th><th>Thời lượng</th><th class="text-end">Giá</th><th>Sảnh</th><th>Miễn phí</th><th>Bán</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><?= (int)$p['duration_minutes'] ?>p</td>
                            <td class="text-end"><?= (float)$p['price'] > 0 ? money((float)$p['price']) : 'Miễn phí' ?></td>
                            <td class="text-secondary"><?= e($p['provider_code'] ?: 'Tất cả') ?></td>
                            <td><?= $p['is_free_trial'] ? 'Có' : 'Không' ?></td>
                            <td><?= $p['is_active'] ? 'Có' : 'Không' ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPkg<?= (int)$p['id'] ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php foreach ($packages as $p): ?>
<div class="modal fade" id="editPkg<?= (int)$p['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:var(--bg-card);border-color:var(--border-subtle)">
            <form method="POST" action="<?= url('/admin/packages/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa gói</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tên</label>
                        <input type="text" class="form-control" name="name" value="<?= e($p['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <input type="text" class="form-control" name="description" value="<?= e($p['description']) ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Thời lượng (phút)</label>
                            <input type="number" class="form-control" name="duration_minutes" value="<?= (int)$p['duration_minutes'] ?>" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Giá (đ)</label>
                            <input type="number" class="form-control" name="price" value="<?= (float)$p['price'] ?>" min="0" step="1000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giới hạn sảnh</label>
                        <input type="text" class="form-control" name="provider_code" value="<?= e($p['provider_code']) ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_free_trial" value="1" <?= $p['is_free_trial'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Gói trải nghiệm</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Đang bán</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
