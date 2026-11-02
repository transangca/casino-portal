<?php /** @var string $category, array $categories,$products,$customers,$orders,$stats,$paymentMethods */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1><?= e($categories[$category] ?? 'Quản lý bán hàng') ?></h1>
        <div class="pos-stats">
            <span><i class="fas fa-calendar-day"></i> Hôm nay: <strong><?= money($stats['today']) ?></strong></span>
            <span><i class="fas fa-chart-line"></i> Tháng: <strong><?= money($stats['month']) ?></strong></span>
            <span><i class="fas fa-receipt"></i> Đơn hôm nay: <strong><?= (int)$stats['orders_today'] ?></strong></span>
            <span><i class="fas fa-box"></i> Sản phẩm: <strong><?= (int)$stats['products'] ?></strong></span>
            <span><i class="fas fa-users"></i> Khách: <strong><?= (int)$stats['customers'] ?></strong></span>
        </div>
    </div>
</div>

<div class="pos-body-grid">
    <!-- ===== Danh sách sản phẩm ===== -->
    <section class="pos-products">
        <div class="pos-search">
            <input type="text" id="posSearch" class="form-control" placeholder="Tìm sản phẩm...">
        </div>
        <div class="pos-product-grid" id="posProductGrid">
            <?php foreach ($products as $p): ?>
                <button type="button" class="pos-product" data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" data-price="<?= (float)$p['price'] ?>">
                    <span class="pos-product-name"><?= e($p['name']) ?></span>
                    <span class="pos-product-price"><?= money((float)$p['price']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===== Giỏ hàng ===== -->
    <aside class="pos-cart">
        <h3 class="pos-cart-title"><i class="fas fa-receipt me-2"></i>Giỏ hàng</h3>

        <div class="pos-cart-items" id="posCartItems">
            <div class="pos-cart-empty" id="posCartEmpty">
                <i class="fas fa-basket-shopping"></i>
                <span>Chưa có sản phẩm nào</span>
            </div>
        </div>

        <div class="pos-cart-summary">
            <div class="pos-sum-row"><span>Tạm tính</span><strong id="posSubtotal">0 đ</strong></div>
            <div class="pos-sum-row">
                <span>Giảm giá</span>
                <input type="number" id="posDiscount" class="form-control form-control-sm" value="0" min="0" step="1000">
            </div>
            <div class="pos-sum-row total"><span>Tổng cộng</span><strong id="posTotal">0 đ</strong></div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <input type="text" id="posCustomerName" class="form-control form-control-sm" placeholder="Tên khách">
                </div>
                <div class="col-6">
                    <input type="text" id="posCustomerPhone" class="form-control form-control-sm" placeholder="SĐT khách">
                </div>
            </div>

            <select id="posPaymentMethod" class="form-select form-select-sm mb-2">
                <?php foreach ($paymentMethods as $code => $label): ?>
                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-gold w-100" id="posCheckout">
                <i class="fas fa-check-circle me-1"></i>Thanh toán
            </button>
        </div>
    </aside>
</div>

<!-- ===== Đơn hàng gần đây ===== -->
<section class="pos-orders">
    <h3 class="pos-section-title"><i class="fas fa-clock-rotate-left me-2"></i>Đơn hàng gần đây</h3>
    <div class="table-responsive">
        <table class="table table-sm small align-middle mb-0">
            <thead><tr><th>Mã</th><th>Khách</th><th>Tổng</th><th>Thanh toán</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e((string)$o['order_no']) ?></td>
                    <td><?= e((string)($o['customer_name'] ?: '—')) ?></td>
                    <td class="text-gold fw-semibold"><?= money((float)$o['total']) ?></td>
                    <td><?= e($paymentMethods[$o['payment_method']] ?? (string)$o['payment_method']) ?></td>
                    <td>
                        <span class="badge bg-<?= $o['status'] === 'paid' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'info') ?>">
                            <?= e((string)$o['status']) ?>
                        </span>
                    </td>
                    <td class="text-muted-2"><?= e(date('d/m H:i', strtotime((string)$o['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
const POS = {
    cart: [],
    add(id, name, price) {
        const found = this.cart.find(i => i.product_id === id);
        if (found) { found.qty++; }
        else { this.cart.push({ product_id: id, name: name, price: price, qty: 1 }); }
        this.render();
    },
    change(id, delta) {
        const item = this.cart.find(i => i.product_id === id);
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) {
            this.cart = this.cart.filter(i => i.product_id !== id);
        }
        this.render();
    },
    render() {
        const box = document.getElementById('posCartItems');
        const empty = document.getElementById('posCartEmpty');
        let html = '';
        let subtotal = 0;
        this.cart.forEach(i => {
            const line = i.price * i.qty;
            subtotal += line;
            html += '<div class="pos-cart-item">'
                + '<div class="flex-grow-1"><div class="pos-cart-item-name">' + i.name + '</div>'
                + '<div class="small text-muted-2">' + i.price.toLocaleString('vi-VN') + ' đ</div></div>'
                + '<div class="pos-qty">'
                + '<button type="button" data-act="dec" data-id="' + i.product_id + '">−</button>'
                + '<span>' + i.qty + '</span>'
                + '<button type="button" data-act="inc" data-id="' + i.product_id + '">+</button>'
                + '</div>'
                + '<div class="pos-cart-line">' + line.toLocaleString('vi-VN') + ' đ</div>'
                + '</div>';
        });
        box.innerHTML = html || '';
        if (empty) empty.style.display = this.cart.length ? 'none' : 'flex';

        const discount = parseFloat(document.getElementById('posDiscount').value || 0);
        const total = Math.max(0, subtotal - discount);
        document.getElementById('posSubtotal').textContent = subtotal.toLocaleString('vi-VN') + ' đ';
        document.getElementById('posTotal').textContent = total.toLocaleString('vi-VN') + ' đ';
    },
    checkout() {
        if (!this.cart.length) { alert('Giỏ hàng đang trống.'); return; }
        const payload = new URLSearchParams();
        payload.append('items', JSON.stringify(this.cart));
        payload.append('discount', document.getElementById('posDiscount').value || 0);
        payload.append('payment_method', document.getElementById('posPaymentMethod').value);
        payload.append('customer_name', document.getElementById('posCustomerName').value);
        payload.append('customer_phone', document.getElementById('posCustomerPhone').value);
        payload.append('category', '<?= e($category) ?>');
        payload.append('_csrf_token', window.APP?.csrfToken || '');

        fetch('<?= url('/services/pos/order') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: payload
        }).then(r => r.json()).then(json => {
            if (json.success) {
                Swal.fire({ icon: 'success', title: 'Thành công', text: json.message || 'Đã tạo đơn', showConfirmButton: false, timer: 1200 })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: json.message || 'Không tạo được đơn' });
            }
        }).catch(e => {
            Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
        });
    }
};

document.getElementById('posProductGrid')?.addEventListener('click', function (e) {
    const btn = e.target.closest('.pos-product');
    if (!btn) return;
    POS.add(parseInt(btn.dataset.id), btn.dataset.name, parseFloat(btn.dataset.price));
});

document.getElementById('posCartItems')?.addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    POS.change(parseInt(btn.dataset.id), btn.dataset.act === 'inc' ? 1 : -1);
});

document.getElementById('posSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#posProductGrid .pos-product').forEach(el => {
        el.style.display = el.dataset.name.toLowerCase().includes(q) ? '' : 'none';
    });
});

document.getElementById('posDiscount')?.addEventListener('input', () => POS.render());
document.getElementById('posCheckout')?.addEventListener('click', () => POS.checkout());
</script>
