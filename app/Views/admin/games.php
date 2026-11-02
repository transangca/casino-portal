<?php /** @var array $games,$providers */ $adminPage = 'games'; ?>
<h1 class="page-title">Quản lý game</h1>
<p class="page-sub">Đồng bộ danh sách game từ nhà cung cấp và quản lý cờ hiển thị.</p>

<div class="panel-card">
    <h3><i class="fas fa-sync text-gold me-2"></i>Đồng bộ từ nhà cung cấp</h3>
    <form method="POST" action="<?= url('/admin/games/sync') ?>"
          data-confirm="Tien hanh tai ve danh sach game tu nha cung cap?">
        <?= csrf_field() ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="provider">Nhà cung cấp</label>
                <select class="form-select" id="provider" name="provider">
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= e($p['code']) ?>"><?= e($p['name']) ?> (<?= e($p['driver']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100">Đồng bộ ngay</button>
            </div>
        </div>
    </form>
</div>

<div class="panel-card">
    <h3><i class="fas fa-dice text-gold me-2"></i>Danh sách game (<?= count($games) ?>)</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Ảnh</th><th>Tên</th><th>Sảnh</th><th>Mã</th><th>Trạng thái</th><th>Cờ</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($games as $g): ?>
                <tr>
                    <td style="width:52px">
                        <?php if (!empty($g['icon'])): ?>
                            <img src="<?= e(media_url((string)$g['icon'])) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px"
                                 onerror="this.src='<?= asset('img/placeholder.svg') ?>'">
                        <?php else: ?>
                            <img src="<?= asset('img/placeholder.svg') ?>" style="width:40px;height:40px;border-radius:8px">
                        <?php endif; ?>
                    </td>
                    <td><?= e($g['name']) ?></td>
                    <td class="text-secondary"><?= e($g['provider_name']) ?></td>
                    <td><code><?= e($g['provider_game_id']) ?></code></td>
                    <td><span class="badge-status st-<?= e($g['status']) ?>"><?= e(strtoupper($g['status'])) ?></span></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <form method="POST" action="<?= url('/admin/games/toggle') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <input type="hidden" name="field" value="is_featured">
                                <button class="btn btn-sm <?= $g['is_featured'] ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Nổi bật">
                                    <i class="fas fa-star"></i></button>
                            </form>
                            <form method="POST" action="<?= url('/admin/games/toggle') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <input type="hidden" name="field" value="is_hot">
                                <button class="btn btn-sm <?= $g['is_hot'] ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Hot">
                                    <i class="fas fa-fire"></i></button>
                            </form>
                            <form method="POST" action="<?= url('/admin/games/toggle') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <input type="hidden" name="field" value="is_new">
                                <button class="btn btn-sm <?= $g['is_new'] ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Mới">
                                    <i class="fas fa-sparkles"></i></button>
                            </form>
                            <form method="POST" action="<?= url('/admin/games/toggle') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <input type="hidden" name="field" value="is_free">
                                <button class="btn btn-sm <?= $g['is_free'] ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Miễn phí">
                                    <i class="fas fa-gift"></i></button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <form method="POST" action="<?= url('/admin/games/toggle') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                            <input type="hidden" name="field" value="status">
                            <button class="btn btn-sm btn-outline-secondary">
                                <?= $g['status'] === 'active' ? 'Ẩn' : 'HIỆN' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
