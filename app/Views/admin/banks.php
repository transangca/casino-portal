<?php /** @var array $banks */ $adminPage = 'banks'; ?>
<h1 class="page-title">Tài khoản ngân hàng nhận tiền (VietQR)</h1>
<p class="page-sub">Cấu hình tài khoản ngân hàng nhận tiền — dùng để tạo mã QR nạp tiền tự động.</p>

<div class="alert alert-info">
    <i class="fas fa-circle-info me-1"></i>
    Mã <strong>BIN / acq_id</strong> là mã ngân hàng 6 số (VD MB = <code>970436</code>).
    Có thể tra tại <a href="https://api.vietqr.io/v2/banks" target="_blank" rel="noopener">api.vietqr.io/v2/banks</a>.
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-building-columns text-gold me-2"></i>Thêm tài khoản</h3>

            <form method="POST" action="<?= url('/admin/banks/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="0">

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label" for="bank_code">Mã NH</label>
                        <input type="text" class="form-control" id="bank_code" name="bank_code" required placeholder="MB">
                    </div>
                    <div class="col-8">
                        <label class="form-label" for="bank_name">Tên ngân hàng</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="MB Bank">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="account_no">Số tài khoản</label>
                    <input type="text" class="form-control font-numeric" id="account_no" name="account_no" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="account_name">Chủ tài khoản</label>
                    <input type="text" class="form-control" id="account_name" name="account_name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="acq_id">Mã BIN (acq_id)</label>
                    <input type="text" class="form-control font-numeric" id="acq_id" name="acq_id" required placeholder="970436">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label" for="logo">Logo (URL)</label>
                        <input type="text" class="form-control" id="logo" name="logo" placeholder="https://...">
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="sort_order">Thứ tự</label>
                        <input type="number" class="form-control font-numeric" id="sort_order" name="sort_order" value="0">
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">Hiển thị trong trang nạp tiền</label>
                </div>

                <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <h3><i class="fas fa-list text-gold me-2"></i>Danh sách tài khoản</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Ngân hàng</th><th>Số tài khoản</th><th>Chủ tài khoản</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$banks): ?>
                        <tr><td colspan="5" class="text-center text-muted-2 py-4">Chưa cấu hình tài khoản nhận tiền.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($banks as $b): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($b['bank_name']) ?></div>
                                <div class="form-hint"><?= e($b['bank_code']) ?> · <?= e($b['acq_id']) ?></div>
                            </td>
                            <td class="font-numeric small"><?= e($b['account_no']) ?></td>
                            <td class="small"><?= e($b['account_name']) ?></td>
                            <td>
                                <span class="badge-status st-<?= $b['is_active'] ? 'active' : 'closed' ?>">
                                    <?= $b['is_active'] ? 'Active' : 'Off' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="<?= url('/admin/banks/delete') ?>"
                                      data-confirm="Xoá tài khoản ngân hàng?" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
