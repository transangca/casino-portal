<?php
/**
 * Trang quản trị Auto Bank.
 *
 * @var array  $accounts
 * @var array  $transactions
 * @var array  $pending
 * @var array  $stats
 * @var array  $banks
 * @var array  $versions
 * @var string $tab
 * @var int    $accountId
 * @var string $txnType
 */
$adminPage = 'auto-bank';

$tabs = [
    'accounts'    => 'Tài khoản ngân hàng',
    'transactions'=> 'Giao dịch',
    'deposits'    => 'Nạp tiền chờ duyệt',
    'settings'    => 'Cấu hình',
];
?>
<h1 class="page-title">Auto Bank — Cổng thanh toán tự động</h1>
<p class="page-sub">
    Đọc lịch sử giao dịch từ 20+ ngân hàng &amp; ví điện tử qua API <code>api.sieuthicode.net</code>
    và tự động đối soát phiếu nạp tiền.
</p>

<!-- ===== Thẻ thống kê ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['accounts'] ?></div>
            <div class="stat-label">Tài khoản (<?= (int)$stats['active'] ?> bật)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['transactions'] ?></div>
            <div class="stat-label">Giao dịch đã đọc</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value text-gold"><?= number_format((float)$stats['volume_in']) ?> đ</div>
            <div class="stat-label">Tổng tiền vào</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['auto_approved'] ?></div>
            <div class="stat-label">Nạp tự động duyệt</div>
        </div>
    </div>
</div>

