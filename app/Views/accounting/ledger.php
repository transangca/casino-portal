<?php /** @var array $accounts,$rows, string $account,$from,$to */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Sổ cái</h1>
    </div>
</div>

<div class="p-3 p-lg-4">
    <form method="GET" class="panel-card mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Tài khoản</label>
                <select name="account" class="form-select form-select-sm">
                    <option value="">Tất cả tài khoản</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= e((string)$a['code']) ?>" <?= $account === (string)$a['code'] ? 'selected' : '' ?>>
                            <?= e((string)$a['code']) ?> · <?= e((string)$a['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-gold btn-sm w-100"><i class="fas fa-filter me-1"></i>Lọc</button>
            </div>
        </div>
    </form>

    <div class="panel-card">
        <div class="table-responsive">
            <table class="table table-sm small mb-0">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Mã phiếu</th>
                        <th>Tài khoản</th>
                        <th>Diễn giải</th>
                        <th class="text-end">Nợ</th>
                        <th class="text-end">Có</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sumD = 0; $sumC = 0; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $sumD += (float)$r['debit']; $sumC += (float)$r['credit']; ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime((string)$r['doc_date']))) ?></td>
                            <td class="font-monospace"><?= e((string)$r['doc_no']) ?></td>
                            <td><?= e((string)$r['account_code']) ?> · <?= e((string)$r['account_name']) ?></td>
                            <td class="text-muted-2"><?= e((string)($r['note'] ?: $r['partner'] ?: '—')) ?></td>
                            <td class="text-end"><?= (float)$r['debit'] ? money((float)$r['debit']) : '—' ?></td>
                            <td class="text-end"><?= (float)$r['credit'] ? money((float)$r['credit']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-muted-2 text-center py-4">Không có dữ liệu trong khoảng thời gian này.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="4">Tổng cộng</td>
                        <td class="text-end"><?= money($sumD) ?></td>
                        <td class="text-end"><?= money($sumC) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
