<?php /** @var array $codes */ $adminPage = 'giftcodes'; ?>
<h1 class="page-title">Giftcode</h1>
<p class="page-sub">Tạo mã quà tặng để người chơi đổi thẳng vào ví.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <h3><i class="fas fa-plus-circle text-gold me-2"></i>Tạo giftcode</h3>
            <form method="POST" action="<?= url('/admin/giftcodes/create') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="code">Mã</label>
                    <input type="text" class="form-control" id="code" name="code" placeholder="VD: WELCOME100" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="amount">Số tiền (đ)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="1000" step="1000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="bonus_amount">Thưởng thêm (đ)</label>
                        <input type="number" class="form-control" id="bonus_amount" name="bonus_amount" min="0" step="1000" value="0">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="min_deposit">Nạp tối thiểu</label>
                        <input type="number" class="form-control" id="min_deposit" name="min_deposit" min="0" step="1000" value="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="max_uses">Số lượt dùng</label>
                        <input type="number" class="form-control" id="max_uses" name="max_uses" min="1" value="1">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="expires_at">Hết hạn (không bắt buộc)</label>
                    <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                </div>
                <button class="btn btn-primary w-100">Tạo giftcode</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <h3><i class="fas fa-gift text-gold me-2"></i>Danh sách</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Mã</th><th class="text-end">Số tiền</th><th>Lượt</th><th>Hết hạn</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php foreach ($codes as $c): ?>
                        <tr>
                            <td><code><?= e($c['code']) ?></code></td>
                            <td class="text-end text-gold"><?= money((float)$c['amount']) ?></td>
                            <td class="text-secondary"><?= (int)$c['used_count'] ?>/<?= (int)$c['max_uses'] ?></td>
                            <td class="text-secondary small"><?= $c['expires_at'] ? date('d/m/Y', strtotime($c['expires_at'])) : '—' ?></td>
                            <td><span class="badge-status st-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Off' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
