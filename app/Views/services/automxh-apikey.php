<?php
/**
 * Quản lý API key Auto MXH của khách hàng.
 *
 * @var array  $keys
 * @var string $primary
 */
?>
<div class="container py-4" style="max-width:820px">

    <a href="<?= url('/services/automxh') ?>" class="btn btn-outline-light btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Quay lại dịch vụ
    </a>

    <div class="panel-card">
        <h1 class="page-title mb-1"><i class="fas fa-key me-2 text-gold"></i>API key Auto MXH của tôi</h1>
        <p class="page-sub">
            API key riêng của bạn tại hệ thống. Key này được dùng để tạo đơn,
            kiểm tra trạng thái và xem số dư ví chính thay bạn.
        </p>

        <div class="alert alert-info small">
            <i class="fas fa-circle-info me-1"></i>
            Bạn có thể dùng chung key với các dịch vụ khác của hệ thống.
            Nếu đã có key, chỉ cần dán lại vào đây — hệ thống sẽ tự nhận diện.
        </div>

        <form method="POST" action="<?= url('/services/automxh/apikey/save') ?>" class="mt-3">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="apikey">API key</label>
                <input type="text" name="apikey" id="apikey" class="form-control font-numeric" required
                       placeholder="ttmmo_apikey_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                       value="">
                <div class="form-hint">Dán key của bạn. Hệ thống sẽ dùng key này cho mọi đơn Auto MXH của bạn.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="label">Nhãn (tuỳ chọn)</label>
                <input type="text" name="label" id="label" class="form-control" maxlength="140"
                       placeholder="VD: key chính, key dự phòng...">
            </div>
            <button class="btn btn-gold"><i class="fas fa-plus me-1"></i>Lưu API key</button>
        </form>
    </div>

    <?php if ($keys): ?>
        <div class="panel-card mt-4">
            <h6 class="mb-3"><i class="fas fa-list me-2"></i>Key đã lưu (<?= count($keys) ?>)</h6>

            <div class="aic-order-list">
                <?php foreach ($keys as $k): ?>
                    <div class="aic-order-item">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <i class="fas fa-key text-gold"></i>
                            <div class="min-w-0 flex-fill">
                                <div class="small fw-semibold font-numeric text-truncate">
                                    <?= e((string)$k['apikey']) ?>
                                </div>
                                <div class="form-hint">
                                    <?= e((string)($k['label'] ?: '—')) ?>
                                    · Thêm lúc <?= e(date('d/m/Y', strtotime((string)$k['created_at']))) ?>
                                </div>
                            </div>
                            <?php if ((string)$k['apikey'] === $primary): ?>
                                <span class="badge-status st-active"><i class="fas fa-check me-1"></i>Đang dùng</span>
                            <?php else: ?>
                                <form method="POST" action="<?= url('/services/automxh/apikey/primary') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                                    <button class="btn btn-sm btn-outline-gold">Dùng key này</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="<?= url('/services/automxh/apikey/delete') ?>"
                                  onsubmit="return confirm('Xoá API key này?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Xoá">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel-card mt-4">
        <h6 class="mb-2"><i class="fas fa-terminal me-2"></i>Dùng key trực tiếp qua API</h6>
        <div class="aic-delivery small font-numeric">
<pre class="mb-0" style="color:#c9c9d4">curl '<?= e(url('/api/external/automxh/services')) ?>' \
  --header 'x-api-key: KEY_CUA_BAN'</pre>
        </div>
        <div class="form-hint mt-2">
            Hệ thống cung cấp proxy trùng endpoint chuẩn của Auto MXH — dùng API key của bạn
            để gọi trực tiếp:
            <code>/services</code>, <code>/catalog</code>, <code>/category/{slug}</code>,
            <code>/quote</code>, <code>/order</code>, <code>/status</code>,
            <code>/orders</code>, <code>/balance</code>.
            Xác thực bằng header <code>x-api-key</code> hoặc <code>Authorization: Bearer</code>.
        </div>
    </div>
</div>
