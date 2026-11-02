<?php /** @var array $items,$total,$page,$pages,$kind,$q,$kinds */ ?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div>
            <h1 class="page-title mb-1"><i class="fas fa-network-wired me-2 text-gold"></i>Kho proxy</h1>
            <p class="page-sub mb-0">Proxy HTTP, SOCKS5, residential và mobile 4G cho mọi nhu cầu — thanh toán ngay bằng số dư ví.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="<?= url('/services/proxy/orders') ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-receipt me-1"></i>Proxy đã mua
            </a>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= url('/services/proxy') ?>" class="filter-chip <?= $kind === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($kinds as $code => $label): ?>
            <a href="<?= url('/services/proxy?kind=' . urlencode($code)) ?>" class="filter-chip <?= $kind === $code ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <form class="mb-3" action="<?= url('/services/proxy') ?>" method="GET">
                <div class="input-group" style="max-width:360px">
                    <input type="text" name="q" class="form-control" placeholder="Tìm proxy..." value="<?= e($q) ?>">
                    <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <?php if (!$items): ?>
                <div class="empty-state">
                    <i class="fas fa-network-wired fs-1 d-block mb-3"></i>
                    <p class="mb-0">Chưa có gói proxy nào. Hãy là người đầu tiên đăng bán.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($items as $p): ?>
                        <div class="col-sm-6">
                            <div class="proxy-card h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <strong class="small"><?= e($p['title']) ?></strong>
                                    <span class="badge bg-info text-nowrap"><?= e($kinds[$p['kind']] ?? $p['kind']) ?></span>
                                </div>
                                <div class="small text-muted-2 mt-1">
                                    <?= e((string)($p['country'] ?? '')) ?><?= $p['city'] ? ' · ' . e((string)$p['city']) : '' ?>
                                    <?= $p['isp'] ? ' · ' . e((string)$p['isp']) : '' ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="small"><i class="fas fa-clock me-1"></i><?= (int)$p['duration_days'] ?> ngày · <?= (int)$p['threads'] ?> luồng</span>
                                    <strong class="text-gold small"><?= money((float)$p['price']) ?></strong>
                                </div>
                                <div class="small text-muted-2 mt-1">
                                    <i class="fas fa-user me-1"></i><?= e((string)($p['seller_name'] ?? '')) ?>
                                    <?php if (!empty($p['seller_verified'])): ?>
                                        <span class="verified-tick" title="Đã xác minh"><i class="fas fa-check"></i></span>
                                    <?php endif; ?>
                                    <?php if ((int)($p['stock'] ?? -1) >= 0): ?>
                                        · còn <?= (int)$p['stock'] ?> gói
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto pt-3">
                                    <form method="POST" action="<?= url('/services/proxy/purchase') ?>"
                                          class="d-flex gap-2 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="listing_id" value="<?= (int)$p['id'] ?>">
                                        <input type="number" name="quantity" value="1" min="1"
                                               max="<?= (int)($p['stock'] ?? 1) > 0 ? (int)$p['stock'] : 9999 ?>"
                                               class="form-control form-control-sm font-numeric" style="max-width:78px">
                                        <button class="btn btn-gold btn-sm flex-fill">
                                            <i class="fas fa-bag-shopping me-1"></i>Mua ngay
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('/services/proxy?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Đăng gói bán</h6>
                <form method="POST" action="<?= url('/services/proxy/create') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Tên gói" required>
                    </div>
                    <div class="mb-2">
                        <select name="kind" class="form-select form-select-sm">
                            <?php foreach ($kinds as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" name="country" class="form-control form-control-sm" placeholder="Quốc gia"></div>
                        <div class="col-6"><input type="text" name="city" class="form-control form-control-sm" placeholder="Thành phố"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="number" name="duration_days" class="form-control form-control-sm" value="30" min="1"></div>
                        <div class="col-6"><input type="number" name="threads" class="form-control form-control-sm" value="1" min="1"></div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="endpoint" class="form-control form-control-sm"
                               placeholder="IP:port hoặc user:pass@ip:port">
                    </div>
                    <div class="mb-3">
                        <input type="number" name="price" class="form-control form-control-sm" placeholder="Giá (VNĐ)" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-gold btn-sm w-100">Đăng bán</button>
                </form>
            </div>

            <div class="panel-card mt-4">
                <h6 class="mb-2"><i class="fas fa-shield-halved me-2"></i>Mua an toàn</h6>
                <ul class="small text-muted-2 mb-0" style="padding-left:1.1rem">
                    <li>Thanh toán bằng số dư ví — không cần chuyển khoản thủ công.</li>
                    <li>Thông tin proxy hiển thị ngay sau khi thanh toán thành công.</li>
                    <li>Lịch sử mua được lưu tại trang "Proxy đã mua".</li>
                </ul>
            </div>
        </div>
    </div>
</div>
