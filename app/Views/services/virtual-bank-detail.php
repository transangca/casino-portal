<?php
/**
 * Chi tiết tài khoản ngân hàng ảo (trang đầy đủ).
 *
 * @var array  $account
 * @var array  $remote
 * @var string|null $secret
 * @var string|null $otp
 */
$detail = is_array($remote['data'] ?? null) ? $remote['data'] : [];
?>
<div class="container py-4" style="max-width:900px">
    <a href="<?= url('/services/virtual-bank?tab=accounts') ?>" class="btn btn-outline-light btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
    </a>

    <div class="panel-card">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pb-3 border-bottom">
            <?php if (!empty($account['avatar'])): ?>
                <img src="<?= e((string)$account['avatar']) ?>" alt="" class="vbank-avatar" style="width:60px;height:60px">
            <?php else: ?>
                <span class="vbank-avatar vbank-avatar-fallback" style="width:60px;height:60px">
                    <i class="fas fa-building-columns"></i>
                </span>
            <?php endif; ?>
            <div>
                <h1 class="page-title mb-1">
                    <?= e((string)($account['bank_name'] ?: $account['bank_type'])) ?>
                </h1>
                <div class="text-muted-2 small">
                    <?= e((string)($account['bank_username'] ?: 'Chưa đặt tên')) ?>
                    · <?= e((string)($account['bank_number'] ?: '')) ?>
                </div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted-2">Số dư hiện tại</div>
                <div class="fw-bold text-gold" style="font-size:1.5rem"><?= money((float)$account['account_balance']) ?></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Thông tin tài khoản</h6>
                <table class="table table-sm small">
                    <tr><td class="text-muted-2" style="width:40%">Loại ngân hàng</td><td><?= e((string)$account['bank_type']) ?></td></tr>
                    <tr><td class="text-muted-2">Số điện thoại</td><td class="font-numeric"><?= e((string)($account['phone'] ?: '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Số tài khoản</td><td class="font-numeric"><?= e((string)($account['bank_number'] ?: '—')) ?></td></tr>
                    <tr><td class="text-muted-2">Trạng thái</td><td><?= (int)$account['status'] === 1 ? 'Hoạt động' : 'Tạm khoá' ?></td></tr>
                    <tr><td class="text-muted-2">Hết hạn</td><td><?= e((string)($account['expired_at'] ?: '—')) ?></td></tr>
                    <tr>
                        <td class="text-muted-2">Mật khẩu</td>
                        <td>
                            <?php if ($secret !== null && $secret !== ''): ?>
                                <code class="text-gold"><?= e($secret) ?></code>
                            <?php else: ?>
                                <span class="text-muted-2">Chưa lưu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted-2">OTP</td>
                        <td>
                            <?php if ($otp !== null && $otp !== ''): ?>
                                <code class="text-gold"><?= e($otp) ?></code>
                            <?php else: ?>
                                <span class="text-muted-2">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h6 class="mb-2 mt-4"><i class="fas fa-pen me-2"></i>Cập nhật thông tin</h6>
                <form method="POST" action="<?= url('/services/virtual-bank/update') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Tên chủ tài khoản</label>
                            <input type="text" name="bank_username" class="form-control form-control-sm"
                                   value="<?= e((string)$account['bank_username']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số tài khoản</label>
                            <input type="text" name="bank_number" class="form-control form-control-sm font-numeric"
                                   value="<?= e((string)$account['bank_number']) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Số dư</label>
                            <input type="number" name="account_balance" class="form-control form-control-sm font-numeric"
                                   value="<?= (float)$account['account_balance'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mật khẩu</label>
                            <input type="text" name="password_decryption" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">OTP</label>
                            <input type="text" name="password_level_two" class="form-control form-control-sm">
                        </div>
                    </div>
                    <button class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i>Lưu & đồng bộ lên API</button>
                </form>
            </div>

            <div class="col-lg-6">
                <h6 class="mb-2"><i class="fas fa-image me-2"></i>Ảnh đại diện</h6>
                <div class="d-flex flex-wrap gap-2 align-items-start mb-4">
                    <form method="POST" action="<?= url('/services/virtual-bank/upload-avatar') ?>"
                          enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                        <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*" required>
                        <button class="btn btn-outline-gold btn-sm">Upload</button>
                    </form>
                    <form method="POST" action="<?= url('/services/virtual-bank/remove-avatar') ?>"
                          data-confirm="Xoá ảnh đại diện?" class="w-100 mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm">Xoá ảnh đại diện</button>
                    </form>
                </div>

                <h6 class="mb-2"><i class="fas fa-code me-2"></i>Dữ liệu trực tiếp từ API</h6>
                <?php if ($detail): ?>
                    <pre class="vbank-raw"><?= e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                <?php elseif (!$remote['ok']): ?>
                    <div class="alert alert-warning mb-0">
                        <?= e((string)($remote['message'] ?? 'Không lấy được dữ liệu từ API.')) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">API không trả về dữ liệu chi tiết.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
