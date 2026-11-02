<?php
/**
 * Dịch vụ VPS VMware — danh mục gói, chọn OS, đặt đơn (XVPS Agency API).
 *
 * @var array  $groups    nhóm sản phẩm → danh sách gói
 * @var array  $osList    danh sách hệ điều hành
 * @var array  $orders    đơn gần đây
 * @var array  $instances VPS đang quản lý
 * @var string $cycle     chu kỳ đang chọn
 * @var array  $cycles    danh sách chu kỳ
 * @var bool   $configured
 * @var bool   $enabled
 * @var float  $markup
 * @var float  $balance
 */
use App\Services\XvpsService;

$cycKey = $cycle === 'yearly' ? 'annually' : $cycle;
$cycleLabel = $cycles[$cycle] ?? '1 Tháng';
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-server me-2 text-gold"></i>VPS VMware — Thuê máy chủ ảo</h1>
            <p class="page-sub mb-0">
                Thuê VPS theo tháng / quý / năm, quản lý trực tiếp start, stop, rebuild, gia hạn.
                Không cộng VAT — trừ trực tiếp ví chính.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/vps/manage') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-gauge-high me-1"></i>VPS của tôi
                <?php if ($instances): ?>
                    <span class="badge bg-gold text-dark ms-1"><?= count($instances) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <?php if (!$enabled): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-pause-circle me-1"></i>
            Dịch vụ VPS hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php elseif (!$configured): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-1"></i>
            Dịch vụ chưa được cấu hình. Vui lòng liên hệ hỗ trợ để được cấp quyền sử dụng.
        </div>
    <?php endif; ?>

    <!-- ===== Thanh chọn chu kỳ ===== -->
    <div class="panel-card mb-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div>
                <h6 class="mb-1"><i class="fas fa-calendar-days me-2 text-gold"></i>Chu kỳ thanh toán</h6>
                <p class="form-hint mb-0">Giá hiển thị theo chu kỳ đang chọn, không cộng VAT.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 ms-auto">
                <?php foreach ($cycles as $k => $label):
                    $show = ($k === 'yearly' ? 'annually' : $k);
                    if ($show === 'semi-annually' && !isset($cycles['semi-annually'])) {
                        continue;
                    }
                    $href = url('/services/vps?cycle=' . urlencode($k));
                    ?>
                    <a href="<?= $href ?>" class="filter-chip <?= $cycle === $k ? 'active' : '' ?>">
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ===== Danh mục gói ===== -->
        <div class="col-lg-8">
            <?php if (!$groups): ?>
                <div class="empty-state">
                    <i class="fas fa-server fs-1 d-block mb-3"></i>
                    <p class="mb-0">
                        Chưa có gói VPS nào. Danh mục đang được cập nhật, vui lòng quay lại sau.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $groupName => $items): ?>
                    <div class="panel-card mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <i class="fas fa-layer-group text-gold"></i>
                            <h5 class="mb-0"><?= e((string)$groupName) ?></h5>
                            <span class="badge-status st-active ms-auto"><?= count($items) ?> gói</span>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($items as $p):
                                $price = (float)$p['price_' . $cycKey];
                                $sell  = XvpsService::sellPrice($price);
                                ?>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="otp-service h-100 d-flex flex-column">
                                        <?php if ((int)$p['special'] === 1): ?>
                                            <span class="badge-status st-active mb-2 align-self-start">
                                                <i class="fas fa-star me-1"></i>Sản phẩm đặc biệt
                                            </span>
                                        <?php endif; ?>

                                        <div class="fw-semibold small mb-2" style="min-height:2.4em">
                                            <?= e((string)$p['name']) ?>
                                        </div>

                                        <ul class="vps-specs small mb-2">
                                            <li><i class="fas fa-microchip"></i><?= (int)$p['cpu'] ?> vCPU</li>
                                            <li><i class="fas fa-memory"></i><?= (int)$p['ram'] ?> MB RAM</li>
                                            <li><i class="fas fa-hard-drive"></i><?= (int)$p['disk'] ?> GB SSD</li>
                                            <?php if ((string)$p['ip_info'] !== ''): ?>
                                                <li><i class="fas fa-network-wired"></i><?= e((string)$p['ip_info']) ?></li>
                                            <?php endif; ?>
                                            <?php if ((string)$p['bandwidth'] !== ''): ?>
                                                <li><i class="fas fa-gauge"></i><?= e((string)$p['bandwidth']) ?></li>
                                            <?php endif; ?>
                                            <?php if ((string)$p['os_support'] !== ''): ?>
                                                <li><i class="fab fa-linux"></i><?= e((string)$p['os_support']) ?></li>
                                            <?php endif; ?>
                                        </ul>

                                        <div class="otp-price mt-auto">
                                            <span class="aic-label"><?= e($cycleLabel) ?></span>
                                            <b class="text-gold"><?= money($sell) ?></b>
                                            <?php if ($markup > 0): ?>
                                                <span class="form-hint d-block">
                                                    <s><?= money($price) ?></s> · +<?= number_format($markup, 0) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <button type="button"
                                                class="btn btn-gold btn-sm w-100 mt-2 js-open-order"
                                                data-product="<?= e((string)$p['product_id']) ?>"
                                                data-name="<?= e((string)$p['name']) ?>"
                                                data-price="<?= $sell ?>"
                                                data-price-raw="<?= $price ?>"
                                                data-spec="<?= e((int)$p['cpu'] . ' vCPU · ' . (int)$p['ram'] . ' MB RAM · ' . (int)$p['disk'] . ' GB') ?>"
                                                <?= (!$enabled || !$configured) ? 'disabled' : '' ?>>
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

        <!-- ===== Sidebar ===== -->
        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-wallet me-2"></i>Số dư ví chính</h6>
                <div class="fs-4 fw-bold text-gold mb-1"><?= money((float)$balance) ?></div>
                <p class="form-hint mb-0">Tiền trừ trực tiếp từ ví khi đặt VPS. Gia hạn cũng trừ ví.</p>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Đơn của tôi</h6>

                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Chưa có đơn nào.</p>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($orders as $o):
                            $st = (string)$o['status'];
                            ?>
                            <a href="<?= url('/services/vps/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-server text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['product_name'] ?: 'VPS')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= $o['remote_order'] ? '#' . e((string)$o['remote_order']) : 'Chưa có mã' ?>
                                            · <?= e(date('d/m/Y H:i', strtotime((string)$o['created_at']))) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                        <span class="badge-status st-<?= e(XvpsService::statusBadge($st)) ?>">
                                            <?= e(XvpsService::statusLabel($st)) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($instances): ?>
                <div class="panel-card mt-4">
                    <h6 class="mb-3"><i class="fas fa-gauge-high me-2"></i>VPS của tôi</h6>
                    <div class="aic-order-list">
                        <?php foreach ($instances as $v): ?>
                            <a href="<?= url('/services/vps/manage/' . rawurlencode((string)$v['vps_id'])) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-server text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($v['product_name'] ?: 'VPS')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= e((string)$v['ip']) ?>
                                            · <?= (int)$v['day_left'] ?> ngày còn
                                        </div>
                                    </div>
                                    <span class="badge-status st-<?= e(XvpsService::vpsStatusBadge((string)$v['vps_status'])) ?>">
                                        <?= e(XvpsService::vpsStatusLabel((string)$v['vps_status'])) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Hướng dẫn</h6>
                <ol class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Chọn gói VPS và chu kỳ thanh toán.</li>
                    <li>Bấm <b>Đặt đơn</b>, chọn hệ điều hành và số lượng.</li>
                    <li>Hệ thống trừ ví chính và tạo đơn qua API XVPS.</li>
                    <li>VPS khởi tạo qua hàng chờ — theo dõi tại trang đơn.</li>
                    <li>Quản lý start / stop / rebuild / gia hạn tại <b>VPS của tôi</b>.</li>
                </ol>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-calculator me-2"></i>Công thức tính giá</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Giá bán đã bao gồm biên lợi nhuận của hệ thống.</li>
                    <li>Tổng = (giá gói + addon) × số lượng.</li>
                    <li>Không cộng VAT (luôn bằng 0).</li>
                    <li>Hoàn tiền tự động nếu đơn không được chấp nhận.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal đặt đơn ===== -->
