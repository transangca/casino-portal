<?php
/**
 * PG SOFT SHOWCASE — dựng 1:1 theo pgsoft.com
 *
 * Cấu trúc DOM bám sát selector trong pgsoft-official.css:
 *   .pg-slogan    → hero chữ + thanh "Vuốt Lên" (Lottie mũi tên)
 *   .pg-data      → quả địa cầu (earth video) + 2 counter + 8 thẻ thống kê
 *   .pg-features  → 5 icon + stepper, animation điện thoại/mac/nhân vật (step1..step5)
 *   .pg-licensing → nhân vật + 4 tờ giấy bay + 2 cột Licensing / Certification
 *   .pg-backoffice→ 4 chỉ số + đồ thị Lottie
 *   .pg-about     → lưới 6 ô (hover bung ảnh + nội dung)
 *   .pg-partner   → lưới logo đối tác (grayscale → màu)
 *   .pg-vision    → sóng line video
 *   .pg-gallery   → game PG SOFT cuộn ngang
 *
 * @var array $pgSlogan,$pgData,$pgFeatures,$pgLicensing,$pgAbout,$pgBackOffice,$pgPartners,$pgVision
 * @var array $pgGallery
 * @var int   $pgGames,$pgProviders
 */
$pgSlogan     = $pgSlogan     ?? [];
$pgData       = $pgData       ?? [];
$pgFeatures   = $pgFeatures   ?? [];
$pgLicensing  = $pgLicensing  ?? [];
$pgAbout      = $pgAbout      ?? [];
$pgBackOffice = $pgBackOffice ?? [];
$pgPartners   = $pgPartners   ?? [];
$pgVision     = $pgVision     ?? [];
$pgGallery    = $pgGallery    ?? [];
$pgGames      = (int)($pgGames ?? 0);
$pgProviders  = (int)($pgProviders ?? 0);

$pgImg = static fn(string $f): string => url('/assets/img/pgsoft/' . $f);

// Mã chính thức: Social / Tournament / FreeGame / SmartBot / Lobby
$pgFeatureCodes = ['Social', 'Tournament', 'FreeGame', 'SmartBot', 'Lobby'];
?>

