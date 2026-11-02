<?php
/**
 * Chi tiết tài nguyên trong chợ.
 *
 * @var array $item
 */
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small" style="--bs-breadcrumb-divider:'›'">
            <li class="breadcrumb-item"><a href="<?= url('/market') ?>">Chợ tài nguyên</a></li>
            <li class="breadcrumb-item active"><?= e($item['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <?php if (!empty($item['image'])): ?>
                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>"
                         class="w-100 rounded mb-3" style="max-height:340px;object-fit:cover"
                         onerror="this.onerror=null;this.src='<?= asset('img/placeholder.svg') ?>';">
                <?php endif; ?>

                <span class="news-tag"><?= e(\App\Services\MarketService::categoryLabel((string)$item['category'])) ?></span>
                <h1 class="h4 fw-bold mt-2 mb-2"><?= e($item['title']) ?></h1>

                <p class="text-muted-2 small">
                    <i class="fas fa-user me-1"></i><?= e($item['seller_name']) ?>
                    <?php if (!empty($item['seller_verified'])): ?>
                        <i class="fas fa-check-circle text-info ms-1" title="Đã xác minh"></i>
                    <?php endif; ?>
                    · <i class="fas fa-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($item['created_at']))) ?>
                    <?php if ((int)$item['sold_count'] > 0): ?>
                        · <i class="fas fa-shopping-bag me-1"></i>Đã bán <?= (int)$item['sold_count'] ?>
                    <?php endif; ?>
                </p>

                <?php if (!empty($item['description'])): ?>
                    <div class="border-top pt-3 mt-3" style="border-color:var(--casino-line-soft)!important">
                        <p class="mb-0" style="white-space:pre-line;line-height:1.7"><?= e($item['description']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card">
                <div class="price-tag mb-1"><?= money((float)$item['price']) ?></div>
                <p class="form-hint mb-3">
                    <?php if ((int)$item['stock'] < 0): ?>
                        <i class="fas fa-infinity me-1"></i>Còn hàng
                    <?php else: ?>
                        Còn <?= (int)$item['stock'] ?> sản phẩm
                    <?php endif; ?>
                </p>

                <?php if (\App\Core\Auth::check()): ?>
                    <?php if ((int)\App\Core\Auth::id() === (int)$item['seller_id']): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-circle-info me-1"></i>Đây là sản phẩm của bạn.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= url('/market/buy') ?>"
                              data-confirm="Xác nhận mua sản phẩm này với giá <?= money((float)$item['price']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label" for="note">Ghi chú cho người bán</label>
                                <input type="text" class="form-control" id="note" name="note" maxlength="255">
                            </div>
                            <button class="btn btn-gold w-100 py-2 fw-bold">
                                <i class="fas fa-cart-shopping me-2"></i><?= t('market.buy') ?>
                            </button>
                        </form>

                        <?php if (!empty($item['contact'])): ?>
                            <div class="alert alert-info mt-3 mb-0 small">
                                <i class="fas fa-circle-info me-1"></i>Liên hệ: <strong><?= e($item['contact']) ?></strong>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= url('/login') ?>" class="btn btn-gold w-100 py-2 fw-bold">
                        <i class="fas fa-right-to-bracket me-2"></i>Đăng nhập để mua
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
