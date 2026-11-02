<?php /** @var array $game,$related */ ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/games') ?>">Kho game</a></li>
            <li class="breadcrumb-item active"><?= e($game['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <div class="game-thumb rounded-3 mb-3" style="border-radius:16px">
                    <?php if (!empty($game['icon'])): ?>
                        <img src="<?= e(media_url((string)$game['icon'])) ?>" alt="<?= e($game['name']) ?>"
                             onerror="this.src='<?= asset('img/placeholder.svg') ?>'">
                    <?php else: ?>
                        <img src="<?= asset('img/placeholder.svg') ?>" alt="<?= e($game['name']) ?>">
                    <?php endif; ?>
                </div>
                <h1 class="h4 fw-bold mb-1"><?= e($game['name']) ?></h1>
                <p class="text-secondary small mb-3">
                    <i class="fas fa-cube me-1"></i><?= e($game['provider_name']) ?>
                    <?php if (!empty($game['rtp'])): ?>
                        · RTP <?= e($game['rtp']) ?>%
                    <?php endif; ?>
                </p>

                <?php if (!empty($game['description'])): ?>
                    <p class="text-secondary small"><?= e($game['description']) ?></p>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if (!empty($game['is_free'])): ?>
                        <span class="badge-status st-approved">MIỄN PHÍ</span>
                    <?php endif; ?>
                    <?php if (!empty($game['is_hot'])): ?>
                        <span class="badge-status st-processing">HOT</span>
                    <?php endif; ?>
                    <?php if (!empty($game['is_new'])): ?>
                        <span class="badge-status st-active">MỚI</span>
                    <?php endif; ?>
                    <?php $ls = (string)($game['launch_state'] ?? 'ready'); ?>
                    <?php $pm = (string)($game['play_mode'] ?? 'demo'); ?>
                    <?php if ($ls !== 'ready'): ?>
                        <span class="badge-status st-pending"><i class="fas fa-flask me-1"></i>CHƠI THỬ — CHƯA ĐỒNG BỘ VÍ</span>
                    <?php elseif ($pm === 'real'): ?>
                        <span class="badge-status st-approved"><i class="fas fa-link me-1"></i>ĐỒNG BỘ VÍ 2 CHIỀU</span>
                    <?php else: ?>
                        <span class="badge-status st-pending"><i class="fas fa-flask me-1"></i>CHƠI THỬ</span>
                    <?php endif; ?>
                </div>

                <?php if ($ls !== 'ready'): ?>
                    <div class="lobby-external-note mb-3">
                        <i class="fas fa-circle-info me-2"></i>
                        <span>
                            Sảnh <strong><?= e($game['provider_name']) ?></strong> chưa công bố API đồng bộ ví
                            nên game này hiện ở <strong>chế độ chơi thử</strong> (tiền ảo).
                            Bạn vẫn chơi được để thử trước khi quyết định nạp tiền.
                        </span>
                    </div>
                <?php endif; ?>

                <a href="<?= url('/play/' . $game['slug']) ?>" class="btn btn-play w-100 py-2">
                    <i class="fas fa-play me-2"></i>CHƠI NGAY
                </a>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel-card">
                <h3><i class="fas fa-circle-info text-gold me-2"></i>Thông tin game</h3>
                <table class="data-table">
                    <tbody>
                        <tr><td class="text-secondary">Tên game</td><td class="text-end"><?= e($game['name']) ?></td></tr>
                        <tr><td class="text-secondary">Nhà cung cấp</td><td class="text-end"><?= e($game['provider_name']) ?></td></tr>
                        <tr><td class="text-secondary">Mã game</td><td class="text-end"><code><?= e($game['provider_game_id']) ?></code></td></tr>
                        <tr><td class="text-secondary">Loại</td><td class="text-end"><?= e(ucfirst($game['game_type'])) ?></td></tr>
                        <?php if ((float)$game['min_bet'] > 0): ?>
                        <tr><td class="text-secondary">Cược tối thiểu</td><td class="text-end"><?= money((float)$game['min_bet']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ((float)$game['max_bet'] > 0): ?>
                        <tr><td class="text-secondary">Cược tối đa</td><td class="text-end"><?= money((float)$game['max_bet']) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($related): ?>
            <div class="panel-card">
                <h3><i class="fas fa-layer-group text-gold me-2"></i>Game cùng sảnh</h3>
                <?php $gameList = $related; $providers = []; include BASE_PATH . '/app/Views/partials/game-grid.php'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
