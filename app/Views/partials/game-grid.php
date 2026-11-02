<?php
/**
 * Partial hiển thị lưới game.
 *
 * @var array      $gameList   danh sách game cần hiển thị
 * @var array|null $providers  danh sách nhà cung cấp (để tra tên + logo)
 */
$gameList   = $gameList   ?? ($games ?? []);
$providers  = $providers  ?? [];

$providerMap = [];
foreach ($providers as $p) {
    $providerMap[(int)$p['id']] = $p;
}
// Tự bổ sung nhà phát hành còn thiếu (để game card luôn có logo thương hiệu)
if (!$providerMap) {
    $providerMap = provider_map();
}
?>
<div class="game-grid" id="gameGrid">
    <?php foreach ($gameList as $g): ?>
        <?php $p = $providerMap[$g['provider_id']] ?? null; ?>
        <a href="<?= url('/games/' . $g['slug']) ?>" class="game-card text-decoration-none"
           data-game-id="<?= (int)$g['id'] ?>">

            <?php if (!empty($g['is_hot'])): ?>
                <span class="icon-badge badge-hot">HOT</span>
            <?php endif; ?>
            <?php if (!empty($g['is_new'])): ?>
                <span class="icon-badge badge-new">MỚI</span>
            <?php endif; ?>
            <?php if (!empty($g['is_featured'])): ?>
                <span class="icon-badge badge-featured">NỔI BẬT</span>
            <?php endif; ?>

            <div class="game-thumb">
                <?php if (!empty($g['icon'])): ?>
                    <img src="<?= e(media_url((string)$g['icon'])) ?>"
                         alt="<?= e($g['name']) ?>"
                         loading="lazy" decoding="async"
                         width="300" height="300"
                         onerror="this.onerror=null;this.src='<?= asset('img/placeholder.svg') ?>';">
                <?php else: ?>
                    <img src="<?= asset('img/placeholder.svg') ?>" alt="<?= e($g['name']) ?>" loading="lazy">
                <?php endif; ?>
                <div class="game-overlay"></div>

                <?php /* Badge logo nhà phát hành — chỉ hiện khi hover */ ?>
                <?php if ($p): ?>
                    <span class="game-provider-badge">
                        <?php $logo = provider_logo_url($p); ?>
                        <?php if ($logo !== ''): ?>
                            <img src="<?= e($logo) ?>" alt="" onerror="this.remove()">
                        <?php endif; ?>
                        <span><?= e((string)$p['name']) ?></span>
                    </span>
                <?php endif; ?>

                <?php /* Chế độ chơi: đồng bộ ví (vàng) hoặc demo (xám) */ ?>
                <?php
                $launchState = (string)($g['launch_state'] ?? 'ready');
                $playMode    = (string)($g['play_mode'] ?? 'demo');
                ?>
                <?php if ($launchState !== 'ready'): ?>
                    <span class="game-mode-badge demo" title="Sản chưa công bố API đồng bộ ví — chỉ chơi thử">
                        <i class="fas fa-flask"></i> Demo
                    </span>
                <?php elseif ($playMode === 'real'): ?>
                    <span class="game-mode-badge real" title="Đồng bộ ví 2 chiều — nạp tiền hiển thị ngay trong game">
                        <i class="fas fa-link"></i> Đồng bộ ví
                    </span>
                <?php endif; ?>
            </div>

            <div class="game-name" title="<?= e($g['name']) ?>">
                <?= e($g['name']) ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$gameList): ?>
    <div class="empty-state">
        <i class="fas fa-ghost fs-1 d-block mb-3"></i>
        <p class="mb-3">Không tìm thấy game nào phù hợp.</p>
        <a href="<?= url('/games') ?>" class="btn btn-outline-gold btn-sm">
            <i class="fas fa-rotate-left me-1"></i>Xem tất cả game
        </a>
    </div>
<?php endif; ?>
