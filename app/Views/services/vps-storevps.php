<?php
/**
 * Dịch vụ VPS StoreVPS (Virtualizor) — danh mục gói, chọn OS/location, đặt đơn.
 *
 * @var array  $groups    nhóm sản phẩm → danh sách gói
 * @var array  $osList    danh sách hệ điều hành
 * @var array  $locations danh sách datacenter
 * @var array  $addons    danh sách addon
 * @var array  $orders    đơn gần đây
 * @var array  $instances VPS đang quản lý
 * @var string $cycle     chu kỳ đang chọn
 * @var array  $cycles    danh sách chu kỳ
 * @var bool   $configured
 * @var bool   $enabled
 * @var float  $markup
 * @var float  $balance
 */
use App\Services\VirtualizorService;

$cycKey     = VirtualizorService::cycleKey($cycle);
$cycleLabel = $cycles[$cycle] ?? '1 Tháng';
?>
<div class="container py-4">

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="fas fa-layer-group me-2 text-gold"></i>VPS StoreVPS — Virtualizor
            </h1>
            <p class="page-sub mb-0">
                Thuê VPS StoreVPS theo tháng / quý / năm. Quản lý nguồn, cài lại OS, đổi mật khẩu,
                đổi hostname, gia hạn, nâng cấp addon và hủy dịch vụ — không cộng VAT, trừ trực tiếp ví chính.
            </p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/vps-storevps/manage') ?>" class="btn btn-outline-gold btn-sm">
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
            Dịch vụ VPS StoreVPS hiện đang tạm dừng. Vui lòng quay lại sau.
        </div>
    <?php endif; ?>

    <?php if (!$configured): ?>
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
                <?php foreach ($cycles as $k => $label): ?>
                    <a href="<?= url('/services/vps-storevps?cycle=' . urlencode($k)) ?>"
                       class="filter-chip <?= $cycle === $k ? 'active' : '' ?>">
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
                    <i class="fas fa-layer-group fs-1 d-block mb-3"></i>
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
                                $sell  = VirtualizorService::sellPrice($price);
                                $locJson = json_decode((string)$p['locations_json'], true);
                                $locs = is_array($locJson) ? $locJson : [];
                                ?>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="otp-service h-100 d-flex flex-column">
                                        <div class="fw-semibold small mb-2" style="min-height:2.4em">
                                            <?= e((string)$p['name']) ?>
                                        </div>

                                        <ul class="vps-specs small mb-2">
                                            <li><i class="fas fa-microchip"></i><?= (int)$p['cpu'] ?> vCPU</li>
                                            <li><i class="fas fa-memory"></i><?= (int)$p['ram'] ?> MB RAM</li>
                                            <li><i class="fas fa-hard-drive"></i><?= (int)$p['disk'] ?> GB SSD</li>
                                            <?php if ((int)$p['ipv4'] > 0): ?>
                                                <li><i class="fas fa-network-wired"></i><?= (int)$p['ipv4'] ?> IPv4</li>
                                            <?php endif; ?>
                                            <?php if ((int)$p['ipv6'] > 0): ?>
                                                <li><i class="fas fa-network-wired"></i><?= (int)$p['ipv6'] ?> IPv6</li>
                                            <?php endif; ?>
                                            <?php if ((int)$p['bandwidth'] > 0): ?>
                                                <li><i class="fas fa-gauge"></i><?= (int)$p['bandwidth'] ?> Mbps</li>
                                            <?php endif; ?>
                                            <?php if ((int)$p['network_speed'] > 0): ?>
                                                <li><i class="fas fa-tower-broadcast"></i><?= (int)$p['network_speed'] ?> Mbps</li>
                                            <?php endif; ?>
                                        </ul>

                                        <?php if ($locs): ?>
                                            <div class="form-hint mb-2">
                                                <i class="fas fa-location-dot me-1"></i>
                                                <?= e(implode(' · ', array_map(
                                                    static fn($l) => (string)($l['name'] ?? ''),
                                                    array_slice($locs, 0, 3)
                                                ))) ?>
                                            </div>
                                        <?php endif; ?>

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
                                                data-locations="<?= e((string)$p['locations_json']) ?>"
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

            <?php if ($addons): ?>
                <div class="panel-card mt-4">
                    <h6 class="mb-3"><i class="fas fa-sliders me-2"></i>Bảng giá addon</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Loại</th>
                                    <th>Đơn vị</th>
                                    <th class="text-end">Tháng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addons as $ad): ?>
                                    <tr>
                                        <td class="small"><?= e((string)$ad['name']) ?></td>
                                        <td class="small font-numeric"><?= (int)$ad['unit_value'] ?></td>
                                        <td class="small font-numeric text-end">
                                            <?= money((float)$ad['price_monthly']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="form-hint mt-2 mb-0">
                        Giá addon được cộng vào đơn khi nâng cấp VPS hiện có.
                    </p>
                </div>
            <?php endif; ?>

            <div class="panel-card mt-4">
                <h6 class="mb-3"><i class="fas fa-clock-rotate-left me-2"></i>Đơn của tôi</h6>

                <?php if (!$orders): ?>
                    <p class="text-muted-2 small mb-0">Chưa có đơn nào.</p>
                <?php else: ?>
                    <div class="aic-order-list">
                        <?php foreach ($orders as $o):
                            $st = (string)$o['status'];
                            ?>
                            <a href="<?= url('/services/vps-storevps/order/' . (int)$o['id']) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-layer-group text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($o['product_name'] ?: 'VPS')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= $o['order_code'] ? '#' . e((string)$o['order_code']) : 'Chưa có mã' ?>
                                            · <?= e(date('d/m/Y H:i', strtotime((string)$o['created_at']))) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-gold fw-semibold"><?= money((float)$o['amount']) ?></div>
                                        <span class="badge-status st-<?= e(VirtualizorService::statusBadge($st)) ?>">
                                            <?= e(VirtualizorService::statusLabel($st)) ?>
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
                            <a href="<?= url('/services/vps-storevps/manage/' . rawurlencode((string)$v['vps_id'])) ?>" class="aic-order-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-layer-group text-gold"></i>
                                    <div class="min-w-0 flex-fill">
                                        <div class="small fw-semibold text-truncate">
                                            <?= e((string)($v['hostname'] ?: $v['product_name'] ?: 'VPS')) ?>
                                        </div>
                                        <div class="form-hint font-numeric">
                                            <?= e((string)$v['ip']) ?>
                                            · <?= e((string)$v['day_left']) ?>
                                        </div>
                                    </div>
                                    <span class="badge-status st-<?= e(VirtualizorService::vpsStatusBadge((string)$v['vps_status'])) ?>">
                                        <?= e(VirtualizorService::vpsStatusLabel((string)$v['vps_status'])) ?>
                                    </span>
                                    <?php if ((int)$v['auto_renew'] === 1): ?>
                                        <span class="badge-status st-active ms-1" title="Tự động gia hạn">
                                            <i class="fas fa-repeat me-1"></i>Auto
                                        </span>
                                    <?php endif; ?>
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
                    <li>Bấm <b>Đặt đơn</b>, chọn OS, datacenter, hostname và số lượng.</li>
                    <li>Hệ thống trừ ví chính và tạo đơn qua API Virtualizor.</li>
                    <li>VPS khởi tạo qua hàng chờ — theo dõi tại trang đơn.</li>
                    <li>Quản lý nguồn / rebuild / mật khẩu / hostname / gia hạn / addon tại <b>VPS của tôi</b>.</li>
                </ol>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-calculator me-2"></i>Công thức tính giá</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Giá bán đã bao gồm biên lợi nhuận của hệ thống.</li>
                    <li>Tổng = (giá gói + addon + phí datacenter) × số lượng.</li>
                    <li>Không cộng VAT (luôn bằng 0).</li>
                    <li>Hoàn tiền tự động nếu đơn không được chấp nhận.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal đặt đơn ===== -->
<div class="modal fade" id="vzOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt VPS StoreVPS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('/services/vps-storevps/submit') ?>" id="vzOrderForm">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" id="vzProductId" value="">
                <input type="hidden" name="cycle" id="vzCycle" value="<?= e($cycle) ?>">
                <input type="hidden" name="idempotency_key" id="vzIdemKey" value="">

                <div class="modal-body">
                    <div class="alert alert-info mb-3 small">
                        <i class="fas fa-box me-1"></i>
                        <b id="vzProductName">—</b>
                        <div class="form-hint mt-1" id="vzProductSpec"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="vzOsId">Hệ điều hành <span class="text-danger">*</span></label>
                            <select name="os_id" id="vzOsId" class="form-select" required>
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
                            <label class="form-label" for="vzHostname">Hostname <span class="text-danger">*</span></label>
                            <input type="text" name="hostname" id="vzHostname" class="form-control font-numeric"
                                   placeholder="demo-storevps-api" required maxlength="63"
                                   pattern="[a-z0-9][a-z0-9._\-]{1,61}[a-z0-9]">
                            <div class="form-hint">lowercase, a-z 0-9 . _ - (không trùng VPS khác).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="vzLocationId">Datacenter</label>
                            <select name="location_id" id="vzLocationId" class="form-select">
                                <option value="">— Mặc định —</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= e((string)$loc['location_id']) ?>"
                                            data-surcharge="<?= (float)$loc['surcharge'] ?>">
                                        <?= e((string)$loc['name']) ?>
                                        <?php if ((float)$loc['surcharge'] > 0): ?>
                                            (+<?= money((float)$loc['surcharge']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="vzQuantity">Số lượng</label>
                            <input type="number" name="quantity" id="vzQuantity"
                                   class="form-control font-numeric" min="1" max="10" step="1" value="1">
                            <div class="form-hint">Từ 1 đến 10 máy chủ.</div>
                        </div>

                        <?php if ($addons): ?>
                            <div class="col-12">
                                <label class="form-label mb-1">Addon nâng cấp (đơn vị)</label>
                                <div class="row g-2">
                                    <?php foreach ($addons as $ad): ?>
                                        <div class="col-6 col-md-4">
                                            <label class="form-hint d-block" for="vzAddon_<?= e((string)$ad['type_addon']) ?>">
                                                <?= e((string)$ad['name']) ?>
                                            </label>
                                            <input type="number"
                                                   name="addon_<?= e((string)$ad['type_addon']) ?>"
                                                   id="vzAddon_<?= e((string)$ad['type_addon']) ?>"
                                                   class="form-control form-control-sm font-numeric"
                                                   min="0" max="64" step="1" value="0">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="vps-total mt-3 p-3 rounded" style="background:rgba(255,255,255,.04)">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Đơn giá</span>
                            <span id="vzPriceItem">—</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Addon</span>
                            <span id="vzPriceAddon">0 đ</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Phí datacenter</span>
                            <span id="vzPriceLoc">0 đ</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">Số lượng</span>
                            <span id="vzPriceQty">× 1</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-2">VAT</span>
                            <span id="vzPriceVat">0 đ</span>
                        </div>
                        <hr class="my-2" style="border-color:rgba(255,255,255,.12)">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">Tổng cộng</span>
                            <span class="fs-5 fw-bold text-gold" id="vzPriceTotal">—</span>
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
                    <button type="submit" class="btn btn-gold" id="vzSubmitBtn">
                        <i class="fas fa-bolt me-1"></i>Đặt &amp; Thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modalEl = document.getElementById('vzOrderModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);

    var els = {
        productId: document.getElementById('vzProductId'),
        cycle: document.getElementById('vzCycle'),
        idem: document.getElementById('vzIdemKey'),
        name: document.getElementById('vzProductName'),
        spec: document.getElementById('vzProductSpec'),
        os: document.getElementById('vzOsId'),
        host: document.getElementById('vzHostname'),
        loc: document.getElementById('vzLocationId'),
        qty: document.getElementById('vzQuantity'),
        item: document.getElementById('vzPriceItem'),
        addon: document.getElementById('vzPriceAddon'),
        locOut: document.getElementById('vzPriceLoc'),
        qtyOut: document.getElementById('vzPriceQty'),
        vat: document.getElementById('vzPriceVat'),
        total: document.getElementById('vzPriceTotal'),
        submit: document.getElementById('vzSubmitBtn')
    };

    var addonInputs = Array.prototype.slice.call(
        document.querySelectorAll('[id^="vzAddon_"]')
    );

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(n) + ' đ';
    }

    function addonParams() {
        var out = {};
        addonInputs.forEach(function (el) {
            var key = el.id.replace('vzAddon_', '');
            out['addon_' + key] = parseInt(el.value || '0', 10) || 0;
        });
        return out;
    }

    function refreshQuote() {
        if (!els.productId.value) return;
        var qty = parseInt(els.qty.value || '1', 10);
        var url = new URL('<?= url('/services/vps-storevps/quote') ?>', window.location.origin);
        url.searchParams.set('product_id', els.productId.value);
        url.searchParams.set('cycle', els.cycle.value);
        url.searchParams.set('quantity', qty);
        url.searchParams.set('location_id', els.loc.value || '');

        var ap = addonParams();
        Object.keys(ap).forEach(function (k) { url.searchParams.set(k, ap[k]); });

        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) {
                    els.total.textContent = json.message || 'Lỗi tính giá';
                    return;
                }
                var d = json.data;
                els.item.textContent = d.formatted.item;
                els.addon.textContent = d.formatted.addon;
                els.locOut.textContent = d.formatted.surcharge;
                els.qtyOut.textContent = '× ' + d.quantity;
                els.vat.textContent = d.formatted.vat;
                els.total.textContent = d.formatted.total;
            })
            .catch(function () {
                els.total.textContent = '—';
            });
    }

    var quoteTimer = null;
    function scheduleQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(refreshQuote, 220);
    }

    document.querySelectorAll('.js-open-order').forEach(function (btn) {
        btn.addEventListener('click', function () {
            els.productId.value = btn.dataset.product;
            els.name.textContent = btn.dataset.name || '—';
            els.spec.textContent = btn.dataset.spec || '';
            els.item.textContent = fmt(parseFloat(btn.dataset.price || '0'));
            els.total.textContent = fmt(parseFloat(btn.dataset.price || '0'));

            // Chỉ hiện datacenter thuộc gói này
            var locs = [];
            try {
                locs = JSON.parse(btn.dataset.locations || '[]');
            } catch (e) { locs = []; }
            els.loc.innerHTML = '<option value="">— Mặc định —</option>';
            (Array.isArray(locs) ? locs : []).forEach(function (l) {
                var o = document.createElement('option');
                o.value = l.location_id || '';
                o.textContent = l.name || l.location_id || '';
                if (parseFloat(l.surcharge || 0) > 0) {
                    o.textContent += ' (+' + fmt(parseFloat(l.surcharge)) + ')';
                }
                els.loc.appendChild(o);
            });

            // Idempotency key mới cho mỗi lần mở modal
            els.idem.value = 'vzv-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);

            modal.show();
            refreshQuote();
        });
    });

    [els.qty, els.loc].forEach(function (el) {
        el.addEventListener('change', scheduleQuote);
    });
    addonInputs.forEach(function (el) {
        el.addEventListener('input', scheduleQuote);
    });

    // Không cho submit 2 lần
    document.getElementById('vzOrderForm').addEventListener('submit', function () {
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
