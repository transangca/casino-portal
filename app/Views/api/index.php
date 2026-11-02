<?php
/** @var array $clients */
/** @var array $packages */
/** @var array $usage */
$reveal = $_SESSION['api_key_reveal'] ?? null;
unset($_SESSION['api_key_reveal']);
?>
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="fas fa-key text-gold me-2"></i>API của tôi</h1>
            <p class="text-muted-2 mb-0">
                Mua gói API để đấu game vào website của bạn. Mỗi gói cấp một API key riêng
                kèm hạn sử dụng và quota hằng ngày.
            </p>
        </div>
        <a href="<?= url('/api/docs') ?>" class="btn btn-outline-gold">
            <i class="fas fa-book me-2"></i>Đọc tài liệu API
        </a>
    </div>

    <!-- ============ API KEY VỪA TẠO ============ -->
    <?php if ($reveal): ?>
    <div class="casino-card mb-4" style="border-color:rgba(34,197,94,.45)">
        <div class="card-header d-flex align-items-center gap-2" style="background:rgba(34,197,94,.1)">
            <i class="fas fa-check-circle text-success"></i>
            <span>API key của bạn đã sẵn sàng</span>
        </div>
        <div class="card-body">
            <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
                <i class="fas fa-exclamation-triangle mt-1"></i>
                <div class="small">
                    <b>Hãy lưu lại key ngay.</b> Vì lý do bảo mật, key được mã hóa AES-256-GCM trong hệ thống
                    và chỉ hiển thị đầy đủ khi bạn chủ động yêu cầu xem lại.
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <code class="api-code flex-grow-1"><?= e($reveal['key']) ?></code>
                <button class="btn btn-sm btn-gold" data-copy="<?= e($reveal['key']) ?>">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <div class="small text-muted-2">
                Gói: <b class="text-light"><?= e($reveal['package'] ?? '') ?></b> ·
                Client ID: <b class="text-light">#<?= e((string)($reveal['client_id'] ?? '')) ?></b>
            </div>

            <hr class="my-3" style="border-color:var(--casino-line)">

            <div class="small text-muted-2 mb-2">Dùng thử ngay:</div>
            <code class="api-code d-block">curl "<?= e(url('/api/games?limit=3')) ?>" -H "X-API-Key: <?= e($reveal['key']) ?>"</code>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ============ Gói API ============ -->
        <div class="col-lg-7">
            <div class="casino-card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-box text-gold"></i>
                    <span>Gói API có sẵn</span>
                </div>
                <div class="card-body">
                    <?php if (!$packages): ?>
                        <p class="text-muted-2 mb-0">Hiện chưa có gói API nào. Vui lòng liên hệ quản trị viên.</p>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($packages as $p): ?>
                            <div class="col-md-6">
                                <div class="casino-card h-100 p-3 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <h5 class="h6 fw-bold mb-0"><?= e($p['name']) ?></h5>
                                        <?php if ((float)$p['price'] == 0): ?>
                                            <span class="badge bg-success">Miễn phí</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted-2 small flex-grow-1 mb-3"><?= e($p['description']) ?></p>

                                    <div class="row g-2 small text-muted-2 mb-3">
                                        <div class="col-6"><i class="fas fa-clock me-1"></i><?= (int)$p['duration_days'] ?> ngày</div>
                                        <div class="col-6"><i class="fas fa-chart-line me-1"></i><?= number_format((int)$p['daily_quota']) ?> req/ngày</div>
                                        <div class="col-6"><i class="fas fa-bolt me-1"></i><?= (int)$p['rate_limit'] ?> req/phút</div>
                                        <div class="col-6"><i class="fas fa-shield-halved me-1"></i>HMAC</div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                                        <div>
                                            <?php if ((float)$p['price'] == 0): ?>
                                                <span class="price-tag">0đ</span>
                                            <?php else: ?>
                                                <span class="price-tag"><?= money((float)$p['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm btn-gold" data-bs-toggle="modal"
                                                data-bs-target="#buyModal" data-pkg-id="<?= (int)$p['id'] ?>"
                                                data-pkg-name="<?= e($p['name']) ?>"
                                                data-pkg-price="<?= money((float)$p['price']) ?>">
                                            <i class="fas fa-shopping-cart me-1"></i>Thuê gói
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ API key của tôi ============ -->
        <div class="col-lg-5">
            <div class="casino-card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-list text-gold"></i>
                    <span>API key đang có</span>
                </div>
                <div class="card-body">
                    <?php if (!$clients): ?>
                        <p class="text-muted-2 mb-0">
                            Bạn chưa có API key nào. Chọn một gói phía trên để bắt đầu.
                        </p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($clients as $c): ?>
                                <?php
                                $expired = $c['expires_at'] && strtotime($c['expires_at']) < time();
                                $used    = \App\Services\ApiKeyService::requestsToday((int)$c['id']);
                                $pct     = $c['quota_limit'] > 0 ? min(100, round($used / (int)$c['quota_limit'] * 100)) : 0;
                                ?>
                                <div class="casino-card p-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div class="min-w-0">
                                            <div class="fw-bold text-truncate"><?= e($c['name']) ?></div>
                                            <div class="small text-muted-2 font-numeric">
                                                <?= e((string)$c['key_prefix']) ?>••••••
                                            </div>
                                        </div>
                                        <?php if ($c['status'] === 'active' && !$expired): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php elseif ($c['status'] === 'suspended'): ?>
                                            <span class="badge bg-secondary">Đã khóa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Hết hạn</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="small text-muted-2 mb-1">
                                        <i class="fas fa-clock me-1"></i>
                                        Hết hạn: <b class="<?= $expired ? 'text-danger' : 'text-light' ?>">
                                            <?= $c['expires_at'] ? date('d/m/Y H:i', strtotime($c['expires_at'])) : '—' ?>
                                        </b>
                                    </div>

                                    <div class="d-flex justify-content-between small text-muted-2 mb-1">
                                        <span>Quota hôm nay</span>
                                        <span class="font-numeric"><?= number_format($used) ?> / <?= number_format((int)$c['quota_limit']) ?></span>
                                    </div>
                                    <div class="progress" style="height:5px;background:rgba(255,255,255,.06)">
                                        <div class="progress-bar bg-warning" role="progressbar"
                                             style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>

                                    <div class="d-flex gap-1 mt-3 flex-wrap">
                                        <a class="btn btn-sm btn-outline-light" href="<?= url('/api-keys/reveal?id=' . (int)$c['id']) ?>">
                                            <i class="fas fa-eye me-1"></i>Xem key
                                        </a>
                                        <button class="btn btn-sm btn-outline-gold" data-bs-toggle="modal"
                                                data-bs-target="#renewModal" data-client-id="<?= (int)$c['id'] ?>">
                                            <i class="fas fa-rotate me-1"></i>Gia hạn
                                        </button>
                                        <?php if ($c['status'] === 'active'): ?>
                                            <form method="POST" action="<?= url('/api-keys/toggle') ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="client_id" value="<?= (int)$c['id'] ?>">
                                                <input type="hidden" name="status" value="suspended">
                                                <button class="btn btn-sm btn-outline-secondary" title="Tạm khóa">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= url('/api-keys/toggle') ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="client_id" value="<?= (int)$c['id'] ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn btn-sm btn-outline-success" title="Mở khóa">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= url('/api-keys/delete') ?>" class="d-inline"
                                              data-confirm="Xóa API key này? Các ứng dụng đang dùng sẽ ngừng hoạt động.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="client_id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Modal mua gói ============ -->
<div class="modal fade" id="buyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content casino-card">
            <form method="POST" action="<?= url('/api-keys/purchase') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Thuê gói API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="package_id" id="buyPkgId">
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span id="buyPkgName">—</span>
                        <strong class="text-gold" id="buyPkgPrice">—</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="buyName">Tên ứng dụng</label>
                        <input type="text" class="form-control" id="buyName" name="name"
                               placeholder="VD: Website casino của tôi" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="buyEmail">Email liên hệ <span class="text-muted-2">(không bắt buộc)</span></label>
                        <input type="email" class="form-control" id="buyEmail" name="email"
                               placeholder="dev@example.com" maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="buyWebsite">Website <span class="text-muted-2">(không bắt buộc)</span></label>
                        <input type="url" class="form-control" id="buyWebsite" name="website"
                               placeholder="https://example.com" maxlength="255">
                    </div>
                    <div class="form-hint">
                        <i class="fas fa-circle-info me-1"></i>
                        Phí gói được trừ trực tiếp từ số dư ví của bạn.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold">
                        <i class="fas fa-check me-1"></i>Xác nhận thuê gói
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ Modal gia hạn ============ -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content casino-card">
            <form method="POST" action="<?= url('/api-keys/renew') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Gia hạn API key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="client_id" id="renewClientId">
                    <label class="form-label" for="renewDays">Thêm thời gian</label>
                    <select class="form-select" id="renewDays" name="days">
                        <option value="7">7 ngày</option>
                        <option value="30" selected>30 ngày</option>
                        <option value="90">90 ngày</option>
                        <option value="180">180 ngày</option>
                        <option value="365">365 ngày</option>
                    </select>
                    <div class="form-hint mt-2">
                        <i class="fas fa-circle-info me-1"></i>
                        Phí gia hạn được tính theo tỷ lệ ngày của gói hiện tại và trừ từ ví.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="fas fa-rotate me-1"></i>Gia hạn</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var buyModal = document.getElementById('buyModal');
    if (buyModal) {
        buyModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            document.getElementById('buyPkgId').value   = btn.getAttribute('data-pkg-id');
            document.getElementById('buyPkgName').textContent = btn.getAttribute('data-pkg-name');
            document.getElementById('buyPkgPrice').textContent = btn.getAttribute('data-pkg-price');
        });
    }
    var renewModal = document.getElementById('renewModal');
    if (renewModal) {
        renewModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            document.getElementById('renewClientId').value = btn.getAttribute('data-client-id');
        });
    }
});
</script>
