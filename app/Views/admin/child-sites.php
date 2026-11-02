<?php /** @var array $sites */ $adminPage = 'child-sites'; ?>
<h1 class="page-title">Quản lý Website con</h1>
<p class="page-sub">Duyệt / phê duyệt website con, chỉnh % ăn chia và điều kiện nạp tiền.</p>

<?php foreach ($sites as $s): ?>
    <form id="f<?= (int)$s['id'] ?>" method="POST" action="<?= url('/admin/child-sites/update') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    </form>
<?php endforeach; ?>

<div class="panel-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th>Website</th><th>Chủ site</th><th>Domain</th>
                <th class="text-end">Ăn chia</th><th class="text-end">Nạp tối thiểu</th>
                <th>Lượt truy cập</th><th>Trạng thái</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$sites): ?>
                <tr><td colspan="8" class="text-center text-muted-2 py-4">Chưa có website con nào.</td></tr>
            <?php endif; ?>
            <?php foreach ($sites as $s): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($s['name']) ?></div>
                        <div class="form-hint">/<?= e($s['slug']) ?></div>
                    </td>
                    <td class="small"><?= e($s['username']) ?></td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="domain"
                               form="f<?= (int)$s['id'] ?>"
                               value="<?= e((string)($s['domain'] ?? '')) ?>" placeholder="domain.com">
                    </td>
                    <td class="text-end">
                        <div class="input-group input-group-sm" style="width:120px">
                            <input type="number" step="0.1" min="0" max="100" class="form-control font-numeric"
                                   name="rev_share" form="f<?= (int)$s['id'] ?>"
                                   value="<?= e((string)(float)$s['rev_share']) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </td>
                    <td class="text-end">
                        <input type="number" step="1000" min="0" class="form-control form-control-sm font-numeric"
                               name="min_deposit" form="f<?= (int)$s['id'] ?>"
                               value="<?= e((string)(float)$s['min_deposit']) ?>">
                    </td>
                    <td class="font-numeric small"><?= (int)$s['hits'] ?></td>
                    <td>
                        <select class="form-select form-select-sm" name="status" form="f<?= (int)$s['id'] ?>">
                            <option value="pending"   <?= $s['status'] === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                            <option value="active"    <?= $s['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="suspended" <?= $s['status'] === 'suspended' ? 'selected' : '' ?>>Tạm khoá</option>
                        </select>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="require_deposit" value="1"
                                   id="rd_<?= (int)$s['id'] ?>" form="f<?= (int)$s['id'] ?>"
                                   <?= $s['require_deposit'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="rd_<?= (int)$s['id'] ?>">Bắt buộc nạp</label>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" form="f<?= (int)$s['id'] ?>">
                            <i class="fas fa-save"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info mt-3">
    <i class="fas fa-circle-info me-1"></i>
    Mặc định người dùng cần đạt <strong>VIP <?= (int)\App\Services\ChildSiteService::minVipLevel() ?></strong> để tạo website con.
    Trần % ăn chia tối đa:
    <strong><?= number_format(\App\Services\ChildSiteService::maxRevShare() * 100, 1) ?>%</strong>
    (đặt tại Admin → Cấu hình).
</div>