<?php if ($pgData || $pgFeatures || $pgAbout): ?>
<section class="pg-showcase" id="pgShowcase">

    <!-- ============================================================
         1. SLOGAN — hero + thanh "Vuốt Lên" (Lottie mũi tên)
         ============================================================ -->
    <?php if ($pgSlogan): ?>
    <div class="pg-slogan" id="pg_slogan">
        <div class="slogancover" id="pgSloganCover">
            <div class="slogantitle" id="pgSloganTitle"></div>
            <div class="sloganbackground" id="pgSloganBg">
                <div class="sloganbg"></div>
                <div class="slogantext"><?= e((string)($pgSlogan['swipeUp'] ?? 'Vuốt Lên')) ?></div>
                <div class="slogananime" id="pgSloganAnime"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         2. DATA — quả địa cầu + counter + 8 thẻ thống kê
         ============================================================ -->
    <?php if ($pgData): ?>
    <div class="pg-data" id="pg_data">
        <div class="mask"></div>
        <div class="bg"></div>

        <div class="data-cover">
            <!-- Counter + mô tả -->
            <div class="data-content">
                <div class="counter-cover">
                    <?php
                    $pgCounters = [
                        ['text' => 'TRÒ CHƠI',      'amount' => (int)($pgData['counterGames'] ?? 0)],
                        ['text' => 'NGÔN NGỮ',      'amount' => (int)($pgData['counterLanguages'] ?? 0)],
                    ];
                    foreach ($pgCounters as $ci => $c):
                        $digits = str_split((string)$c['amount']);
                    ?>
                        <div class="counter-box" data-pg-counter>
                            <div class="box number">
                                <?php foreach ($digits as $di => $d): ?>
                                    <div data-pg-digit="<?= (int)$d ?>">
                                        <span style="bottom:-<?= 80 * (int)$d ?>px;transition-duration:2.5s;transition-delay:<?= 0.3 * $di ?>s">
                                            <?php for ($k = 0; $k < 40; $k++): ?>
                                                <i><?= (9 - ($k % 10)) % 10 ?></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text"><?= e((string)$c['text']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="description-section">
                    <div class="desccover">
                        <div class="d-desc">
                            <div><?= $pgData['desc'] ?? '' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Globe + lưới 8 thẻ -->
            <div class="earth-cover">
                <div class="data-earth" id="pgEarth">
                    <div class="earth">
                        <video id="earth_video" autoplay muted loop playsinline preload="auto">
                            <source src="<?= $pgImg((string)($pgData['earthVideo'] ?? 'earth_animation.ef40696.mp4')) ?>" type="video/mp4">
                        </video>
                        <div class="cen"></div>
                    </div>
                </div>

                <div class="data-detail">
                    <div class="detail-list">

                        <!-- ===== Cột 1 ===== -->
                        <div class="list-col">
                            <!-- Khách hàng -->
                            <div class="globebox customer">
                                <div class="detail-box data-customer">
                                    <div class="dbox-cover pc">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['countries'] ?? 0)) ?><i style="background:#19BEE6"></i></b>
                                            <span>KHÁCH HÀNG TẠI<br><?= number_format((int)($pgData['countries'] ?? 0)) ?> QUỐC GIA</span>
                                        </div>
                                        <div class="pie-container">
                                            <div class="pie" id="pgPie">
                                                <div class="pie-bg"></div>
                                                <div class="piecover">
                                                    <img src="<?= $pgImg((string)($pgData['pieImage'] ?? 'pie.b01dc8b.jpg')) ?>" alt="" loading="lazy">
                                                </div>
                                            </div>
                                            <div class="pie-desc">
                                                <?php foreach (($pgData['areas'] ?? []) as $ar): ?>
                                                    <div class="descbox">
                                                        <div>
                                                            <i style="background:<?= e((string)$ar['color']) ?>"></i>
                                                            <span><?= e((string)$ar['label']) ?></span>
                                                            <b><?= e((string)$ar['pct']) ?></b>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nhà điều hành -->
                            <div class="globebox user">
                                <div class="detail-box data-user">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['operators'] ?? 0)) ?><i style="background:#E46C2C"></i></b>
                                            <span>NHÀ ĐIỀU HÀNH<br>TOÀN CẦU</span>
                                        </div>
                                        <div class="testimonial" id="pgTestimonial">
                                            <div class="dot">
                                                <?php foreach (($pgData['operatorTestimonials'] ?? []) as $ti => $t): ?>
                                                    <div class="<?= $ti === 0 ? 'active' : '' ?>" data-pg-dot="<?= $ti ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <i class="top"><img src="<?= $pgImg('quotation_mask1.5e74001.svg') ?>" alt=""></i>
                                            <i class="bottom"><img src="<?= $pgImg('quotation_mask2.21e5735.svg') ?>" alt=""></i>
                                            <div class="testimonial-cover">
                                                <div class="overflow" id="pgTestimonialTrack">
                                                    <?php foreach (($pgData['operatorTestimonials'] ?? []) as $t): ?>
                                                        <div>
                                                            <img src="<?= $pgImg((string)$t['logo']) ?>" alt="" loading="lazy">
                                                            <span><?= e((string)$t['desc']) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="clear-row"></div>
                        </div>

                        <!-- ===== Cột 2 ===== -->
                        <div class="list-col">
                            <!-- 24/7 -->
                            <div class="globebox toshour">
                                <div class="detail-box data-toshour">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-desc">
                                            <div><span class="number">24</span><span class="hour">GIỜ</span></div>
                                            <b><?= e((string)($pgData['hoursTitle'] ?? 'TRUY CẬP NHÀ ĐIỀU HÀNH')) ?></b>
                                            <p><?= e((string)($pgData['hoursDesc'] ?? '')) ?></p>
                                        </div>
                                        <div class="spit-content pc">
                                            <video autoplay muted loop playsinline preload="none">
                                                <source src="<?= $pgImg((string)($pgData['rocketVideo'] ?? 'rocket_animation.bb0f5f5.mp4')) ?>" type="video/mp4">
                                            </video>
                                        </div>
                                        <div class="spit-content mb">
                                            <img src="<?= $pgImg((string)($pgData['rocketImage'] ?? 'rocket.f5c07ee.jpg')) ?>" alt="" loading="lazy">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Poker -->
                            <div class="globebox poker">
                                <div class="detail-box data-poker">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['poker'] ?? 0)) ?><i style="background:#FF2D55"></i></b>
                                            <span>PG SOFT<br>TRÒ CHƠI BÀN</span>
                                        </div>
                                        <div class="poker-list pc">
                                            <?php foreach (($pgData['pokerIcons'] ?? []) as $pi => $p): ?>
                                                <div id="game-<?= $pi + 1 ?>">
                                                    <img src="<?= $pgImg((string)$p) ?>" alt="" loading="lazy">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="poker-list mb">
                                            <div class="overflow" id="pgPokerOverflow">
                                                <?php foreach (($pgData['pokerIcons'] ?? []) as $p): ?>
                                                    <div><img src="<?= $pgImg((string)$p) ?>" alt="" loading="lazy"></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thiết bị -->
                            <div class="globebox devices">
                                <div class="detail-box data-devices">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['devices'] ?? 0)) ?><i style="background:#14E691"></i></b>
                                            <span>HỖ TRỢ TẤT CẢ THIẾT BỊ</span>
                                        </div>
                                        <div class="device-list pc">
                                            <div class="overflow" id="pgDeviceOverflow">
                                                <?php foreach (($pgData['deviceIcons'] ?? []) as $di => $d): ?>
                                                    <div>
                                                        <img style="opacity:<?= $di === 0 ? '1' : '.18' ?>"
                                                             src="<?= $pgImg((string)$d) ?>" alt="" loading="lazy">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="device-list mb">
                                            <div class="overflow">
                                                <?php foreach (($pgData['deviceIcons'] ?? []) as $di => $d): ?>
                                                    <div>
                                                        <img style="opacity:<?= $di === 0 ? '1' : '.3' ?>"
                                                             src="<?= $pgImg((string)$d) ?>" alt="" loading="lazy">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="clear-row"></div>
                        </div>

                        <!-- ===== Cột 3 ===== -->
                        <div class="list-col">
                            <!-- Slot -->
                            <div class="globebox slot">
                                <div class="detail-box data-slot">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['slots'] ?? 0)) ?><i style="background:#FF4B00"></i></b>
                                            <span>PG SOFT<br>TRÒ QUAY SỐ</span>
                                        </div>
                                        <div class="slot-list" id="pgSlotList">
                                            <?php foreach (($pgData['slotIcons'] ?? []) as $i => $s): ?>
                                                <div>
                                                    <img class="first"  src="<?= $pgImg((string)$pgData['slotIcons'][($i + 1) % 16]) ?>" alt="" loading="lazy">
                                                    <img class="second" src="<?= $pgImg((string)$pgData['slotIcons'][($i + 2) % 16]) ?>" alt="" loading="lazy">
                                                    <img class="third"  src="<?= $pgImg((string)$pgData['slotIcons'][($i + 3) % 16]) ?>" alt="" loading="lazy">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tiền tệ -->
                            <div class="globebox currency">
                                <div class="detail-box data-currency">
                                    <div class="dbox-cover">
                                        <div class="cen"></div>
                                        <div class="float-title">
                                            <b class="number"><?= number_format((int)($pgData['currencies'] ?? 0)) ?><i style="background:#FFB919"></i></b>
                                            <span>HIỆN ĐƯỢC HỖ TRỢ</span>
                                        </div>
                                        <div class="currency-list pc">
                                            <div class="overflow" id="pgCurrencyOverflow">
                                                <?php foreach (($pgData['coins'] ?? []) as $ci => $c): ?>
                                                    <div>
                                                        <img src="<?= $pgImg((string)$c) ?>" alt="" loading="lazy">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="currency-list mb">
                                            <div class="overflow" id="pgCurrencyOverflowMb">
                                                <?php foreach (($pgData['coins'] ?? []) as $c): ?>
                                                    <div><img src="<?= $pgImg((string)$c) ?>" alt="" loading="lazy"></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ngôn ngữ -->
                            <div class="globebox languages">
                                <div class="detail-box data-languages">
                                    <div class="dbox-cover pc">
                                        <div class="cen"></div>
                                        <div class="spit-title">
                                            <b class="number"><?= number_format((int)($pgData['languages'] ?? 0)) ?></b>
                                            <span>NGÔN NGỮ<br>ĐƯỢC HỖ TRỢ</span>
                                        </div>
                                        <div class="spit-content">
                                            <div class="lang-left" id="pgLangLeft">
                                                <div class="overflow">
                                                    <?php foreach (($pgData['langLeft'] ?? []) as $l): ?>
                                                        <div><img src="<?= $pgImg((string)$l) ?>" alt="" loading="lazy"></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="lang-right" id="pgLangRight">
                                                <div class="overflow">
                                                    <?php foreach (($pgData['langRight'] ?? []) as $l): ?>
                                                        <div><img src="<?= $pgImg((string)$l) ?>" alt="" loading="lazy"></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dbox-cover mb">
                                        <div class="cen"></div>
                                        <div class="spit-title">
                                            <b class="number"><?= number_format((int)($pgData['languages'] ?? 0)) ?></b>
                                            <span>NGÔN NGỮ<br>ĐƯỢC HỖ TRỢ</span>
                                        </div>
                                        <div class="spit-content">
                                            <div class="lang-left">
                                                <div class="overflow">
                                                    <?php foreach (($pgData['langLeft'] ?? []) as $l): ?>
                                                        <div><img src="<?= $pgImg((string)$l) ?>" alt="" loading="lazy"></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="lang-right">
                                                <div class="overflow">
                                                    <?php foreach (($pgData['langRight'] ?? []) as $l): ?>
                                                        <div><img src="<?= $pgImg((string)$l) ?>" alt="" loading="lazy"></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="clear-row"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         3. FEATURES — icon + stepper + animation điện thoại
         ============================================================ -->
    <?php if ($pgFeatures): ?>
    <div class="pg-features" id="pg_features">
        <div class="features-content">
            <div class="description-section">
                <div class="desccover">
                    <div class="d-title"><?= e((string)($pgFeaturesTitle ?? 'TÍNH NĂNG CỦA CHÚNG TÔI')) ?></div>
                    <div class="d-desc">
                        <div><?= $pgFeaturesDesc ?? '' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="features-scrollarea" id="pgFeaturesScroll">
            <div class="scrollcover" id="scrollContainer">
                <div class="sticky-container" id="pgSticky">

                    <!-- Icon xã hội nổi bật -->
                    <div class="socialicon">
                        <div class="soicon">
                            <img src="<?= $pgImg('screen5_social@2x.28914a4.png') ?>" alt="">
                        </div>
                    </div>

                    <!-- 5 icon tab -->
                    <div class="icon-list">
                        <?php foreach ($pgFeatures as $i => $f): ?>
                            <div class="iconbox icon-<?= e($pgFeatureCodes[$i] ?? ('F' . $i)) ?>">
                                <img src="<?= $pgImg((string)$f['screen']) ?>" alt="" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 5 tiêu đề tab -->
                    <div class="text-list">
                        <?php foreach ($pgFeatures as $i => $f): ?>
                            <div class="text-<?= e($pgFeatureCodes[$i] ?? ('F' . $i)) ?>">
                                <div><?= e((string)$f['title']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sân khấu: trái = animation, phải = stepper -->
                    <div class="container-content" id="pgFeatureStage">
                        <div class="video">
                            <div class="feature-animation step1" id="pgFeatureAnim">
                                <!-- Điện thoại -->
                                <div class="phone">
                                    <div class="phone-bg"><img src="<?= $pgImg('phone.c833404.svg') ?>" alt=""></div>
                                    <div class="phone-social">
                                        <div><img src="<?= $pgImg('social_pic_en.5225ca3.png') ?>" alt=""></div>
                                    </div>
                                    <div class="phone-tournament">
                                        <div class="tournament-overlay">
                                            <div class="tournament-scroll">
                                                <div class="tournament-title">
                                                    <img src="<?= $pgImg('tournament_title_en.a8b3ddb.png') ?>" alt="">
                                                </div>
                                                <div class="tournament-bg">
                                                    <div class="tournament-card">
                                                        <img src="<?= $pgImg('tournament_card_en.3b77f24.png') ?>" alt="">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="phone-freegame">
                                        <div class="freegame-overlay">
                                            <div class="freegame-free">
                                                <div class="free1"><img src="<?= $pgImg('free_normal_en@3x.b5e4775.png') ?>" alt=""></div>
                                                <div class="free2"><img src="<?= $pgImg('free_hover_en@3x.c884ca5.png') ?>" alt=""></div>
                                            </div>
                                            <div class="freegame-gift">
                                                <div class="gift1"><img src="<?= $pgImg('bonus_normal_en@3x.c837bb2.png') ?>" alt=""></div>
                                                <div class="gift2"><img src="<?= $pgImg('bonus_hover_en@3x.14d2241.png') ?>" alt=""></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="phone-smartbot">
                                        <div class="smartbot-overlay">
                                            <div></div>
                                            <video autoplay muted loop playsinline preload="none">
                                                <source src="<?= $pgImg('smartbot_videoen.ffeb11e.mp4') ?>" type="video/mp4">
                                            </video>
                                        </div>
                                    </div>
                                </div>

                                <!-- Điện thoại nhỏ (Game Lobby) -->
                                <div class="tiny-phone">
                                    <div class="phone-bg"><img src="<?= $pgImg('phone.c833404.svg') ?>" alt=""></div>
                                    <div class="phone-gamelobby">
                                        <div class="gamelobby-overlay">
                                            <div></div>
                                            <video autoplay muted loop playsinline preload="none">
                                                <source src="<?= $pgImg('lobby_video.104c8f9.mp4') ?>" type="video/mp4">
                                            </video>
                                        </div>
                                    </div>
                                </div>

                                <!-- Máy tính -->
                                <div class="mac">
                                    <div class="bg"><img src="<?= $pgImg('mac.cb91d0c.svg') ?>" alt=""></div>
                                    <div class="mainimg">
                                        <div><img src="<?= $pgImg('lobby_video.da931d2.png') ?>" alt=""></div>
                                    </div>
                                </div>

                                <!-- 5 nhân vật -->
                                <div class="social-char"><img src="<?= $pgImg('img_social@2x.37c4b9f.png') ?>" alt=""></div>
                                <div class="tournament-char"><img src="<?= $pgImg('img_tournament@2x.b44f660.png') ?>" alt=""></div>
                                <div class="freegame-char"><img src="<?= $pgImg('img_freegame@2x.3248ec9.png') ?>" alt=""></div>
                                <div class="smartbot-char"><img src="<?= $pgImg('img_smartbot@2x.bc5c9a4.png') ?>" alt=""></div>
                                <div class="gamelobby-char"><img src="<?= $pgImg('img_lobby@2x.18ddc34.png') ?>" alt=""></div>
                            </div>
                        </div>

                        <!-- Stepper mô tả -->
                        <div class="step">
                            <div class="feature-stepper" id="pgFeatureStepper">
                                <?php foreach ($pgFeatures as $i => $f): ?>
                                    <div class="stepbox<?= $i === 0 ? ' active' : '' ?>" data-pg-step="<?= $i + 1 ?>">
                                        <div class="img">
                                            <img src="<?= $pgImg((string)$f['screen']) ?>" alt="" loading="lazy">
                                        </div>
                                        <h1 data-pg-goto="<?= $i + 1 ?>"><i></i><?= e((string)$f['title']) ?></h1>
                                        <div class="desc">
                                            <b></b>
                                            <div><?= e((string)$f['text']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         4. LICENSING — nhân vật giấy tờ bay + 2 cột
         ============================================================ -->
    <?php if ($pgLicensing): ?>
    <div class="pg-licensing" id="pg_licensing">
        <div class="pglicensing-cover">
            <div class="char" id="pgLicChar">
                <div class="paper1"><img src="<?= $pgImg('pic_paper1@2x.ea5e067.png') ?>" alt=""></div>
                <div class="paper2"><img src="<?= $pgImg('pic_paper2@2x.13030f2.png') ?>" alt=""></div>
                <div class="paper3"><img src="<?= $pgImg('pic_paper3@2x.8d8bfbc.png') ?>" alt=""></div>
                <div class="paper4"><img src="<?= $pgImg('pic_paper4@2x.105958a.png') ?>" alt=""></div>
                <div class="main"><img src="<?= $pgImg('pic_man@2x.6a41344.png') ?>" alt=""></div>
            </div>

            <div class="licensing-cover">
                <?php foreach ($pgLicensing as $li => $l): ?>
                    <div class="<?= $li === 0 ? 'license' : 'certificate' ?>">
                        <div class="license-content">
                            <h1><?= e((string)$l['title']) ?></h1>
                            <?php foreach ($l['items'] as $it): ?>
                                <div>
                                    <img src="<?= $pgImg((string)$it['logo']) ?>" alt="" loading="lazy">
                                    <span><?= e((string)$it['text']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         5. BACK OFFICE — 4 chỉ số + đồ thị Lottie
         ============================================================ -->
    <?php if ($pgBackOffice): ?>
    <div class="pg-backoffice" id="pg_backoffice">
        <div class="backoffice-cover">
            <div class="backoffice-content">
                <div class="description-section">
                    <div class="desccover">
                        <div class="d-title"><?= e((string)($pgBackOffice['title'] ?? 'BACK OFFICE')) ?></div>
                        <div class="d-desc">
                            <div><?= $pgBackOffice['desc'] ?? '' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-list">
                <?php foreach (($pgBackOffice['items'] ?? []) as $it): ?>
                    <div class="list-box item-<?= e((string)$it['code']) ?>">
                        <b><?= e((string)$it['num']) ?></b>
                        <h1><?= e((string)$it['title']) ?></h1>
                        <span><?= $it['desc'] ?? '' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg">
            <div class="statistic" id="pgBoStatistic"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         6. ABOUT — lưới 6 ô, hover bung ảnh + nội dung
         ============================================================ -->
    <?php if ($pgAbout): ?>
    <div class="pg-about" id="pg_about">
        <div class="about-cover">
            <div class="about-content">
                <h1><?= e((string)($pgAboutTitle ?? 'VỀ PG SOFT®')) ?></h1>
                <p><?= $pgAboutDesc ?? '' ?></p>
            </div>

            <div class="about-list" id="pgAboutList">
                <?php foreach ($pgAbout as $ai => $a): ?>
                    <div class="list-box box-<?= $ai + 1 ?>">
                        <div class="box-cover">
                            <h1>
                                <span class="box-title"><?= e((string)$a['line1']) ?></span>
                                <b style="color:<?= e((string)$a['color']) ?>"><?= e((string)$a['line2']) ?></b>
                            </h1>
                            <img src="<?= $pgImg((string)$a['icon']) ?>" alt="" loading="lazy">
                            <p><?= $a['text'] ?? '' ?></p>
                        </div>
                        <div class="box-hover">
                            <div class="video" style="background-image:url('<?= $pgImg((string)$a['bg']) ?>')"></div>
                            <div class="content">
                                <h1><?= e((string)$a['line2']) ?></h1>
                                <div><p><?= $a['text'] ?? '' ?></p></div>
                            </div>
                            <span style="background:<?= e((string)$a['color']) ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         7. PARTNER — lưới logo đối tác
         ============================================================ -->
    <?php if ($pgPartners): ?>
    <div class="pg-partner" id="pg_partner">
        <div class="partner-cover">
            <div class="partner-content">
                <div class="description-section">
                    <div class="desccover">
                        <div class="d-title"><?= e((string)($pgPartners['title'] ?? 'ĐỐI TÁC CỦA CHÚNG TÔI')) ?></div>
                        <div class="d-desc">
                            <div><?= $pgPartners['desc'] ?? '' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="partner-logo" id="pgPartnerLogo">
                <?php foreach (($pgPartners['logos'] ?? []) as $pi => $logo): ?>
                    <div id="partner<?= $pi + 1 ?>">
                        <img src="<?= $pgImg((string)$logo) ?>" alt="" loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         8. VISION — sóng line video
         ============================================================ -->
    <?php if ($pgVision): ?>
    <div class="pg-vision" id="pg_vision">
        <div class="vision-cover">
            <div class="vision-content">
                <div class="description-section">
                    <div class="desccover">
                        <div class="d-title"><?= e((string)($pgVision['title'] ?? 'TẦM NHÌN CỦA CHÚNG TÔI')) ?></div>
                        <div class="d-desc">
                            <div><?= $pgVision['desc'] ?? '' ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="vision-wave" id="pgVisionWave">
                <div class="video-cover">
                    <video class="vid" autoplay muted loop playsinline preload="none">
                        <source src="<?= $pgImg((string)($pgVision['video'] ?? 'line-video.2db4fa0.mp4')) ?>" type="video/mp4">
                    </video>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         9. GALLERY GAME PG SOFT (cuộn ngang)
         ============================================================ -->
    <?php if ($pgGallery): ?>
    <div class="pg-gallery">
        <div class="container">
            <div class="section-head">
                <h2><i class="fas fa-gamepad"></i>Game PG SOFT</h2>
                <span class="form-hint">Cuộn ngang để xem thêm · bấm để chơi thử</span>
                <a href="<?= url('/games?provider=pgsoft') ?>" class="btn btn-outline-gold btn-sm">Xem tất cả</a>
            </div>

            <div class="pg-gallery-scroll">
                <button type="button" class="pg-gal-nav pg-gal-prev" aria-label="Trước">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="pg-gallery-track" data-pg-gallery>
                    <?php foreach ($pgGallery as $g): ?>
                        <a class="pg-gal-card" href="<?= url('/games/' . (string)$g['slug']) ?>">
                            <div class="pg-gal-thumb">
                                <img src="<?= e((string)$g['icon']) ?>"
                                     alt="<?= e((string)$g['name']) ?>"
                                     loading="lazy" decoding="async"
                                     onerror="this.src='<?= $pgImg('PGlogo.jpg') ?>'">
                                <span class="pg-gal-play"><i class="fas fa-play"></i></span>
                            </div>
                            <span class="pg-gal-name"><?= e((string)$g['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="pg-gal-nav pg-gal-next" aria-label="Kế tiếp">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="pg-stats">
                <div class="pg-stat">
                    <b><?= number_format($pgGames) ?></b>
                    <span>Game</span>
                </div>
                <div class="pg-stat">
                    <b><?= number_format($pgProviders) ?></b>
                    <span>Nhà phát hành</span>
                </div>
                <div class="pg-stat">
                    <b>2 chiều</b>
                    <span>Đồng bộ ví</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</section>

<script>
window.addEventListener('load', function () { (function () {
    'use strict';

    var $  = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var isMobile = function () { return window.innerWidth <= 991; };
    var reduce   = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ============================================================
       1. SLOGAN — chữ responsive + Lottie mũi tên
       ============================================================ */
    var slogan = $('#pgSloganTitle');
    if (slogan) {
        var texts = {
            large:  <?= json_encode($pgSlogan['large'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
            medium: <?= json_encode($pgSlogan['medium'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
            small:  <?= json_encode($pgSlogan['small'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
            xs:     <?= json_encode($pgSlogan['xs'] ?? '', JSON_UNESCAPED_UNICODE) ?>
        };
        var pick = function () {
            var w = window.innerWidth || document.documentElement.clientWidth;
            slogan.innerHTML = (w >= 992 ? texts.large
                      : w >= 768 ? texts.medium
                      : w >= 576 ? texts.small
                      : texts.xs) || '';
        };
        pick();
        window.addEventListener('resize', pick);

        // Lottie mũi tên "Vuốt Lên"
        var initSloganLottie = function () {
            if (!window.lottie || !$('#pgSloganAnime')) { return; }
            fetch(<?= json_encode($pgImg('slogan_lottie.json')) ?>, { cache: 'force-cache' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    window.lottie.loadAnimation({
                        container: $('#pgSloganAnime'),
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        animationData: data
                    });
                })
                .catch(function () {});
        };
        initSloganLottie();

        // Hero trượt lên khi cuộn (giống pgsoft.com)
        var cover = $('#pgSloganCover');
        var onScroll = function () {
            if (!cover) { return; }
            var y = window.scrollY || document.documentElement.scrollTop;
            cover.classList.toggle('active', y > 100);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ============================================================
       2. DATA — hiệu ứng vào khung nhìn + các animation lặp
       ============================================================ */
    if (window.gsap && window.ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);

        // Đếm số khi vào khung nhìn
        $$('[data-pg-counter]').forEach(function (box) {
            var cols = $$('.box > div', box);
            cols.forEach(function (col) {
                var sp = $('span', col);
                if (sp) { sp.style.bottom = '0px'; }
            });
            ScrollTrigger.create({
                trigger: box,
                start: 'top 85%',
                once: true,
                onEnter: function () {
                    cols.forEach(function (col) {
                        var sp = $('span', col);
                        if (!sp) { return; }
                        var digit = parseInt(col.getAttribute('data-pg-digit') || '0', 10);
                        sp.style.bottom = (-80 * digit) + 'px';
                    });
                }
            });
        });

        // Các thẻ thống kê xoay quanh quả địa cầu
        $$('.pg-data .globebox').forEach(function (b, i) {
            gsap.fromTo(b,
                { opacity: 0, y: 60, scale: .82 },
                {
                    opacity: 1, y: 0, scale: 1,
                    duration: .8, ease: 'power1.out', delay: i * .06,
                    scrollTrigger: { trigger: '#pg_data', start: 'top 55%', once: true }
                });
        });

        gsap.fromTo('.pg-data .data-content',
            { opacity: 0, y: 80 },
            { opacity: 1, y: 0, duration: .8,
              scrollTrigger: { trigger: '#pg_data', start: 'top 70%', once: true } });
    }

    // Biểu đồ tròn quay
    var pie = $('#pgPie');
    if (pie && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (en) {
            if (en[0].isIntersecting) { pie.classList.add('active'); }
        }, { threshold: .4 }).observe(pie);
    }

    // Testimonial nhà điều hành — xoay vòng
    var track = $('#pgTestimonialTrack');
    if (track) {
        var dots  = $$('#pgTestimonial [data-pg-dot]');
        var slide = 0;
        var setSlide = function (i) {
            slide = i;
            track.style.left = (-180 * i) + 'px';
            dots.forEach(function (d, di) { d.classList.toggle('active', di === i); });
        };
        dots.forEach(function (d) {
            d.addEventListener('click', function () { setSlide(parseInt(d.getAttribute('data-pg-dot'), 10)); });
        });
        if (!reduce && dots.length > 1) {
            setInterval(function () { setSlide((slide + 1) % dots.length); }, 5000);
        }
    }

    // Slot animation lặp
    var slot = $('#pgSlotList');
    if (slot) {
        var runSlot = function () { slot.classList.add('active'); };
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) {
                if (en[0].isIntersecting) { runSlot(); }
            }, { threshold: .2 }).observe(slot);
        } else { runSlot(); }
    }

    // Tiền tệ trượt (mobile)
    var curMb = $('#pgCurrencyOverflowMb');
    if (curMb) {
        var ci = 0;
        setInterval(function () {
            ci = (ci + 1) % 6;
            curMb.classList.toggle('active', ci === 3);
        }, 3000);
    }

    // Poker trượt (mobile)
    var pokerMb = $('#pgPokerOverflow');
    if (pokerMb) {
        setTimeout(function () { pokerMb.classList.add('active'); }, 1200);
    }

    // Ngôn ngữ: cột trái lên, cột phải xuống
    var langL = $('#pgLangLeft');
    var langR = $('#pgLangRight');
    if (langL && langR && !reduce) {
        setInterval(function () {
            langL.classList.toggle('active');
            langR.classList.toggle('active');
        }, 3200);
    }

    /* ============================================================
       3. FEATURES — sticky scroll + đổi step
       ============================================================ */
    var animBox = $('#pgFeatureAnim');
    var stepper = $('#pgFeatureStepper');
    if (animBox && stepper) {
        var steps = $$('.stepbox', stepper);
        var current = 1;

        var paint = function (n) {
            current = n;
            animBox.className = 'feature-animation step' + n;
            steps.forEach(function (s, i) { s.classList.toggle('active', i === n - 1); });
        };

        steps.forEach(function (s, i) {
            var h1 = $('h1', s);
            if (h1) {
                h1.addEventListener('click', function () {
                    paint(i + 1);
                    if (!isMobile()) {
                        var area = $('#pgFeaturesScroll');
                        if (area) {
                            var top = area.offsetTop + (area.offsetHeight - window.innerHeight) * (i / 5);
                            window.scrollTo({ top: top, behavior: 'smooth' });
                        }
                    }
                });
            }
        });
        paint(1);

        if (window.gsap && window.ScrollTrigger && !isMobile()) {
            ScrollTrigger.create({
                trigger: '#pgFeaturesScroll',
                start: 'top top',
                end: 'bottom bottom',
                onUpdate: function (self) {
                    var n = Math.min(5, Math.floor(self.progress * 5.4) + 1);
                    if (n !== current) { paint(n); }
                }
            });
        } else if (isMobile()) {
            // Mobile: bấm tiêu đề để đổi step
            $$('#pgFeatureStepper .stepbox').forEach(function (s, i) {
                s.addEventListener('click', function () { paint(i + 1); });
            });
        }
    }

    /* ============================================================
       4. LICENSING — giấy tờ bay khi vào khung nhìn
       ============================================================ */
    var licCover = $('.pg-licensing .pglicensing-cover');
    if (licCover) {
        var licIn = function () { licCover.classList.add('active'); };
        if (window.gsap && window.ScrollTrigger) {
            ScrollTrigger.create({
                trigger: '#pg_licensing',
                start: 'top 70%',
                once: true,
                onEnter: licIn
            });
        } else if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) {
                if (en[0].isIntersecting) { licIn(); }
            }, { threshold: .2 }).observe(licCover);
        } else { licIn(); }
    }

    /* ============================================================
       5. BACK OFFICE — Lottie đồ thị
       ============================================================ */
    var boBox = $('#pgBoStatistic');
    if (boBox && window.lottie) {
        var boAnim = null;
        var initBo = function () {
            if (boAnim) { return; }
            fetch(<?= json_encode($pgImg((string)($pgBackOffice['lottie'] ?? 'backoffice_lottie.json'))) ?>, { cache: 'force-cache' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    boAnim = window.lottie.loadAnimation({
                        container: boBox, renderer: 'svg', loop: true, autoplay: true, animationData: data
                    });
                })
                .catch(function () {});
        };
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) {
                if (en[0].isIntersecting) { initBo(); }
            }, { threshold: .05 }).observe(boBox);
        } else { initBo(); }

        if (window.gsap && window.ScrollTrigger) {
            gsap.fromTo('.pg-backoffice .backoffice-content', { opacity: 0, y: 80 }, {
                opacity: 1, y: 0, duration: .7,
                scrollTrigger: { trigger: '#pg_backoffice', start: 'top 70%', once: true }
            });
            $$('.pg-backoffice .content-list .list-box').forEach(function (b, i) {
                gsap.fromTo(b, { opacity: 0, y: 50 }, {
                    opacity: 1, y: 0, duration: .6, delay: .15 * i,
                    scrollTrigger: { trigger: '#pg_backoffice', start: 'top 55%', once: true }
                });
            });
        }
    }

    /* ============================================================
       6. ABOUT — hiện 6 ô khi cuộn tới
       ============================================================ */
    var aboutList = $('#pgAboutList');
    if (aboutList && window.gsap && window.ScrollTrigger) {
        $$('.list-box', aboutList).forEach(function (b, i) {
            gsap.fromTo(b, { opacity: 0, y: 50 }, {
                opacity: 1, y: 0, duration: .6, delay: .12 * i,
                scrollTrigger: { trigger: aboutList, start: 'top 75%', once: true }
            });
        });
        gsap.fromTo('.pg-about .about-content', { opacity: 0, y: 80 }, {
            opacity: 1, y: 0, duration: .7,
            scrollTrigger: { trigger: '#pg_about', start: 'top 80%', once: true }
        });
    }

    /* ============================================================
       7. PARTNER — logo hiện dần
       ============================================================ */
    var pLogo = $('#pgPartnerLogo');
    if (pLogo && window.gsap && window.ScrollTrigger) {
        $$('#pgPartnerLogo > div').forEach(function (d, i) {
            gsap.fromTo(d, { opacity: 0, y: 40 }, {
                opacity: 1, y: 0, duration: .5, delay: .08 * i,
                scrollTrigger: { trigger: pLogo, start: 'top 85%', once: true }
            });
        });
        gsap.fromTo('.pg-partner .partner-content', { opacity: 0, y: 80 }, {
            opacity: 1, y: 0, duration: .7,
            scrollTrigger: { trigger: '#pg_partner', start: 'top 80%', once: true }
        });
    }

    /* ============================================================
       8. VISION — sóng video + tiêu đề
       ============================================================ */
    if (window.gsap && window.ScrollTrigger) {
        gsap.fromTo('.pg-vision .vision-content', { opacity: 0, y: 80 }, {
            opacity: 1, y: 0, duration: .7,
            scrollTrigger: { trigger: '#pg_vision', start: 'top 75%', once: true }
        });
        gsap.fromTo('#pgVisionWave', { opacity: 0, scale: .9 }, {
            opacity: 1, scale: 1, duration: .8,
            scrollTrigger: { trigger: '#pg_vision', start: 'top 60%', once: true }
        });
    }

    /* ============================================================
       9. GALLERY — cuộn ngang
       ============================================================ */
    $$('[data-pg-gallery]').forEach(function (track) {
        if (track.dataset.pgBound) { return; }
        track.dataset.pgBound = '1';

        var wrap = track.parentElement;
        var prev = $('.pg-gal-prev', wrap);
        var next = $('.pg-gal-next', wrap);
        var step = function () {
            var card = $('.pg-gal-card', track);
            return card ? card.offsetWidth + 14 : 260;
        };
        if (prev) { prev.addEventListener('click', function () { track.scrollBy({ left: -step() * 2, behavior: 'smooth' }); }); }
        if (next) { next.addEventListener('click', function () { track.scrollBy({ left: step() * 2, behavior: 'smooth' }); }); }
    });
})(); });
</script>
<?php endif; ?>
