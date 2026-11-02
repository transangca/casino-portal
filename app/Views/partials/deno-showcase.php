<?php
/**
 * Section trưng bày Deno Casino (CQ9 Live).
 *
 * Dữ liệu lấy từ lobby chính thức (đồng bộ qua WebSocket):
 *  - banner  : ảnh quảng cáo trong lobby
 *  - marquee : thông báo cuộn của nhà phát hành
 *  - bàn     : danh sách game (bảng games, provider = deno-casino)
 *
 * @var array  $denoBanners
 * @var array  $denoMarquee
 * @var array  $denoTables
 */
$denoBanners = $denoBanners ?? [];
$denoMarquee = $denoMarquee ?? [];
$denoTables  = $denoTables  ?? [];

// Gom bàn theo nhóm để hiển thị gọn
$denoGroups = [];
foreach ($denoTables as $t) {
    $denoGroups[$t['category']][] = $t;
}
?>

<?php if ($denoGroups || $denoBanners): ?>
<section class="deno-showcase" id="denoShowcase">
    <div class="container">
        <div class="deno-head">
            <span class="deno-eyebrow">
                <i class="fas fa-crown"></i> Casino trực tiếp · CQ9 Live
            </span>
            <h2>Deno Casino — Baccarat, Dragon Tiger, Sicbo &amp; Marble</h2>
            <p>Chơi thử trực tiếp ngay trên website, không cần nạp tiền. Toàn bộ bàn chơi được đồng bộ tự động từ sảnh chính thức.</p>
        </div>

        <!-- Banner từ lobby -->
        <?php if ($denoBanners): ?>
            <div class="deno-banners">
                <?php foreach (array_slice($denoBanners, 0, 6) as $b): ?>
                    <?php $img = (string)($b['adsUrl'] ?? ''); ?>
                    <?php if ($img === '') { continue; } ?>
                    <div class="deno-banner">
                        <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async" onerror="this.parentNode.remove()">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Nhóm bàn chơi -->
        <div class="deno-groups">
            <?php foreach ($denoGroups as $cat => $items): ?>
                <?php
                $meta = [
                    'baccarat' => ['Baccarat', 'fas fa-diamond', 'deno-baccarat'],
                    'dt'       => ['Dragon Tiger', 'fas fa-dragon', 'deno-dt'],
                    'sicbo'    => ['Sicbo', 'fas fa-cubes', 'deno-sicbo'],
                    'marble'   => ['Marble / PK10', 'fas fa-circle-dot', 'deno-marble'],
                ];
                [$title, $icon, $img] = $meta[$cat] ?? [ucfirst((string)$cat), 'fas fa-gamepad', 'deno-casino'];
                ?>
                <div class="deno-group">
                    <div class="deno-group-head">
                        <img class="deno-group-icon"
                             src="<?= url('/assets/img/providers/' . $img . '.svg') ?>"
                             alt="" loading="lazy" decoding="async" onerror="this.remove()">
                        <div>
                            <h3><i class="<?= $icon ?> me-1"></i><?= e($title) ?></h3>
                            <span><?= count($items) ?> bàn đang mở</span>
                        </div>
                    </div>
                    <div class="deno-tables">
                        <?php foreach ($items as $t): ?>
                            <a class="deno-table" href="<?= url('/games/' . (string)$t['slug']) ?>">
                                <span class="deno-table-id"><?= e((string)$t['table_id']) ?></span>
                                <span class="deno-table-name"><?= e((string)$t['name']) ?></span>
                                <i class="fas fa-play-circle"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Thông báo cuộn từ nhà phát hành -->
        <?php if ($denoMarquee): ?>
            <div class="deno-marquee" aria-label="Thông báo sảnh">
                <div class="deno-marquee-track">
                    <?php for ($i = 0; $i < 2; $i++): ?>
                        <?php foreach ($denoMarquee as $m): ?>
                            <?php $txt = (string)($m['content'] ?? ''); ?>
                            <?php if ($txt === '') { continue; } ?>
                            <span class="deno-marquee-item">
                                <i class="fas fa-bullhorn"></i><?= e($txt) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
            <script>
            (function () {
                var wrap = document.querySelector('.deno-marquee');
                var track = wrap && wrap.querySelector('.deno-marquee-track');
                if (!track || window.__denoMarqueeInit) return;
                window.__denoMarqueeInit = true;

                // Vận tốc cố định (px/giây) -> thời gian chạy luôn bằng nhau dù nội dung dài/ngắn
                var SPEED = 22;

                function apply() {
                    // Chiều rộng nửa vòng lặp (1 bản sao) để translateX(-50%) là chu kỳ
                    var one = track.scrollWidth / 2;
                    if (!one) return;
                    var dur = Math.max(30, one / SPEED);
                    track.style.animationDuration = dur.toFixed(2) + 's';
                }

                apply();
                window.addEventListener('resize', apply);
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(apply);
                }
                setTimeout(apply, 600);
            })();
            </script>
        <?php endif; ?>

        <div class="deno-cta">
            <a href="<?= url('/lobbies/deno-casino') ?>" class="btn btn-play btn-lg">
                <i class="fas fa-dice me-2"></i>VÀO SẢNH DENO
            </a>
            <a href="<?= url('/lobbies/deno-casino') ?>" class="btn btn-soft btn-lg px-4" target="_blank" rel="noopener">
                <i class="fas fa-arrow-up-right-from-square me-2"></i>MỞ LOBBY GỐC
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
