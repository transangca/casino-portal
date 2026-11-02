<?php
/**
 * Dịch vụ SMM — mua like / view / comment / share / follow.
 *
 * @var array  $tree      cây nhóm → dịch vụ
 * @var array  $platforms danh sách platform
 * @var array  $orders    đơn gần đây của tôi
 * @var string $platform  platform đang lọc
 * @var string $keyword   từ khoá tìm kiếm
 * @var bool   $configured
 * @var bool   $enabled
 * @var bool   $hasKey
 * @var float  $markup
 */
use App\Services\SmmService;
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-thumbs-up me-2 text-gold"></i>SMM — Like, View, Comment, Follow</h1>
            <p class="page-sub mb-0">
                Mua like, view, comment, share, follow trên Facebook, TikTok, Instagram, YouTube.
                Giá tính theo số lượng, không cộng VAT — trừ trực tiếp ví chính.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/smm/apikey') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-key me-1"></i>API key của tôi
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ SMM hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình. Vui lòng liên hệ hỗ trợ để được cấp quyền sử dụng.
        </div>
    <?php elseif (!$hasKey): ?>
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-1"></i>
            Bạn chưa có API key riêng. Vui lòng tạo tại
            <a href="<?= url('/services/smm/apikey') ?>">trang API key</a> để hệ thống đặt đơn thay bạn.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ===== Danh mục dịch vụ ===== -->
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="<?= url('/services/smm') ?>"
                   class="filter-chip <?= $platform === '' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group me-1"></i>Tất cả
                </a>
                <?php foreach ($platforms as $p): ?>
                    <a href="<?= url('/services/smm?platform=' . urlencode((string)$p['platform'])) ?>"
                       class="filter-chip <?= $platform === (string)$p['platform'] ? 'active' : '' ?>">
                        <i class="fas fa-circle-nodes me-1"></i><?= e((string)$p['platform']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" action="<?= url('/services/smm') ?>" class="mb-3">
                <?php if ($platform !== ''): ?>
                    <input type="hidden" name="platform" value="<?= e($platform) ?>">
                <?php endif; ?>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" name="q" class="form-control" value="<?= e($keyword) ?>"
                           placeholder="Tìm dịch vụ (like Facebook, view TikTok, comment...)">
                    <button class="btn btn-outline-gold">Tìm</button>
                    <?php if ($keyword !== ''): ?>
                        <a href="<?= url('/services/smm') ?>" class="btn btn-outline-light">Xoá</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$tree): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fs-1 d-block mb-3"></i>
                    <p class="mb-0">
                        <?= $keyword !== '' || $platform !== ''
                            ? 'Không tìm thấy dịch vụ phù hợp.'
                            : 'Chưa có dịch vụ nào. Danh mục đang được cập nhật, vui lòng quay lại sau.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($tree as $group): ?>
                    <div class="panel-card mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <i class="fas fa-layer-group text-gold"></i>
                            <h5 class="mb-0"><?= e((string)($group['clean_category'] ?: $group['category'])) ?></h5>
                            <?php if ((string)$group['platform'] !== ''): ?>
                                <span class="badge-status st-active"><?= e((string)$group['platform']) ?></span>
                            <?php endif; ?>
                            <span class="badge-status st-active ms-auto"><?= count($group['services']) ?> dịch vụ</span>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($group['services'] as $s):
                                $cost   = (float)$s['price_per_1k_vnd'];
                                $sell   = $markup > 0 ? (float)ceil($cost * (1 + $markup / 100)) : $cost;
                                $isCmt  = (int)$s['is_comment_service'] === 1;
                                $disc   = (float)$s['agent_discount_percent'];
                                $list   = $s['list_price_per_1k_vnd'] !== null ? (float)$s['list_price_per_1k_vnd'] : null;
                                ?>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="otp-service h-100 d-flex flex-column">
                                        <?php if ($isCmt): ?>
                                            <span class="badge-status st-active mb-2 align-self-start">
                                                <i class="fas fa-comment me-1"></i>Dịch vụ comment
                                            </span>
                                        <?php elseif ((string)$s['type'] !== ''): ?>
                                            <span class="badge-status st-active mb-2 align-self-start">
                                                <?= e((string)$s['type']) ?>
                                            </span>
                                        <?php endif; ?>

                                        <div class="fw-semibold small" style="min-height:2.6em">
                                            <?= e((string)$s['name']) ?>
                                        </div>

                                        <div class="form-hint small mb-2">
                                            Min <?= (int)$s['min'] ?> · Max <?= (int)$s['max'] ?>
                                        </div>

                                        <div class="otp-price mt-auto">
                                            <span class="aic-label">Giá / 1.000</span>
                                            <b class="text-gold"><?= money($sell) ?></b>
                                            <span class="form-hint d-block">
                                                <?= number_format(SmmService::pricePerUnit($sell), 3) ?> đ / lượt
                                            </span>
                                        </div>

                                        <button type="button"
                                                class="btn btn-gold btn-sm w-100 mt-2 js-open-order"
                                                data-service="<?= (int)$s['service_id'] ?>"
                                                data-name="<?= e((string)$s['name']) ?>"
                                                data-min="<?= (int)$s['min'] ?>"
                                                data-max="<?= (int)$s['max'] ?>"
                                                data-price="<?= money($sell) ?>"
                                                data-price1k="<?= $sell ?>"
                                                data-comment="<?= $isCmt ? '1' : '0' ?>"
                                                <?= (!$enabled || !$configured || !$hasKey) ? 'disabled' : '' ?>>
                                            <i class="fas fa-bolt me-1"></i>Đặt đơn
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
                            <a href="<?= url('/services/smm/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-thumbs-up text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['service_name'] ?: 'Dịch vụ SMM')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= $o['remote_order'] ? '#' . (int)$o['remote_order'] : 'Chưa có mã' ?>
                                            · <?= e(date('d/m/Y H:i', strtotime((string)$o['created_at']))) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                        <span class="badge-status st-<?= e(SmmService::statusBadge($st)) ?>">
                                            <?= e(SmmService::statusLabel($st)) ?>
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
                    <li>Chọn dịch vụ, bấm <b>Đặt đơn</b>.</li>
                    <li>Nhập link cần tăng (post/video/profile).</li>
                    <li>Dịch vụ comment: nhập từng dòng — số lượng tính tự động theo số dòng.</li>
                    <li>Hệ thống trừ ví chính và đẩy đơn sang bộ phận xử lý.</li>
                    <li>Theo dõi tiến độ trên trang đơn — trang tự làm mới mỗi 10 giây.</li>
                </ol>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-calculator me-2"></i>Công thức tính giá</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li><code>price_per_1k_vnd</code> = giá bán cho 1.000 lượt.</li>
                    <li><code>price_per_unit_vnd</code> = <code>price_per_1k_vnd / 1000</code>.</li>
                    <li>Tổng = <code>ceil(quantity / 1000 × price_per_1k_vnd)</code>.</li>
                    <li>Không cộng VAT (luôn bằng 0).</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal đặt đơn ===== -->
