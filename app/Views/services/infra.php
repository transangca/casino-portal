<?php /** @var array|null $check, string $domain, array $plans, array $myDomains, array $tldPrice */ ?>
<div class="container py-4">
    <h1 class="page-title">Domain &amp; Hạ tầng</h1>
    <p class="page-sub">Kiểm tra domain còn hoạt động hay không, đặt mua domain, hosting cPanel và VPS.</p>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-magnifying-glass me-2"></i>Tra cứu domain</h6>
                <form method="POST" action="<?= url('/services/infra/check') ?>">
                    <?= csrf_field() ?>
                    <div class="input-group mb-3">
                        <input type="text" name="domain" class="form-control" placeholder="tenmien.com.vn" value="<?= e($domain) ?>" required>
                        <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>

                <?php if ($check): ?>
                    <div class="domain-status status-<?= e($check['status']) ?>">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas <?= $check['status'] === 'available' ? 'fa-circle-check' : ($check['status'] === 'active' ? 'fa-globe' : 'fa-circle-xmark') ?>"></i>
                            <div>
                                <strong class="d-block"><?= e($check['domain']) ?></strong>
                                <span class="small"><?= e($check['note']) ?></span>
                            </div>
                        </div>
                        <div class="small mt-2">
                            <?php if ($check['dns_active']): ?>
                                <span class="badge bg-success me-1">DNS hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-secondary me-1">Không có DNS</span>
                            <?php endif; ?>
                            <?php if (!empty($check['expires_at'])): ?>
                                <span class="badge bg-info">Hết hạn: <?= e((string)$check['expires_at']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($check['status'] === 'available' && $check['price'] > 0): ?>
                        <form method="POST" action="<?= url('/services/infra/order') ?>" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="domain" value="<?= e($check['domain']) ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-4">
                                    <label class="form-label small">Số năm</label>
                                    <select name="years" class="form-select form-select-sm">
                                        <option value="1">1 năm</option>
                                        <option value="2">2 năm</option>
                                        <option value="3">3 năm</option>
                                    </select>
                                </div>
                                <div class="col-8">
                                    <button class="btn btn-gold btn-sm w-100" type="submit">
                                        Đặt mua · <?= money($check['price']) ?>/năm
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-3"><i class="fas fa-list me-2"></i>Domain của tôi</h6>
                <?php if (!$myDomains): ?>
                    <p class="small text-muted-2 mb-0">Chưa có đơn domain nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Domain</th><th>Năm</th><th>Giá</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                            <?php foreach ($myDomains as $d): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($d['domain']) ?></td>
                                    <td><?= (int)$d['years'] ?></td>
                                    <td><?= money((float)$d['price']) ?></td>
                                    <td><span class="badge bg-info"><?= e((string)$d['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <?php
            $grouped = [];
            foreach ($plans as $p) {
                $grouped[$p['kind']][] = $p;
            }
            $labels = ['hosting' => 'Hosting cPanel', 'vps' => 'VPS', 'mail' => 'Email', 'ssl' => 'SSL'];
            ?>
            <?php foreach ($grouped as $kind => $list): ?>
                <div class="panel-card mb-3">
                    <h6 class="mb-3"><i class="fas fa-server me-2"></i><?= e($labels[$kind] ?? $kind) ?></h6>
                    <?php foreach ($list as $p): ?>
                        <div class="infra-plan">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="d-block small"><?= e($p['name']) ?></strong>
                                    <span class="small text-muted-2"><?= e((string)($p['spec'] ?? '')) ?></span>
                                </div>
                                <div class="text-end">
                                    <strong class="text-gold small"><?= money((float)$p['price_month']) ?>/th</strong>
                                    <div class="small text-muted-2"><?= money((float)$p['price_year']) ?>/năm</div>
                                </div>
                            </div>
                            <?php $feat = json_decode((string)($p['features'] ?? '[]'), true) ?: []; ?>
                            <?php if ($feat): ?>
                                <div class="small text-muted-2 mt-1"><?= e(implode(' · ', $feat)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