<div class="modal fade" id="vpsOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt VPS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('/services/vps/submit') ?>" id="vpsOrderForm">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" id="vpsProductId" value="">
                <input type="hidden" name="cycle" id="vpsCycle" value="<?= e($cycle) ?>">
                <input type="hidden" name="idempotency_key" id="vpsIdemKey" value="">

                <div class="modal-body">
                    <div class="alert alert-info mb-3 small">
                        <i class="fas fa-box me-1"></i>
                        <b id="vpsProductName">—</b>
                        <div class="form-hint mt-1" id="vpsProductSpec"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="vpsOsId">Hệ điều hành <span class="text-danger">*</span></label>
                            <select name="os_id" id="vpsOsId" class="form-select" required>
                                <option value="">— Chọn hệ điều hành —</option>
                                <?php foreach ($osList as $os): ?>
                                    <option value="<?= e((string)$os['os_id']) ?>"><?= e((string)$os['os_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$osList): ?>
                                <div class="form-hint text-danger">Chưa có hệ điều hành — danh mục đang được cập nhật.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="vpsQuantity">Số lượng</label>
                            <input type="number" name="quantity" id="vpsQuantity"
                                   class="form-control font-numeric" min="1" max="10" step="1" value="1">
                            <div class="form-hint">Từ 1 đến 10 máy chủ.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="vpsAddonCpu">Thêm vCPU</label>
                            <input type="number" name="addon_cpu" id="vpsAddonCpu"
                                   class="form-control font-numeric" min="0" step="1" value="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="vpsAddonRam">Thêm RAM (GB)</label>
                            <input type="number" name="addon_ram" id="vpsAddonRam"
                                   class="form-control font-numeric" min="0" step="1" value="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="vpsAddonDisk">Thêm Disk (GB)</label>
                            <input type="number" name="addon_disk" id="vpsAddonDisk"
                                   class="form-control font-numeric" min="0" step="10" value="0">
                            <div class="form-hint">Chia hết cho 10.</div>
                        </div>
                    </div>

                    <div class="vps-total mt-3 p-3 rounded" style="background:rgba(255,255,255,.04)">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Đơn giá</span>
                            <span id="vpsPriceItem">—</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Số lượng</span>
                            <span id="vpsPriceQty">× 1</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">VAT</span>
                            <span id="vpsPriceVat">0 đ</span>
                        </div>
                        <hr class="my-2" style="border-color:rgba(255,255,255,.12)">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">Tổng cộng</span>
                            <span class="fs-5 fw-bold text-gold" id="vpsPriceTotal">—</span>
                        </div>
                    </div>

                    <div class="form-hint mt-2">
                        <i class="fas fa-shield-halved me-1"></i>
                        Số dư hiện tại: <b><?= money((float)$balance) ?></b>.
                        Nếu đơn không được chấp nhận, tiền được hoàn lại tự động.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-gold" id="vpsSubmitBtn">
                        <i class="fas fa-bolt me-1"></i>Đặt &amp; Thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modalEl = document.getElementById('vpsOrderModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);

    var els = {
        productId: document.getElementById('vpsProductId'),
        cycle: document.getElementById('vpsCycle'),
        idem: document.getElementById('vpsIdemKey'),
        name: document.getElementById('vpsProductName'),
        spec: document.getElementById('vpsProductSpec'),
        os: document.getElementById('vpsOsId'),
        qty: document.getElementById('vpsQuantity'),
        cpu: document.getElementById('vpsAddonCpu'),
        ram: document.getElementById('vpsAddonRam'),
        disk: document.getElementById('vpsAddonDisk'),
        item: document.getElementById('vpsPriceItem'),
        qtyOut: document.getElementById('vpsPriceQty'),
        vat: document.getElementById('vpsPriceVat'),
        total: document.getElementById('vpsPriceTotal'),
        submit: document.getElementById('vpsSubmitBtn')
    };

    var currentPrice = 0;
    var quoteTimer = null;

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(n) + ' đ';
    }

    function refreshQuote() {
        if (!els.productId.value) return;
        var qty = parseInt(els.qty.value || '1', 10);
        var url = new URL('<?= url('/services/vps/quote') ?>', window.location.origin);
        url.searchParams.set('product_id', els.productId.value);
        url.searchParams.set('cycle', els.cycle.value);
        url.searchParams.set('quantity', qty);
        url.searchParams.set('addon_cpu', els.cpu.value || 0);
        url.searchParams.set('addon_ram', els.ram.value || 0);
        url.searchParams.set('addon_disk', els.disk.value || 0);

        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) {
                    els.total.textContent = json.message || 'Lỗi tính giá';
                    return;
                }
                var d = json.data;
                currentPrice = parseFloat(d.total);
                els.item.textContent = d.formatted.item;
                els.qtyOut.textContent = '× ' + d.quantity;
                els.vat.textContent = d.formatted.vat;
                els.total.textContent = d.formatted.total;
            })
            .catch(function () {
                els.total.textContent = '—';
            });
    }

    function scheduleQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(refreshQuote, 220);
    }

    document.querySelectorAll('.js-open-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            els.productId.value = btn.dataset.product;
            els.name.textContent = btn.dataset.name || '—';
            els.spec.textContent = btn.dataset.spec || '';
            currentPrice = parseFloat(btn.dataset.price || '0');

            els.item.textContent = fmt(currentPrice);
            els.total.textContent = fmt(currentPrice);

            // Idempotency key mới cho mỗi lần mở modal
            els.idem.value = 'vps-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);

            modal.show();
            refreshQuote();
        });
    });

    [els.qty, els.cpu, els.ram, els.disk].forEach(function (el) {
        el.addEventListener('input', scheduleQuote);
    });

    // Không cho submit 2 lần
    document.getElementById('vpsOrderForm').addEventListener('submit', function () {
        els.submit.disabled = true;
        els.submit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';
    });
})();
</script>

<style>
.vps-specs { list-style: none; padding: 0; margin: 0; color: var(--muted-2); }
.vps-specs li { display: flex; align-items: center; gap: .4rem; margin-bottom: .2rem; }
.vps-specs i { width: 1rem; color: var(--gold); }
</style>
