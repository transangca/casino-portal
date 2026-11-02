<?php /** @var array $orders,$total,$page,$pages,$q,$category,$categories,$reserve */ ?>
<div class="container py-4">
    <h1 class="page-title">Sàn P2P</h1>
    <p class="page-sub">Mua bán trực tiếp giữa các thành viên — tiền được giữ an toàn trong ví dự phòng của hệ thống cho tới khi cả hai bên xác nhận.</p>

    <div class="p2p-banner">
        <div class="p2p-banner-icon"><i class="fas fa-shield-halved"></i></div>
        <div class="p2p-banner-body">
            <h5>Giao dịch an toàn, thanh toán dễ kiểm tra</h5>
            <p>Tiền của người mua <strong>không</strong> chuyển thẳng cho người bán. Hệ thống giữ tiền ở ví dự phòng, chỉ mở khoá khi <strong>cả hai bên đã xác nhận hoàn tất</strong>.</p>
        </div>
        <a href="<?= url('/p2p/sell') ?>" class="btn btn-gold btn-sm ms-auto">
            <i class="fas fa-plus me-1"></i>Đăng tin bán
        </a>
    </div>

    <div class="d-flex flex-wrap mb-4 gap-2">
        <a href="<?= url('/p2p') ?>" class="filter-chip <?= $category === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($categories as $code => $label): ?>
            <a href="<?= url('/p2p?category=' . urlencode($code)) ?>" class="filter-chip <?= $category === $code ? 'active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="d-flex mb-4" action="<?= url('/p2p') ?>" method="GET">
        <?php if ($category !== ''): ?>
            <input type="hidden" name="category" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="input-group" style="max-width:420px">
            <input type="text" name="q" class="form-control" placeholder="Tìm tin bán..." value="<?= e($q) ?>">
            <button class="btn btn-gold" type="submit"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <?php if (!$orders): ?>
        <div class="empty-state">
            <i class="fas fa-store fs-1 d-block mb-3"></i>
            <p class="mb-0">Chưa có tin bán nào phù hợp.</p>
        </div>
    <?php else: ?>
        <div class="p2p-grid">
            <?php foreach ($orders as $o): ?>
                <a href="<?= url('/p2p/order/' . $o['id']) ?>" class="p2p-card">
                    <div class="p2p-card-head">
                        <span class="p2p-cat"><?= e(\App\Services\P2PService::categoryLabel((string)$o['category'])) ?></span>
                        <?php if ((int)$o['remain'] <= 3): ?>
                            <span class="p2p-hot">Sắp hết</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="p2p-title"><?= e($o['title']) ?></h3>
                    <div class="p2p-seller">
                        <i class="fas fa-user"></i> <?= e($o['seller_name']) ?>
                        <?php if (!empty($o['seller_verified'])): ?>
                            <span class="verified-tick" title="Đã xác minh"><i class="fas fa-check"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="p2p-price">
                        <strong><?= money((float)$o['price']) ?></strong>
                        <span>Còn <?= (int)$o['remain'] ?> / <?= (int)$o['quantity'] ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('/p2p?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
