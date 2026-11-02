<?php /** @var array $pl,$bs, string $from,$to */ ?>
<div class="pos-header">
    <div class="pos-header-left">
        <h1>Báo cáo tài chính</h1>
    </div>
</div>

<div class="p-3 p-lg-4">
    <form method="GET" class="panel-card mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-gold btn-sm w-100"><i class="fas fa-file-export me-1"></i>Xuất báo cáo</button>
            </div>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-md-5">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-chart-line me-2 text-gold"></i>Kết quả kinh doanh</h6>
                <table class="table table-sm small mb-0">
                    <tr>
                        <td>Doanh thu thuần</td>
                        <td class="text-end"><?= money((float)$pl['revenue']) ?></td>
                    </tr>
                    <tr>
                        <td>Tổng chi phí</td>
                        <td class="text-end"><?= money((float)$pl['expense']) ?></td>
                    </tr>
                    <tr class="fw-semibold">
                        <td>Lợi nhuận thuần</td>
                        <td class="text-end <?= (float)$pl['profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= money((float)$pl['profit']) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel-card h-100">
                <h6 class="mb-3"><i class="fas fa-scale-balanced me-2 text-gold"></i>Bảng cân đối (<?= e(date('d/m/Y', strtotime((string)$bs['date']))) ?>)</h6>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="acc-block-title">Tài sản</div>
                        <table class="table table-sm small mb-0">
                            <?php foreach ($bs['assets'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <div class="col-6">
                        <div class="acc-block-title">Nguồn vốn</div>
                        <table class="table table-sm small mb-0">
                            <?php foreach ($bs['liab'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($bs['equity'] as $a): ?>
                                <tr>
                                    <td><?= e($a['code']) ?> · <?= e($a['name']) ?></td>
                                    <td class="text-end"><?= money((float)$a['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
