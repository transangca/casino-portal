<?php /** @var array $summary,$balance */ ?>
<div class="container py-4">
    <h1 class="page-title">CSO / Đối tác</h1>
    <p class="page-sub">Giới thiệu người chơi và nhận hoá hồng từ giao dịch của họ.</p>

    <?php if (empty($summary)): ?>
    <div class="panel-card">
        <h3><i class="fas fa-handshake text-gold me-2"></i>Tạo tài khoản CSO</h3>
        <p class="text-secondary small">Bạn chưa có tài khoản CSO. Tạo ngay để nhận mã giới thiệu và hoá hồng.</p>
        <form method="POST" action="<?= url('/cso/create') ?>" class="mt-3">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="name">Tên hiển thị</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="VD: Cong game cua toi" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Tạo tài khoản</button>
                </div>
            </div>
        </form>
    </div>
    <?php else: ?>
        <?php $acc = $summary['account']; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label mb-1">Mã CSO</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="stat-value" style="font-size:1.4rem"><?= e($acc['code']) ?></span>
                        <button class="btn btn-sm btn-outline-secondary" data-copy="<?= e($acc['code']) ?>"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label mb-1">Người giới thiệu</div>
                    <div class="stat-value"><?= (int)$summary['referred'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label mb-1">Hoá hồng chờ duyệt</div>
                    <div class="stat-value"><?= money((float)$summary['pending']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label mb-1">Đã thành toán</div>
                    <div class="stat-value"><?= money((float)$summary['paid']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card">
                    <h3><i class="fas fa-share-nodes text-gold me-2"></i>Link giới thiệu</h3>
                    <p class="text-secondary small">Chia sẻ link này. Người mới đăng ký quá link sẽ được ghi nhận là người do bạn giới thiệu.</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" readonly id="refLink"
                               value="<?= e(url('/register?ref=' . $acc['code'])) ?>">
                        <button class="btn btn-outline-secondary" data-copy="<?= e(url('/register?ref=' . $acc['code'])) ?>">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="game-provider mb-2">Mã giới thiệu: <strong class="text-gold"><?= e($acc['code']) ?></strong>
                        - Ty le: <?= (float)$acc['commission_rate'] * 100 ?>%</div>
                    <a href="<?= url('/cso/referrals') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-users me-1"></i>Danh sách người giới thiệu
                    </a>
                </div>

                <div class="panel-card">
                    <h3><i class="fas fa-money-bill-transfer text-gold me-2"></i>Rút hoá hồng</h3>
                    <p class="text-secondary small">Số dư ví hiện tại: <strong class="text-gold"><?= money($balance) ?></strong></p>
                    <form method="POST" action="<?= url('/cso/withdraw') ?>"
                          data-confirm=" Rut toan bo hoa hong da duyet ve vi?">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary w-100" <?= (float)$summary['pending'] <= 0 ? 'disabled' : '' ?>>
                            Rút về ví
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="panel-card">
                    <h3><i class="fas fa-chart-line text-gold me-2"></i>Lịch sử hoá hồng</h3>
                    <?php if (!$summary['commissions']): ?>
                        <div class="empty-state py-4"><i class="fas fa-inbox"></i><p>Chưa có hoá hồng nào.</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Thời gian</th><th>Loại</th><th>Tỷ lệ</th><th class="text-end">Hoá hồng</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                            <?php foreach ($summary['commissions'] as $c): ?>
                                <tr>
                                    <td class="text-secondary"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                    <td><span class="badge-status st-processing"><?= e(strtoupper($c['type'])) ?></span></td>
                                    <td class="text-secondary"><?= (float)$c['rate'] * 100 ?>%</td>
                                    <td class="text-end amount-pos">+<?= money((float)$c['amount']) ?></td>
                                    <td><span class="badge-status st-<?= e($c['status']) ?>"><?= e(strtoupper($c['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
