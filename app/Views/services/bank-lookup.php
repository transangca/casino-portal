<?php
/**
 * Tra cứu ngân hàng — có logo ngân hàng chính thức, input luôn đọc được.
 *
 * @var array $banks
 * @var string $accountNo
 * @var string $bankCode
 * @var array|null $result
 */
$banks = $banks ?? [];
?>
<div class="container py-4" style="max-width:880px">
    <h1 class="page-title"><i class="fas fa-building-columns text-gold me-2"></i>Tra cứu ngân hàng</h1>
    <p class="page-sub">Tra cứu chủ tài khoản ngân hàng Việt Nam theo số tài khoản.</p>

    <div class="panel-card">
        <form method="GET" action="<?= url('/services/bank-lookup') ?>" id="bankLookupForm">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="bank_code">Ngân hàng</label>
                    <select name="bank_code" id="bank_code" class="form-select" required>
                        <option value="">-- Chọn ngân hàng --</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?= e($b['code']) ?>" <?= $bankCode === $b['code'] ? 'selected' : '' ?>>
                                <?= e($b['shortName'] ?: $b['name']) ?> (<?= e($b['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="account_no">Số tài khoản</label>
                    <div class="input-group">
                        <input type="text" name="account_no" id="account_no" class="form-control font-numeric"
                               placeholder="Nhập số tài khoản (chỉ số)" value="<?= e($accountNo) ?>"
                               inputmode="numeric" autocomplete="off" required>
                        <button class="btn btn-gold" type="submit">
                            <i class="fas fa-magnifying-glass me-1"></i>Tra cứu
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($result): ?>
            <?php if (!empty($result['ok'])): ?>
                <?php $logoUrl = ''; ?>
                <?php foreach ($banks as $b): ?>
                    <?php if ((string)$b['code'] === (string)($result['bank_code'] ?? '') && !empty($b['logo'])): ?>
                        <?php $logoUrl = (string)$b['logo']; break; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="bank-result mt-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if ($logoUrl !== ''): ?>
                            <span class="brand-icon">
                                <img src="<?= e($logoUrl) ?>" alt="<?= e((string)$result['bank_name']) ?>"
                                     loading="lazy" onerror="this.remove()">
                            </span>
                        <?php else: ?>
                            <i class="fas fa-building-columns text-gold fs-3"></i>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <strong class="d-block text-truncate"><?= e((string)$result['account_name']) ?></strong>
                            <span class="small text-muted-2"><?= e((string)$result['bank_name']) ?></span>
                        </div>
                        <span class="badge-status st-active ms-auto">
                            <?= ($result['source'] ?? '') === 'internal' ? 'Nội bộ' : 'VietQR' ?>
                        </span>
                    </div>
                    <table class="table table-sm small mb-0">
                        <tr>
                            <td class="text-muted-2" style="width:38%">Số tài khoản</td>
                            <td class="fw-semibold font-numeric"><?= e((string)$result['account_no']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted-2">Chủ tài khoản</td>
                            <td><?= e((string)$result['account_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted-2">Chi nhánh</td>
                            <td><?= e((string)($result['branch'] ?? '—')) ?></td>
                        </tr>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-4 mb-0">
                    <i class="fas fa-circle-info me-1"></i><?= e((string)($result['message'] ?? 'Không tìm thấy.')) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Danh mục ngân hàng kèm logo -->
    <?php if ($banks): ?>
        <div class="panel-card mt-4">
            <h3 class="mb-3" style="font-size:1rem"><i class="fas fa-building-columns text-gold me-2"></i>Ngân hàng hỗ trợ</h3>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($banks as $b): ?>
                    <?php if (empty($b['logo'])) { continue; } ?>
                    <a href="<?= url('/services/bank-lookup?bank_code=' . urlencode((string)$b['code']) . '&account_no=' . urlencode($accountNo)) ?>"
                       class="brand-chip" title="<?= e($b['name']) ?>">
                        <span class="brand-badge">
                            <img src="<?= e($b['logo']) ?>" alt="<?= e($b['shortName']) ?>" loading="lazy" onerror="this.remove()">
                        </span>
                        <span class="brand-name"><?= e($b['shortName']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
/* Chỉ cho phép nhập số trong ô số tài khoản */
(function () {
    var el = document.getElementById('account_no');
    if (!el) return;
    el.addEventListener('input', function () {
        el.value = el.value.replace(/\D+/g, '');
    });
})();
</script>
