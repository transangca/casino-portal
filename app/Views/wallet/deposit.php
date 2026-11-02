<?php
/**
 * Nạp tiền — tạo mã QR VietQR + gửi yêu cầu nạp thủ công + giftcode + thẻ cào.
 *
 * @var float $balance
 * @var array $methods
 * @var array $banks
 * @var array $cardDenoms
 */
$bonusRate = (float)setting('deposit_bonus_rate', '0.10');
$banks     = $banks ?? [];
$cardDenoms = $cardDenoms ?? [];
$presets   = [50000, 100000, 200000, 500000, 1000000, 2000000, 5000000, 10000000];
?>
<div class="container py-4">
    <h1 class="page-title mb-1"><i class="fas fa-circle-plus text-gold me-2"></i>Nạp tiền</h1>
    <p class="page-sub">Số dư hiện tại: <strong class="text-gold"><?= money($balance) ?></strong></p>

    <div class="row g-4">
        <!-- ============ Cột trái: tạo QR ============ -->
        <div class="col-lg-7">
            <div class="casino-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-qrcode text-gold"></i>
                    <span>Tạo mã QR thanh toán</span>
                </div>
                <div class="card-body">

                    <!-- Bước 1: số tiền -->
                    <div class="mb-4">
                        <label class="form-label" for="amount">
                            <i class="fas fa-1 me-1 text-gold"></i>Số tiền muốn nạp
                        </label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control font-numeric" id="amount" name="amount"
                                   inputmode="numeric" min="1" step="1000" value="" placeholder="Nhập số tiền tùy ý">
                            <span class="input-group-text">đ</span>
                        </div>
                        <div class="form-hint mt-2">
                            <i class="fas fa-circle-info me-1"></i>
                            Bạn nhập số tiền tùy ý — không có mức tối thiểu / tối đa cố định.
                        </div>

                        <!-- Gợi ý nhanh -->
                        <div class="amount-presets mt-3" id="amountPresets">
                            <?php foreach ($presets as $q): ?>
                                <button type="button" class="amount-preset" data-amount="<?= $q ?>">
                                    <?= number_format($q) ?>đ
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bước 2: ngân hàng -->
                    <div class="mb-4">
                        <label class="form-label" for="bank">
                            <i class="fas fa-2 me-1 text-gold"></i>Ngân hàng nhận tiền
                        </label>
                        <select class="form-select" id="bank" name="bank">
                            <option value="">— Chọn ngân hàng —</option>
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= e($b['bank_code']) ?>"
                                        data-name="<?= e($b['bank_name']) ?>"
                                        data-no="<?= e($b['account_no']) ?>"
                                        data-acq="<?= e($b['acq_id']) ?>">
                                    <?= e($b['bank_name']) ?> — <?= e($b['account_no']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$banks): ?>
                            <div class="form-hint mt-2 text-warning-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Admin chưa cấu hình tài khoản nhận tiền. Vui lòng liên hệ hỗ trợ.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tóm tắt -->
                    <div id="summaryBox" class="alert alert-info d-none d-flex justify-content-between align-items-center mb-4">
                        <span><i class="fas fa-calculator me-2"></i>Tổng nhận được</span>
                        <strong id="bonusText" class="font-numeric">—</strong>
                    </div>

                    <button type="button" id="btnCreateQr" class="btn btn-gold w-100 py-2 fw-bold" disabled>
                        <i class="fas fa-qrcode me-2"></i>Tạo mã QR VietQR
                    </button>

                    <!-- Kết quả QR -->
                    <div id="qrBox" class="d-none mt-4 text-center">
                        <div class="qr-box">
                            <img id="qrImg" alt="Mã QR thanh toán" src="">
                        </div>
                        <div id="qrInfo" class="mt-3"></div>
                        <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>In mã
                            </button>
                            <a id="qrDownload" class="btn btn-sm btn-outline-light" download="vietqr.png" href="#">
                                <i class="fas fa-download me-1"></i>Tải ảnh
                            </a>
                        </div>
                        <p class="form-hint mt-3 mb-0">
                            <i class="fas fa-shield-halved me-1"></i>
                            Chuyển khoản đúng số tiền và giữ nguyên nội dung để được đối soát nhanh nhất.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ Cột phải ============ -->
        <div class="col-lg-5">
            <!-- Gửi yêu cầu nạp -->
            <div class="casino-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-file-invoice text-gold"></i>
                    <span>Đã chuyển khoản — gửi yêu cầu</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/wallet/deposit') ?>" enctype="multipart/form-data" id="depositForm">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="amount2">Số tiền đã chuyển</label>
                            <div class="input-group">
                                <input type="number" class="form-control font-numeric" id="amount2" name="amount"
                                       min="1" step="1000" placeholder="Nhập số tiền" required>
                                <span class="input-group-text">đ</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="method">Phương thức thanh toán</label>
                            <select class="form-select" id="method" name="method">
                                <option value="bank">Chuyển khoản ngân hàng</option>
                                <option value="momo">MoMo</option>
                                <option value="zalo">ZaloPay</option>
                                <option value="card">Thẻ tín dụng / ATM</option>
                                <option value="crypto">Crypto (USDT)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="transaction_code">Mã giao dịch</label>
                            <input type="text" class="form-control" id="transaction_code" name="transaction_code"
                                   placeholder="VD: MOMO12345678" required>
                            <div class="form-hint mt-1">Nhập mã giao dịch bạn đã thực hiện để được đối soát nhanh hơn</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bill_image">
                                Ảnh bill chuyển khoản
                                <span class="text-secondary">- không bắt buộc</span>
                            </label>
                            <input type="file" class="form-control" id="bill_image" name="bill_image"
                                   accept="image/png,image/jpeg,image/webp,image/gif">
                            <div class="form-hint mt-1">
                                <i class="fas fa-camera me-1"></i>
                                Chụp màn hình biên lai chuyển khoản để được duyệt nhanh nhất (JPG/PNG/WEBP, tối đa 5MB).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bill_note">
                                Ghi chú
                                <span class="text-secondary">- không bắt buộc</span>
                            </label>
                            <textarea class="form-control" id="bill_note" name="bill_note" rows="2"
                                      placeholder="VD: Đã chuyển từ tài khoản ngân hàng ..."></textarea>
                        </div>
                        <button class="btn btn-gold w-100 py-2" type="submit" id="depositSubmitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Gửi yêu cầu nạp tiền
                        </button>
                    </form>
                </div>
            </div>

            <!-- Giftcode -->
            <div class="casino-card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-gift text-gold"></i>
                    <span>Giftcode</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/wallet/giftcode') ?>">
                        <?= csrf_field() ?>
                        <div class="input-group">
                            <input type="text" class="form-control" name="code" placeholder="Nhập mã giftcode" required>
                            <button class="btn btn-gold">Đổi</button>
                        </div>
                    </form>
                    <p class="form-hint mt-2 mb-0">
                        <i class="fas fa-circle-info me-1"></i>
                        Khuyến mãi nạp tiền hiện tại:
                        <strong class="text-gold"><?= $bonusRate * 100 ?>%</strong>
                    </p>
                </div>
            </div>

            <!-- ============ THẺ CÀO ============ -->
            <div class="casino-card mb-4" id="cardSection">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-credit-card text-gold"></i>
                    <span>Nạp bằng thẻ cào</span>
                    <span class="ms-auto form-hint">Chọn mệnh giá → nhập serial + PIN</span>
                </div>
                <div class="card-body">
                    <?php if (!$cardDenoms): ?>
                        <div class="form-hint text-warning-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Admin chưa cấu hình mệnh giá thẻ cào. Vui lòng liên hệ hỗ trợ.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= url('/wallet/deposit/card') ?>" id="cardForm" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="cardDenom">Mệnh giá thẻ</label>
                                <select class="form-select" id="cardDenom" name="denomination" required
                                        data-role="card-denom">
                                    <option value="">— Chọn mệnh giá —</option>
                                    <?php foreach ($cardDenoms as $d): ?>
                                        <option value="<?= e((string)$d['code']) ?>"
                                                data-amount="<?= e((string)$d['amount']) ?>"
                                                data-name="<?= e((string)$d['name']) ?>"
                                                <?= (int)$d['stock'] <= 0 ? ' disabled' : '' ?>>
                                            <?= e((string)$d['name']) ?>
                                            <?= (int)$d['stock'] > 0 ? '— còn ' . (int)$d['stock'] : '— hết hàng' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label" for="cardSerial">Serial</label>
                                    <input type="text" class="form-control font-numeric" id="cardSerial" name="serial"
                                           placeholder="VD: 1234567890" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cardPin">Mã PIN</label>
                                    <input type="text" class="form-control font-numeric" id="cardPin" name="pin"
                                           placeholder="VD: 1234" required>
                                </div>
                            </div>
                            <button class="btn btn-outline-gold w-100 py-2">
                                <i class="fas fa-bolt me-2"></i>Quy đổi thẻ cào
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============ DANH MỤC THẺ CÀO (LOGO) ============ -->
            <?php if ($cardDenoms): ?>
                <div class="casino-card">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="fas fa-layer-group text-gold"></i>
                        <span>Danh mục thẻ cào</span>
                    </div>
                    <div class="card-body">
                        <div class="brand-grid">
                            <?php
                            $seenTypes = [];
                            foreach ($cardDenoms as $d):
                                $type = (string)$d['type'];
                                if (isset($seenTypes[$type])) {
                                    continue;
                                }
                                $seenTypes[$type] = true;
                                $logo = \App\Services\CardService::typeLogo($type);
                                $typeName = [
                                    'viettel'   => 'Viettel',
                                    'mobifone'  => 'Mobifone',
                                    'vinaphone' => 'Vinaphone',
                                    'game'      => 'Thẻ Game',
                                    'telco'     => 'Viễn thông',
                                    'other'     => 'Khác',
                                ][$type] ?? 'Thẻ cào';
                                ?>
                                <div class="brand-card is-brand">
                                    <span class="brand-icon">
                                        <?php if ($logo !== ''): ?>
                                            <img src="<?= e($logo) ?>" alt="<?= e($typeName) ?>"
                                                 loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <i class="fas fa-credit-card"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="brand-name"><?= e($typeName) ?></span>
                                    <span class="brand-sub">
                                        <?= count(array_filter($cardDenoms, static fn($x) => (string)$x['type'] === $type)) ?> mệnh giá
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Đồng bộ ví -->
            <div class="casino-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-sync text-gold"></i>
                    <span>Đồng bộ ví</span>
                </div>
                <div class="card-body">
                    <p class="text-muted-2 small mb-0">
                        Sau khi admin duyệt, số dư được cộng thẳng vào ví trên website.
                        Khi bạn chơi game, số dư đó được dùng ngay — và tiền thắng cược cũng
                        được ghi nhận trở lại ví và lịch sử giao dịch. Không có bước chuyển đổi thủ công nào.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Truyền tỷ lệ bonus sang JS (app.js đọc APP.bonusRate)
window.APP = window.APP || {};
window.APP.bonusRate = <?= json_encode($bonusRate) ?>;
</script>
<script>
// Ngăn gửi trùng khi upload ảnh bill
document.getElementById('depositForm')?.addEventListener('submit', function () {
    var btn = document.getElementById('depositSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Đang gửi...';
    }
});
</script>
