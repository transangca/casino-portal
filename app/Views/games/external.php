<?php /** @var array $game, string $launchUrl */ ?>
<div class="container py-4" style="max-width:720px">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/games') ?>">Kho game</a></li>
            <li class="breadcrumb-item active"><?= e($game['name']) ?></li>
        </ol>
    </nav>

    <div class="panel-card text-center">
        <div class="mb-3">
            <i class="fas fa-arrow-up-right-from-square fa-3x text-gold"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">Chơi <span class="text-gold"><?= e($game['name']) ?></span></h1>
        <p class="text-muted-2 small mb-4">
            Sảnh <strong><?= e($game['provider_name']) ?></strong> chưa công bố API phát game trực tiếp
            (chỉ có trang landing chính thức). Để tránh hiển thị website của nhà phát hành trong khung nhúng,
            bạn sẽ được chuyển sang tab mới.
        </p>

        <a href="<?= e($launchUrl) ?>" target="_blank" rel="noopener" class="btn btn-gold px-4 py-2">
            <i class="fas fa-play me-2"></i>Mở game ở tab mới
        </a>

        <div class="d-flex justify-content-center gap-2 mt-3">
            <a href="<?= url('/games/' . $game['slug']) ?>" class="btn btn-outline-gold btn-sm">
                <i class="fas fa-circle-info me-1"></i>Thông tin game
            </a>
            <a href="<?= url('/lobbies') ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-layer-group me-1"></i>Xem sảnh khác
            </a>
        </div>

        <p class="small text-muted-2 mt-4 mb-0">
            <i class="fas fa-flask me-1"></i>Chế độ chơi thử — dùng tiền ảo, không đồng bộ ví.
        </p>
    </div>
</div>
