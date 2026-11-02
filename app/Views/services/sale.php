<?php /** @var array $platforms,$statuses,$accStatuses,$accounts,$campaigns,$proxies,$imports,$verifyRequired */ ?>
<?php
$brandMap = \App\Services\SaleService::PLATFORM_BRANDS;
$accStatusOf = static function (array $a) use ($accStatuses): string {
    $st = (string)($a['status'] ?? 'pending');
    if ($st === '' || !isset($accStatuses[$st])) {
        $st = 'pending';
    }
    return $accStatuses[$st];
};
?>
<div class="container py-4">
    <h1 class="page-title"><i class="fas fa-paper-plane me-2"></i>Dịch vụ Sale &amp; tin nhắn hàng loạt</h1>
    <p class="page-sub">
        Thêm tài khoản Telegram / Facebook / Zalo, gắn proxy và chạy chiến dịch gửi tin nhắn hàng loạt
        với giới hạn an toàn theo ngày.
    </p>

    <div class="alert alert-warning small">
        <i class="fas fa-triangle-exclamation me-1"></i>
        Chỉ sử dụng cho mục đích marketing với sự đồng ý của người nhận. Vi phạm chính sách nền tảng
        có thể khiến tài khoản bị khoá — hệ thống tự giới hạn tốc độ để hạn chế rủi ro này.
    </div>

    <!-- ============ DANH MỤC NỀN TẢNG (LOGO 3D) ============ -->
    <div class="panel-card mb-4">
        <h6 class="mb-3"><i class="fas fa-layer-group me-2 text-gold"></i>Danh mục nền tảng</h6>
        <div class="brand-grid">
            <?php foreach ($platforms as $code => $label): ?>
                <?php $logoUrl = brand_logo_url($brandMap[$code] ?? $code); ?>
                <div class="brand-card<?= ($logoUrl === '') ? '' : ' is-brand' ?>">
                    <span class="brand-icon">
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= e($logoUrl) ?>" alt="<?= e($label) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <i class="fas fa-<?= $code === 'email' ? 'envelope' : ($code === 'sms' ? 'comment-sms' : 'paper-plane') ?>"></i>
                        <?php endif; ?>
                    </span>
                    <span class="brand-name"><?= e($label) ?></span>
                    <span class="brand-sub"><?= count(array_filter($accounts, static fn($a) => $a['platform'] === $code)) ?> tài khoản</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Cột trái -->
        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-user-secret me-2 text-gold"></i>Thêm tài khoản gửi</h6>
                <form method="POST" action="<?= url('/services/sale/account') ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Nền tảng</label>
                        <select name="platform" class="form-select form-select-sm" id="salePlatform">
                            <?php foreach ($platforms as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tài khoản (sđt / username / email)</label>
                        <input type="text" name="account" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Mật khẩu / Token / App password</label>
                        <input type="password" name="secret" class="form-control form-control-sm" required>
                        <div class="form-text">Được mã hóa AES-256-GCM trước khi lưu vào database.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Giới hạn/ngày</label>
                            <input type="number" name="daily_limit" class="form-control form-control-sm" value="500" min="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Proxy</label>
                            <select name="proxy_id" class="form-select form-control-sm">
                                <option value="0">Không dùng proxy</option>
                                <?php foreach ($proxies as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= e((string)$p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="saleVerifyCode">
                            Mã xác minh 6 số <span class="form-hint">(nếu ứng dụng đã gửi)</span>
                        </label>
                        <input type="text" id="saleVerifyCode" name="code"
                               class="form-control form-control-sm sale-code-input"
                               inputmode="numeric" maxlength="6" pattern="\d{6}"
                               placeholder="000000" autocomplete="one-time-code">
                        <div class="form-text">
                            <i class="fas fa-shield-halved me-1"></i>
                            Bắt buộc xác minh bằng mã ứng dụng gửi về trước khi chạy chiến dịch —
                            chống spam và tài khoản ảo.
                        </div>
                    </div>
                    <button class="btn btn-gold btn-sm w-100"><i class="fas fa-plus me-1"></i>Thêm tài khoản</button>
                </form>
            </div>

            <!-- ============ IMPORT FILE ============ -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-file-arrow-up me-2 text-gold"></i>Import dữ liệu hàng loạt</h6>
                <p class="small text-muted-2">
                    Tải file <code>.txt</code> hoặc <code>.csv</code> — mỗi dòng một tài khoản theo định dạng:
                    <code>tai-khoan|mat-khau|proxy</code> (hoặc phân tách bằng dấu <code>,</code> / tab).
                </p>
                <form method="POST" action="<?= url('/services/sale/import') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Nền tảng áp dụng</label>
                        <select name="platform" class="form-select form-select-sm">
                            <?php foreach ($platforms as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Chọn file (.txt / .csv — tối đa 5MB)</label>
                        <input type="file" name="data_file" accept=".txt,.csv,text/plain,text/csv" class="form-control form-control-sm" required>
                    </div>
                    <button class="btn btn-outline-gold btn-sm w-100"><i class="fas fa-upload me-1"></i>Import tài khoản</button>
                </form>

                <?php if (!empty($imports)): ?>
                    <hr class="footer-divider">
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>File</th><th>SV</th><th class="text-end">Thêm</th><th class="text-end">Bỏ qua</th></tr></thead>
                            <tbody>
                                <?php foreach ($imports as $im): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width:140px"><?= e((string)$im['filename']) ?></td>
                                        <td><?= e($platforms[$im['platform']] ?? (string)$im['platform']) ?></td>
                                        <td class="text-end"><?= (int)$im['valid_rows'] ?></td>
                                        <td class="text-end"><?= (int)$im['invalid_rows'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cột phải -->
        <div class="col-lg-7">
            <!-- ============ DANH SÁCH TÀI KHOẢN + XÁC MINH ============ -->
            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-shield-halved me-2 text-gold"></i>Tài khoản &amp; xác minh</h6>
                <?php if (!$accounts): ?>
                    <p class="text-muted-2 small mb-0">Chưa có tài khoản nào. Hãy thêm tài khoản hoặc import file.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Tài khoản</th>
                                    <th class="text-end">SV</th>
                                    <th class="text-end">Limit</th>
                                    <th>Xác minh</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $a): ?>
                                    <?php
                                    $accStatus = (string)($a['status'] ?? 'pending');
                                    if ($accStatus === '' || !isset($accStatuses[$accStatus])) {
                                        $accStatus = 'pending';
                                    }
                                    $pCode = (string)$a['platform'];
                                    $logoUrl = brand_logo_url($brandMap[$pCode] ?? $pCode);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($logoUrl !== ''): ?>
                                                    <img src="<?= e($logoUrl) ?>" alt="" class="sale-acc-logo" loading="lazy">
                                                <?php endif; ?>
                                                <div>
                                                    <div><?= e((string)$a['account']) ?></div>
                                                    <div class="small text-muted-2"><?= e($platforms[$pCode] ?? $pCode) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end"><?= (int)$a['sent_today'] ?></td>
                                        <td class="text-end"><?= (int)$a['daily_limit'] ?></td>
                                        <td>
                                            <span class="badge sale-status-badge sale-status-<?= e($accStatus) ?>">
                                                <?= e($accStatuses[$accStatus]) ?>
                                            </span>
                                            <?php if (!empty($a['last_error'])): ?>
                                                <div class="small text-danger-2"><?= e((string)$a['last_error']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($accStatus !== 'verified'): ?>
                                                <form method="POST" action="<?= url('/services/sale/verify/send') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-gold" title="Gửi mã xác minh">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?= url('/services/sale/verify/reset') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" title="Đặt lại">
                                                        <i class="fas fa-rotate-left"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= url('/services/sale/account/delete') ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="Xoá">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php if ($accStatus !== 'verified'): ?>
                                        <tr class="sale-verify-row">
                                            <td colspan="5">
                                                <form method="POST" action="<?= url('/services/sale/verify/submit') ?>"
                                                      class="d-flex align-items-center gap-2 flex-wrap">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                    <label class="small mb-0" for="vc<?= (int)$a['id'] ?>">Nhập mã 6 số đã gửi tới ứng dụng:</label>
                                                    <input type="text" id="vc<?= (int)$a['id'] ?>" name="code"
                                                           class="form-control form-control-sm sale-code-input"
                                                           inputmode="numeric" maxlength="6" pattern="\d{6}"
                                                           placeholder="000000" autocomplete="one-time-code">
                                                    <button class="btn btn-sm btn-gold"><i class="fas fa-check me-1"></i>Xác minh</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card mb-4">
                <h6 class="mb-3"><i class="fas fa-bullhorn me-2 text-gold"></i>Tạo chiến dịch</h6>
                <form method="POST" action="<?= url('/services/sale/campaign') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Tài khoản gửi</label>
                        <select name="account_id" class="form-select form-select-sm" required>
                            <option value="">-- Chọn tài khoản --</option>
                            <?php foreach ($accounts as $a): ?>
                                <?php $st = (string)($a['status'] ?? 'pending'); ?>
                                <option value="<?= (int)$a['id'] ?>"<?= ($st !== 'verified' && $verifyRequired) ? ' disabled' : '' ?>>
                                    <?= e((string)$a['account']) ?> (<?= e($platforms[$a['platform']] ?? (string)$a['platform']) ?>)
                                    <?= ($st !== 'verified' && $verifyRequired) ? ' — chưa xác minh' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($verifyRequired): ?>
                            <div class="form-text">Chỉ tài khoản đã xác minh mới được chạy chiến dịch.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nội dung tin nhắn</label>
                        <textarea name="message" rows="4" class="form-control form-control-sm" required
                                  placeholder="Nội dung sẽ gửi cho từng người nhận..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Danh sách người nhận (mỗi dòng 1 số điện thoại / id)</label>
                        <textarea name="target_list" rows="6" class="form-control form-control-sm font-monospace" required
                                  placeholder="0912345678&#10;0987654321"></textarea>
                    </div>
                    <button class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i>Tạo chiến dịch</button>
                </form>
            </div>

            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-list-check me-2 text-gold"></i>Chiến dịch</h6>
                <?php if (!$campaigns): ?>
                    <p class="text-muted-2 small mb-0">Chưa có chiến dịch nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>#</th><th>Tài khoản</th><th class="text-end">Tổng</th><th class="text-end">Đã gửi</th><th>Trạng thái</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($campaigns as $c): ?>
                                    <tr>
                                        <td><?= (int)$c['id'] ?></td>
                                        <td class="small"><?= e((string)($c['account'] ?? '—')) ?></td>
                                        <td class="text-end"><?= (int)$c['total'] ?></td>
                                        <td class="text-end"><?= (int)$c['sent'] ?></td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-25">
                                                <?= e($statuses[$c['status']] ?? (string)$c['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($c['status'] === 'running'): ?>
                                                <form method="POST" action="<?= url('/services/sale/campaign/stop') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger">Dừng</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= url('/services/sale/campaign/start') ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-gold">Chạy</button>
                                                </form>
                                            <?php endif; ?>
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
</div>
