<?php /** @var array $topics,$total,$page,$pages,$category,$categories */ ?>
<div class="container py-4">
    <h1 class="page-title">Diễn đàn</h1>
    <p class="page-sub">Cộng đồng chia sẻ kinh nghiệm, hỏi đáp và góp ý.</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= url('/services/forum') ?>" class="filter-chip <?= $category === '' ? 'active' : '' ?>">Tất cả</a>
        <?php foreach ($categories as $code => $label): ?>
            <a href="<?= url('/services/forum?category=' . urlencode($code)) ?>" class="filter-chip <?= $category === $code ? 'active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel-card">
                <?php if (!$topics): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments fs-1 d-block mb-3"></i>
                        <p class="mb-0">Chưa có chủ đề nào.</p>
                    </div>
                <?php else: ?>
                    <div class="forum-list">
                        <?php foreach ($topics as $t): ?>
                            <a href="<?= url('/services/forum/topic/' . $t['slug']) ?>" class="forum-row">
                                <div class="forum-avatar"><?= user_avatar($t, 'avatar') ?></div>
                                <div class="forum-main">
                                    <div class="forum-title">
                                        <?php if (!empty($t['is_pinned'])): ?><i class="fas fa-thumbtack text-gold me-1"></i><?php endif; ?>
                                        <?= e($t['title']) ?>
                                    </div>
                                    <div class="forum-meta">
                                        <?= e($t['username']) ?>
                                        <?= verified_tick($t) ?>
                                        · <span class="text-gold"><?= e(\App\Services\ForumService::categoryLabel((string)$t['category'])) ?></span>
                                        · <?= e(date('d/m', strtotime((string)$t['last_post_at']))) ?>
                                    </div>
                                </div>
                                <div class="forum-count">
                                    <strong><?= (int)$t['reply_count'] ?></strong>
                                    <small>trả lời</small>
                                </div>
                                <div class="forum-count">
                                    <strong><?= (int)$t['views'] ?></strong>
                                    <small>lượt xem</small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url('/services/forum?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="panel-card">
                <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Đăng chủ đề mới</h6>
                <form method="POST" action="<?= url('/services/forum/create') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <select name="category" class="form-select form-select-sm">
                            <?php foreach ($categories as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Tiêu đề" required maxlength="250">
                    </div>
                    <div class="mb-3">
                        <textarea name="body" class="form-control form-control-sm" rows="6" placeholder="Nội dung..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold btn-sm w-100">Đăng bài</button>
                </form>
            </div>
        </div>
    </div>
</div>
