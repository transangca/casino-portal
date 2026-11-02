<?php /** @var float $balance */ ?>
<div class="container py-4">
    <h1 class="page-title">Rút tiền</h1>
    <p class="page-sub">Số dư khả dụng: <strong class="text-gold"><?= money($balance) ?></strong></p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <h3><i class="fas fa-money-bill-transfer text-gold me-2"></i>Yêu cầu rút tiền</h3>
                <form method="POST" action="<?= url('/wallet/withdraw') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="amount">Số tiền rút</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="amount" name="amount"
                                   min="<?= (int)setting('withdraw_min', '100000') ?>" step="10000" required>
                            <span class="input-group-text">đ</span>
                        </div>
                        <div class="form-text" style="color:#6c6479">
                            Tối thiểu <?= money((float)setting('withdraw_min', '100000')) ?> ·
                            Phí <?= (float)setting('withdraw_fee_rate', '0.02') * 100 ?>%
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bank_name">Ngân hàng</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="VD: Vietcombank" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="account_number">Số tài khoản</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="account_name">Tên chủ tài khoản</label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required>
                    </div>
                    <button class="btn btn-primary w-100 py-2">Gửi yêu cầu rút tiền</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel-card">
                <h3><i class="fas fa-info-circle text-gold me-2"></i>Quy trình rút tiền</h3>
                <ol class="text-secondary small ps-3 mb-0">
                    <li class="mb-2">Gửi yêu cầu rút kèm thông tin tài khoản nhận tiền.</li>
                    <li class="mb-2">Admin kiểm trả và duyệt yêu cầu.</li>
                    <li class="mb-2">Tiền được chuyển khoản trực tiếp tới tài khoản của bạn.</li>
                    <li>Phí rút được trừ khi gửi yêu cầu.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
