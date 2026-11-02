<?php
/**
 * Dịch vụ Auto MXH — mở khóa & bảo vệ tài khoản.
 *
 * @var array $tree      cây category → product → variant
 * @var array $orders    đơn gần đây của tôi
 * @var string $keyword  từ khoá tìm kiếm
 * @var bool  $configured
 * @var bool  $enabled
 * @var bool  $hasKey
 */
$markup = (float)setting('automxh_markup', '0');
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-unlock-keyhole me-2 text-gold"></i>Auto MXH — Mở khóa tài khoản</h1>
            <p class="page-sub mb-0">
                Lấy lại tài khoản Facebook, Instagram, TikTok bị hack hoặc bị khoá —
                đặt đơn tự động, trừ ví chính, theo dõi tiến độ thời gian thực.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/automxh/apikey') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-key me-1"></i>API key của tôi
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ Auto MXH hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình. Vui lòng liên hệ hỗ trợ để được cấp quyền sử dụng.
        </div>
    <?php elseif (!$hasKey): ?>
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-1"></i>
            Bạn chưa có API key riêng. Vui lòng thêm tại
            <a href="<?= url('/services/automxh/apikey') ?>">trang API key</a> để hệ thống đặt đơn thay bạn.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ===== Danh mục gói ===== -->
        <div class="col-lg-8">
            <form method="GET" action="<?= url('/services/automxh') ?>" class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" name="q" class="form-control" value="<?= e($keyword) ?>"
                           placeholder="Tìm gói dịch vụ (Facebook bị hack, xác minh bảo vệ...)">
                    <button class="btn btn-outline-gold">Tìm</button>
                    <?php if ($keyword !== ''): ?>
                        <a href="<?= url('/services/automxh') ?>" class="btn btn-outline-light">Xoá</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$tree): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fs-1 d-block mb-3"></i>
                    <p class="mb-0">
                        <?= $keyword !== ''
                            ? 'Không tìm thấy gói phù hợp.'
                            : 'Chưa có gói dịch vụ nào. Danh mục đang được cập nhật, vui lòng quay lại sau.' ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Nhóm dịch vụ -->
                <?php foreach ($tree as $cat): ?>
                    <div class="panel-card mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-layer-group text-gold"></i>
                            <h5 class="mb-0"><?= e((string)$cat['name']) ?></h5>
                            <span class="badge-status st-active ms-auto">
                                <?= count($cat['products']) ?> sản phẩm
                            </span>
                        </div>

                        <?php foreach ($cat['products'] as $prod): ?>
                            <div class="mb-4">
                                <div class="fw-semibold small mb-2 text-uppercase" style="letter-spacing:.04em">
                                    <?= e((string)$prod['name']) ?>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($prod['variants'] as $v):
                                        $cost  = (float)$v['price'];
                                        $sell  = $markup > 0 ? (float)ceil($cost * (1 + $markup / 100)) : $cost;
                                        $disc  = (float)($v['agent_discount_percent'] ?? 0);
                                        $list  = $v['list_price'] !== null ? (float)$v['list_price'] : null;
                                        ?>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="otp-service h-100 d-flex flex-column">
                                                <?php if ($v['badge'] !== ''): ?>
                                                    <span class="badge-status st-active mb-2 align-self-start">
                                                        <?= e((string)$v['badge']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <div class="fw-semibold small" style="min-height:2.6em">
                                                    <?= e((string)$v['name']) ?>
                                                </div>

                                                <div class="otp-price mt-auto">
                                                    <span class="aic-label">Giá bán</span>
                                                    <b class="text-gold"><?= money($sell) ?></b>
                                                </div>

                                                <button type="button"
                                                        class="btn btn-gold btn-sm w-100 mt-2 js-open-order"
                                                        data-variant="<?= (int)$v['variant_id'] ?>"
                                                        data-name="<?= e((string)$v['name']) ?>"
                                                        data-price="<?= money($sell) ?>"
                                                        data-desc="<?= e(mb_substr((string)$v['description'], 0, 400)) ?>"
                                                        <?= (!$enabled || !$configured || !$hasKey) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-bolt me-1"></i>Đặt đơn
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== Lịch sử + hướng dẫn ===== -->
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Đơn của tôi</h6>

                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Chưa có đơn nào.</p>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($orders as $o):
                            $st = (string)$o['status'];
                            ?>
                            <a href="<?= url('/services/automxh/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-unlock-keyhole text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['variant_name'] ?: 'Dịch vụ')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : 'Chưa có mã' ?>
                                            · <?= e(date('d/m/Y H:i', strtotime((string)$o['created_at']))) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                        <span class="badge-status st-<?= e(\App\Services\AutoMxhService::statusBadge($st)) ?>">
                                            <?= e(\App\Services\AutoMxhService::statusLabel($st)) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Hướng dẫn</h6>
                <ol class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Chọn gói phù hợp, bấm <b>Đặt đơn</b>.</li>
                    <li>Nhập link cần xử lý + thông tin liên hệ (nếu gói yêu cầu).</li>
                    <li>Hệ thống trừ ví chính và đẩy đơn sang bộ phận xử lý.</li>
                    <li>Theo dõi tiến độ trên trang đơn — trang tự làm mới mỗi 10 giây.</li>
                    <li>Gói chưa tự động xử lý được sẽ được bộ phận hỗ trợ xử lý thủ công.</li>
                </ol>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-shield-halved me-2"></i>Cam kết</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Giá cố định theo gói, không cộng VAT.</li>
                    <li>Hoàn tiền 100% nếu đơn không được chấp nhận.</li>
                    <li>Bạn tự quản lý API key tại trang <a href="<?= url('/services/automxh/apikey') ?>">API key</a>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal đặt đơn ===== -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt đơn Auto MXH</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('/services/automxh/submit') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="variant_id" id="mxhVariantId" value="">
                <input type="hidden" name="idempotency_key" id="mxhIdemKey" value="">
                <div class="modal-body">
                    <div class="alert alert-info mb-3 small">
                        <i class="fas fa-box me-1"></i>
                        <b id="mxhVariantName">—</b>
                        <div class="form-hint mt-1" id="mxhVariantDesc"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="mxhLink">
                            Link cần xử lý <span class="text-danger">*</span>
                        </label>
                        <textarea name="link" id="mxhLink" class="form-control font-numeric" rows="3"
                                  required
                                  placeholder="https://www.facebook.com/example/posts/123"></textarea>
                        <div class="form-hint">Link Facebook / Instagram / TikTok cần mở khóa hoặc bảo vệ.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="mxhBuyerInfo">Thông tin liên hệ</label>
                        <input type="text" name="buyer_info" id="mxhBuyerInfo" class="form-control"
                               placeholder="Zalo/Telegram/Email — gói yêu cầu thì bắt buộc">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="mxhCustomValue">Thông tin nhập phụ</label>
                        <textarea name="custom_value" id="mxhCustomValue" class="form-control" rows="2"
                                  placeholder="Ghi chú / username / mã đơn cũ (nếu gói yêu cầu)"></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="small text-muted-2">Đơn giá</span>
                        <span class="small">Bạn trả: <b id="mxhPrice" class="text-gold fs-5 font-numeric">—</b></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-gold" id="mxhSubmitBtn">
                        <i class="fas fa-bolt me-1"></i>Xác nhận đặt đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modalEl = document.getElementById('orderModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);

    function randKey(variantId) {
        var r = Math.random().toString(36).slice(2, 12);
        return 'amx-' + variantId + '-' + Date.now() + '-' + r;
    }

    document.querySelectorAll('.js-open-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var vid = btn.getAttribute('data-variant');
            document.getElementById('mxhVariantId').value = vid;
            document.getElementById('mxhIdemKey').value = randKey(vid);
            document.getElementById('mxhVariantName').textContent = btn.getAttribute('data-name');
            document.getElementById('mxhVariantDesc').textContent = btn.getAttribute('data-desc') || '';
            document.getElementById('mxhPrice').textContent = btn.getAttribute('data-price');
            document.getElementById('mxhLink').value = '';
            modal.show();
        });
    });

    // Khoá nút trong lúc đang gửi để tránh tạo 2 đơn
    var form = modalEl.querySelector('form');
    form.addEventListener('submit', function () {
        var s = document.getElementById('mxhSubmitBtn');
        s.disabled = true;
        s.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang gửi...';
    });
})();
</script>