<div class="modal fade" id="smmOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt đơn SMM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('/services/smm/submit') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="service_id" id="smmServiceId" value="">
                <input type="hidden" name="idempotency_key" id="smmIdemKey" value="">
                <div class="modal-body">
                    <div class="alert alert-info mb-3 small">
                        <i class="fas fa-box me-1"></i>
                        <b id="smmServiceName">—</b>
                        <div class="form-hint mt-1" id="smmServiceMeta"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smmLink">
                            Link cần tăng <span class="text-danger">*</span>
                        </label>
                        <textarea name="link" id="smmLink" class="form-control font-numeric" rows="2" required
                                  placeholder="https://www.facebook.com/example/posts/123"></textarea>
                        <div class="form-hint">Link Facebook / TikTok / Instagram / YouTube cần tăng.</div>
                    </div>

                    <div class="mb-3" id="smmQuantityWrap">
                        <label class="form-label" for="smmQuantity">Số lượng</label>
                        <input type="number" name="quantity" id="smmQuantity" class="form-control font-numeric"
                               min="1" step="1" value="">
                        <div class="form-hint" id="smmQuantityHint">Min — / Max —</div>
                    </div>

                    <div class="mb-3 d-none" id="smmCommentsWrap">
                        <label class="form-label" for="smmComments">Danh sách comment</label>
                        <textarea name="comments" id="smmComments" class="form-control" rows="5"
                                  placeholder="Mỗi dòng là 1 comment&#10;Comment 1&#10;Comment 2"></textarea>
                        <div class="form-hint">
                            Số lượng tự động tính theo số dòng hợp lệ.
                            <span id="smmCommentCount" class="text-gold fw-semibold">0 dòng</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="smmReaction">Reaction (tuỳ chọn)</label>
                        <input type="text" name="reaction" id="smmReaction" class="form-control"
                               placeholder="like, love, wow... (chỉ dùng khi dịch vụ yêu cầu)">
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="small text-muted-2">
                            Tổng thanh toán
                            <b id="smmTotal" class="text-gold fs-5 font-numeric d-block">—</b>
                        </span>
                        <span class="small text-muted-2 text-end" id="smmFormula">
                            ceil(quantity / 1000 × price_per_1k)
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-gold" id="smmSubmitBtn">
                        <i class="fas fa-bolt me-1"></i>Xác nhận đặt đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modalEl = document.getElementByById('smmOrderModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);

    var qtyWrap    = document.getElementById('smmQuantityWrap');
    var cmtWrap    = document.getElementById('smmCommentsWrap');
    var cmtCount   = document.getElementById('smmCommentCount');
    var qtyInput   = document.getElementById('smmQuantity');
    var cmtInput   = document.getElementById('smmComments');
    var totalEl    = document.getElementById('smmTotal');
    var metaEl     = document.getElementById('smmServiceMeta');

    var current = { price1k: 0, min: 0, max: 0, isComment: false };

    function randKey(serviceId) {
        return 'smm-' + serviceId + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    }

    function countLines(text) {
        if (!text) return 0;
        return text.split(/\r\n|\r|\n/).filter(function (l) { return l.trim() !== ''; }).length;
    }

    function recalc() {
        var qty = current.isComment ? countLines(cmtInput.value) : parseInt(qtyInput.value || '0', 10);
        if (!qty || qty <= 0) {
            totalEl.textContent = '—';
            return;
        }
        var total = Math.ceil(qty / 1000 * current.price1k);
        totalEl.textContent = total.toLocaleString('vi-VN') + ' đ';
    }

    document.querySelectorAll('.js-open-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sid = btn.getAttribute('data-service');
            current.price1k  = parseFloat(btn.getAttribute('data-price1k')) || 0;
            current.min      = parseInt(btn.getAttribute('data-min'), 10) || 0;
            current.max      = parseInt(btn.getAttribute('data-max'), 10) || 0;
            current.isComment = btn.getAttribute('data-comment') === '1';

            document.getElementById('smmServiceId').value = sid;
            document.getElementById('smmIdemKey').value = randKey(sid);
            document.getElementById('smmServiceName').textContent = btn.getAttribute('data-name');
            metaEl.textContent = 'Min ' + current.min + ' / Max ' + current.max
                + ' · Giá / 1.000: ' + (btn.getAttribute('data-price') || '');

            qtyWrap.classList.toggle('d-none', current.isComment);
            cmtWrap.classList.toggle('d-none', !current.isComment);
            qtyInput.min = current.min || 1;
            qtyInput.max = current.max || '';
            qtyInput.value = current.min || '';
            cmtInput.value = '';
            cmtCount.textContent = '0 dòng';
            totalEl.textContent = '—';
            modal.show();
        });
    });

    qtyInput.addEventListener('input', recalc);
    cmtInput.addEventListener('input', function () {
        cmtCount.textContent = countLines(cmtInput.value) + ' dòng';
        recalc();
    });

    var form = modalEl.querySelector('form');
    form.addEventListener('submit', function () {
        var s = document.getElementById('smmSubmitBtn');
        s.disabled = true;
        s.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang gửi...';
    });
})();
</script>