<!-- ===== Tabs ===== -->
<ul class="nav nav-tabs mb-4">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
               href="<?= url('/admin/auto-bank?tab=' . $key) ?>">
                <?= e($label) ?>
                <?php if ($key === 'deposits' && (int)$stats['pending'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?= (int)$stats['pending'] ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'accounts'): ?>
    <!-- ================= TAB: TÀI KHOẢN ================= -->
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card">
                <h3><i class="fas fa-plus-circle text-gold me-2"></i>Thêm / sửa tài khoản</h3>
                <p class="form-hint mb-3">Token &amp; mật khẩu ngân hàng được mã hoá AES-256-GCM trước khi lưu.</p>

                <form method="POST" action="<?= url('/admin/auto-bank/save') ?>" id="accForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="accId" value="">

                    <div class="mb-3">
                        <label class="form-label" for="bank_key">Ngân hàng / ví</label>
                        <select class="form-select" id="bank_key" name="bank_key" required>
                            <option value="">— Chọn —</option>
                            <?php foreach ($banks as $key => $b): ?>
                                <option value="<?= e($key) ?>" data-versions='<?= e(json_encode(array_keys($b['versions']))) ?>'>
                                    <?= e($b['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="api_version">Phiên bản API</label>
                        <select class="form-select" id="api_version" name="api_version">
                            <?php foreach ($versions as $k => $label): ?>
                                <option value="<?= e($k) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="label">Tên hiển thị</label>
                        <input type="text" class="form-control" id="label" name="label" placeholder="VD: TK nhận tiền chính">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="token">Token API <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-numeric" id="token" name="token"
                               placeholder="Mã Token trong dashboard sieuthicode">
                        <div class="form-hint">Để trống khi sửa = giữ nguyên token cũ.</div>
                    </div>

                    <div class="mb-3" id="pwdWrap" style="display:none">
                        <label class="form-label" for="password">Mật khẩu ngân hàng</label>
                        <input type="text" class="form-control font-numeric" id="password" name="password"
                               placeholder="Chỉ dùng cho API V3">
                        <div class="form-hint">Để trống khi sửa = giữ nguyên mật khẩu cũ.</div>
                    </div>

                    <div class="mb-3" id="stkWrap" style="display:none">
                        <label class="form-label" for="stk">Số tài khoản (STK)</label>
                        <input type="text" class="form-control font-numeric" id="stk" name="stk"
                               placeholder="Chỉ dùng cho API V3">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="match_content">Từ khoá trong nội dung (khớp thủ công)</label>
                        <input type="text" class="form-control" id="match_content" name="match_content"
                               placeholder="VD: NAP, STCPAY, nạp tiền">
                        <div class="form-hint">Dùng khi không có mã giao dịch trong nội dung.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="min_amount">Số tiền tối thiểu</label>
                            <input type="number" class="form-control font-numeric" id="min_amount" name="min_amount" value="0" min="0" step="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="max_amount">Số tiền tối đa</label>
                            <input type="number" class="form-control font-numeric" id="max_amount" name="max_amount" value="0" min="0" step="1">
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="auto_approve" name="auto_approve" value="1" checked>
                        <label class="form-check-label" for="auto_approve">Cho phép tự động duyệt nạp tiền</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Kích hoạt tài khoản</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_sandbox" name="is_sandbox" value="1">
                        <label class="form-check-label" for="is_sandbox">
                            Chế độ Sandbox (dữ liệu mẫu, không cần token thật)
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill"><i class="fas fa-save me-2"></i>Lưu</button>
                        <button type="button" class="btn btn-outline-gold" id="btnTest">
                            <i class="fas fa-plug me-1"></i>Test
                        </button>
                        <button type="button" class="btn btn-outline-light" id="btnTestSandbox"
                            title="Gọi sandbox playground, không cần token thật">
                            <i class="fas fa-flask me-1"></i>Sandbox
                        </button>
                    </div>
                    <div id="testResult" class="mt-2"></div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="mb-0"><i class="fas fa-list text-gold me-2"></i>Danh sách tài khoản</h3>
                    <form method="POST" action="<?= url('/admin/auto-bank/sync-all') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-gold" data-confirm="Đồng bộ toàn bộ tài khoản?">
                            <i class="fas fa-sync me-1"></i>Đồng bộ tất cả
                        </button>
                    </form>
                </div>

                <?php if (!$accounts): ?>
                    <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có tài khoản nào.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Tài khoản</th><th>Phiên bản</th><th>Đồng bộ gần nhất</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                        <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small">
                                        <?= e((string)$a['label']) ?>
                                        <?php if ((int)($a['is_sandbox'] ?? 0) === 1): ?>
                                            <span class="badge-status st-pending">SANDBOX</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-hint"><?= e((string)$a['bank_name']) ?><?= $a['stk'] ? ' · ' . e((string)$a['stk']) : '' ?></div>
                                </td>
                                <td><span class="badge-status st-active"><?= e(strtoupper((string)$a['api_version'])) ?></span></td>
                                <td class="small text-muted-2">
                                    <?= $a['last_sync_at'] ? date('d/m H:i', strtotime((string)$a['last_sync_at'])) : '-' ?>
                                    <?php if ((int)$a['last_sync_count'] > 0): ?>
                                        <br><small>(<?= (int)$a['last_sync_count'] ?> giao dịch)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status st-<?= (int)$a['is_active'] === 1 ? 'active' : 'closed' ?>">
                                        <?= (int)$a['is_active'] === 1 ? 'Bật' : 'Tắt' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-gold edit-acc"
                                            data-id="<?= (int)$a['id'] ?>"
                                            data-bank="<?= e((string)$a['bank_key']) ?>"
                                            data-version="<?= e((string)$a['api_version']) ?>"
                                            data-label="<?= e((string)$a['label']) ?>"
                                            data-stk="<?= e((string)($a['stk'] ?? '')) ?>"
                                            data-match="<?= e((string)($a['match_content'] ?? '')) ?>"
                                            data-min="<?= e((string)$a['min_amount']) ?>"
                                            data-max="<?= e((string)$a['max_amount']) ?>"
                                            data-auto="<?= (int)$a['auto_approve'] ?>"
                                            data-sandbox="<?= (int)($a['is_sandbox'] ?? 0) ?>"
                                            data-active="<?= (int)$a['is_active'] ?>">Sửa</button>

                                        <form method="POST" action="<?= url('/admin/auto-bank/sync') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <button class="btn btn-sm btn-primary"><i class="fas fa-sync"></i></button>
                                        </form>

                                        <form method="POST" action="<?= url('/admin/auto-bank/delete') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" data-confirm="Xoá tài khoản này?"><i class="fas fa-trash"></i></button>
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

<?php elseif ($tab === 'transactions'): ?>
    <!-- ================= TAB: GIAO DỊCH ================= -->
    <div class="panel-card">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h3 class="mb-0"><i class="fas fa-receipt text-gold me-2"></i>Lịch sử giao dịch</h3>

            <form method="GET" action="<?= url('/admin/auto-bank') ?>" class="ms-auto d-flex gap-2">
                <input type="hidden" name="tab" value="transactions">
                <select name="account_id" class="form-select form-select-sm" style="width:auto">
                    <option value="0">Tất cả tài khoản</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e((string)$a['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-select form-select-sm" style="width:auto">
                    <option value="">Tất cả loại</option>
                    <option value="IN" <?= $txnType === 'IN' ? 'selected' : '' ?>>Tiền vào</option>
                    <option value="OUT" <?= $txnType === 'OUT' ? 'selected' : '' ?>>Tiền ra</option>
                </select>
                <button class="btn btn-sm btn-outline-gold">Lọc</button>
            </form>
        </div>

        <?php if (!$transactions): ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có giao dịch nào.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Thời gian</th><th>Mã GD</th><th>Nội dung</th><th>Loại</th><th>Số tiền</th><th>Số dư</th><th>Đối soát</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="small text-muted-2 font-numeric">
                            <?= $t['transaction_date'] ? date('d/m/Y H:i', strtotime((string)$t['transaction_date'])) : '-' ?>
                        </td>
                        <td class="small font-numeric"><?= e((string)($t['transaction_id'] ?: '-')) ?></td>
                        <td class="small" style="max-width:320px">
                            <div class="text-truncate" title="<?= e((string)$t['description']) ?>"><?= e((string)$t['description']) ?></div>
                            <div class="form-hint"><?= e((string)($t['label'] ?? '')) ?></div>
                        </td>
                        <td>
                            <span class="badge-status st-<?= (string)$t['type'] === 'IN' ? 'active' : 'closed' ?>">
                                <?= (string)$t['type'] === 'IN' ? 'Vào' : 'Ra' ?>
                            </span>
                        </td>
                        <td class="small fw-semibold <?= (string)$t['type'] === 'IN' ? 'text-gold' : 'text-danger' ?>">
                            <?= (string)$t['type'] === 'IN' ? '+' : '-' ?><?= number_format((float)$t['amount'], 0, ',', '.') ?>
                            <?= (string)$t['currency'] !== 'VND' ? e((string)$t['currency']) : 'đ' ?>
                        </td>
                        <td class="small text-muted-2 font-numeric">
                            <?= $t['balance'] !== null ? number_format((float)$t['balance'], 0, ',', '.') : '-' ?>
                        </td>
                        <td class="small">
                            <?php if ($t['matched_deposit']): ?>
                                <span class="badge-status st-active">Phiếu #<?= (int)$t['matched_deposit'] ?></span>
                            <?php else: ?>
                                <span class="text-muted-2">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'deposits'): ?>
    <!-- ================= TAB: NẠP TIỀN CHỜ DUYỆT ================= -->
    <div class="panel-card">
        <h3><i class="fas fa-money-bill-wave text-gold me-2"></i>Phiếu nạp tiền đang chờ</h3>
        <p class="form-hint mb-3">
            Auto Bank tự động duyệt khi phát hiện giao dịch "tiền vào" khớp số tiền + mã giao dịch.
            Bật trong <a href="<?= url('/admin/auto-bank?tab=settings') ?>">Cấu hình</a>.
        </p>

        <?php if (!$pending): ?>
            <div class="empty-state py-4"><i class="fas fa-check-circle text-success"></i><p>Không có phiếu nào đang chờ.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>#</th><th>User</th><th>Mã GD</th><th>Số tiền</th><th>Thưởng</th><th>PP</th><th>Thời gian</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($pending as $d): ?>
                    <tr>
                        <td class="text-secondary"><?= (int)$d['id'] ?></td>
                        <td><?= e((string)$d['username']) ?></td>
                        <td><code><?= e((string)$d['transaction_code']) ?></code></td>
                        <td class="text-gold"><?= money((float)$d['amount']) ?></td>
                        <td class="small"><?= (float)$d['bonus_amount'] > 0 ? '+' . money((float)$d['bonus_amount']) : '-' ?></td>
                        <td class="text-secondary small"><?= e(strtoupper((string)$d['method'])) ?></td>
                        <td class="text-secondary small"><?= date('d/m/Y H:i', strtotime((string)$d['created_at'])) ?></td>
                        <td>
                            <form method="POST" action="<?= url('/admin/auto-bank/approve-deposit') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="btn btn-sm btn-primary" data-confirm="Duyệt phiếu nạp tiền này?">Duyệt</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ================= TAB: CẤU HÌNH ================= -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card">
                <h3><i class="fas fa-cog text-gold me-2"></i>Cấu hình chung</h3>
                <form method="POST" action="<?= url('/admin/auto-bank/save-settings') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="base_url">Base URL</label>
                        <input type="text" class="form-control font-numeric" id="base_url" name="base_url" value="<?= e($baseUrl) ?>">
                        <div class="form-hint">Mặc định: https://api.sieuthicode.net</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="poll_interval">Chu kỳ đồng bộ (giây)</label>
                        <input type="number" class="form-control font-numeric" id="poll_interval" name="poll_interval"
                               value="<?= (int)setting('autobank_poll_interval', '30') ?>" min="5" max="600" step="5">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="cron_token">Token Cron</label>
                        <input type="text" class="form-control font-numeric" id="cron_token" name="cron_token"
                               value="<?= e((string)setting('autobank_cron_token', '')) ?>"
                               placeholder="VD: 8f3a... (bấm nút bên dưới để sinh)">
                        <div class="form-hint">
                            Dùng cho URL cron: <code><?= e(rtrim($baseUrl, '/')) ?>/api/auto-bank/cron?token=...</code>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-gold mt-1" id="btnGenToken">
                            <i class="fas fa-rotate me-1"></i>Sinh token mới
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="service_fee">Phí dịch vụ (đ)</label>
                        <input type="number" class="form-control font-numeric" id="service_fee" name="service_fee"
                               value="<?= (int)setting('autobank_service_fee', '0') ?>" min="0" step="100">
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="service_on" name="service_on" value="1"
                            <?= setting('autobank_service_on', '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="service_on">Bật dịch vụ Auto Bank</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="auto_approve" name="auto_approve" value="1"
                            <?= setting('autobank_auto_approve', '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_approve">
                            Tự động duyệt nạp tiền khi đối soát khớp
                        </label>
                    </div>

                    <button class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Lưu cấu hình</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card">
                <h3><i class="fas fa-book text-gold me-2"></i>Danh sách endpoint</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Ngân hàng</th><th>Phiên bản</th><th>Endpoint</th></tr></thead>
                        <tbody>
                        <?php foreach ($banks as $key => $b): ?>
                            <?php foreach ($b['versions'] as $v => $meta): ?>
                                <tr>
                                    <td class="small"><?= e($b['label']) ?></td>
                                    <td><span class="badge-status st-active"><?= e(strtoupper($v)) ?></span></td>
                                    <td><code class="small">/<?= e($meta['path']) ?>/<?= e(implode('/', $meta['params'])) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="fas fa-circle-info me-1"></i>
                    <b>V1/V2/Shared</b>: chỉ cần Token.
                    <b>V3</b>: cần Mật khẩu + STK + Token (không lưu mật khẩu trên hệ thống đối tác khi dùng bản chuẩn hoá).
                </div>
            </div>

            <div class="panel-card mt-4">
                <h3><i class="fas fa-flask text-gold me-2"></i>Sandbox Playground</h3>
                <p class="form-hint mb-2">Dùng để kiểm tra kết nối mà không cần tài khoản ngân hàng thật.</p>
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text">GET</span>
                    <input type="text" class="form-control font-numeric" readonly
                           value="<?= e($baseUrl) ?>/sandbox/acb/sandbox_password/0123456789/sandbox_token_stcpay">
                </div>
                <button type="button" class="btn btn-sm btn-outline-gold" id="btnSandbox">
                    <i class="fas fa-play me-1"></i>Gọi thử sandbox
                </button>
                <div id="sandboxResult" class="mt-2"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    var bankSel = document.getElementById('bank_key');
    var verSel  = document.getElementById('api_version');
    var pwdWrap = document.getElementById('pwdWrap');
    var stkWrap = document.getElementById('stkWrap');

    function syncVersionVisibility() {
        var opt = bankSel ? bankSel.selectedOptions[0] : null;
        var versions = opt ? (JSON.parse(opt.dataset.versions || '[]')) : [];
        if (verSel) {
            // Ẩn phiên bản không được hỗ trợ
            Array.prototype.forEach.call(verSel.options, function (o) {
                o.hidden = versions.length > 0 && versions.indexOf(o.value) === -1;
                o.disabled = o.hidden;
            });
            if (versions.length && versions.indexOf(verSel.value) === -1) {
                verSel.value = versions[0];
            }
        }
        var needV3 = verSel && verSel.value === 'v3';
        if (pwdWrap) pwdWrap.style.display = needV3 ? '' : 'none';
        if (stkWrap) stkWrap.style.display = needV3 ? '' : 'none';
    }

    if (bankSel) bankSel.addEventListener('change', syncVersionVisibility);
    if (verSel) verSel.addEventListener('change', syncVersionVisibility);
    syncVersionVisibility();

    // ---- Sửa tài khoản ----
    document.querySelectorAll('.edit-acc').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('accId').value = btn.dataset.id;
            bankSel.value = btn.dataset.bank;
            verSel.value = btn.dataset.version;
            document.getElementById('label').value = btn.dataset.label || '';
            document.getElementById('stk').value = btn.dataset.stk || '';
            document.getElementById('match_content').value = btn.dataset.match || '';
            document.getElementById('min_amount').value = btn.dataset.min || 0;
            document.getElementById('max_amount').value = btn.dataset.max || 0;
            document.getElementById('auto_approve').checked = btn.dataset.auto === '1';
            document.getElementById('is_sandbox').checked = btn.dataset.sandbox === '1';
            document.getElementById('is_active').checked = btn.dataset.active === '1';
            document.getElementById('token').value = '';
            document.getElementById('password').value = '';
            syncVersionVisibility();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // ---- Test kết nối ----
    function doTest(sandbox) {
        var box = document.getElementById('testResult');
        box.innerHTML = '<div class="form-hint">Đang thử...</div>';
        var params = new URLSearchParams({
            bank_key: bankSel.value,
            api_version: verSel.value,
            token: document.getElementById('token').value,
            password: document.getElementById('password').value,
            stk: document.getElementById('stk').value,
            sandbox: sandbox ? '1' : '0'
        });
        fetch('<?= url('/admin/auto-bank/test') ?>' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) {
                    box.innerHTML = '<div class="alert alert-danger small mb-0 py-2">' + (j.message || 'Lỗi') + '</div>';
                    return;
                }
                var rows = (j.data && j.data.rows) || [];
                var html = '<div class="alert alert-success small mb-0 py-2"><b>OK</b> — ' + j.data.count + ' giao dịch</div>';
                if (rows.length) {
                    html += '<div class="table-responsive mt-2"><table class="data-table"><tbody>';
                    rows.forEach(function (r) {
                        html += '<tr><td class="small font-numeric">' + r.transactionID + '</td>'
                             + '<td class="small text-gold">' + Number(r.amount).toLocaleString('vi-VN') + '</td>'
                             + '<td class="small text-truncate" style="max-width:220px">' + r.description + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }
                box.innerHTML = html;
            })
            .catch(function () {
                box.innerHTML = '<div class="alert alert-danger small mb-0 py-2">Không kết nối được</div>';
            });
    }

    var testBtn = document.getElementById('btnTest');
    if (testBtn) {
        testBtn.addEventListener('click', function () { doTest(false); });
    }
    var sbBtn = document.getElementById('btnTestSandbox');
    if (sbBtn) {
        sbBtn.addEventListener('click', function () { doTest(true); });
    }

    // ---- Sinh token cron ----
    var genBtn = document.getElementById('btnGenToken');
    if (genBtn) {
        genBtn.addEventListener('click', function () {
            var bytes = new Uint8Array(24);
            (window.crypto || window.msCrypto).getRandomValues(bytes);
            var hex = Array.prototype.map.call(bytes, function (b) {
                return ('0' + b.toString(16)).slice(-2);
            }).join('');
            document.getElementById('cron_token').value = hex;
        });
    }

    // ---- Sandbox ----
    var sb = document.getElementById('btnSandbox');
    if (sb) {
        sb.addEventListener('click', function () {
            var box = document.getElementById('sandboxResult');
            box.innerHTML = '<div class="form-hint">Đang gọi...</div>';
            fetch('<?= e(rtrim($baseUrl, '/')) ?>/sandbox/acb/sandbox_password/0123456789/sandbox_token_stcpay', {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    var rows = (j.transactions || []).slice(0, 5);
                    var html = '<div class="alert alert-success small mb-0 py-2">HTTP 200 — ' + (j.transactions || []).length + ' giao dịch mẫu</div>';
                    html += '<div class="table-responsive mt-2"><table class="data-table"><tbody>';
                    rows.forEach(function (r) {
                        html += '<tr><td class="small font-numeric">' + r.transactionID + '</td>'
                             + '<td class="small text-gold">' + Number(r.amount).toLocaleString('vi-VN') + '</td>'
                             + '<td class="small">' + r.type + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    box.innerHTML = html;
                })
                .catch(function () {
                    box.innerHTML = '<div class="alert alert-danger small mb-0 py-2">Không gọi được sandbox</div>';
                });
        });
    }
})();
</script>
