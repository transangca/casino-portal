<?php /** @var array $topic,$posts,$categories */ ?>
<div class="container py-4" style="max-width:900px">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/services/forum') ?>">Diễn đàn</a></li>
            <li class="breadcrumb-item active">Chủ đề</li>
        </ol>
    </nav>

    <h1 class="page-title" style="font-size:1.5rem"><?= e($topic['title']) ?></h1>
    <div class="forum-meta mb-4">
        <i class="fas fa-tag me-1"></i>
        <?= e(\App\Services\ForumService::categoryLabel((string)$topic['category'])) ?>
        · <?= (int)$topic['views'] ?> lượt xem
    </div>

    <div class="forum-thread">
        <?php foreach ($posts as $p): ?>
            <article class="forum-post" id="post-<?= (int)$p['id'] ?>">
                <div class="forum-post-side">
                    <div class="forum-avatar-lg"><?= user_avatar($p, 'avatar') ?></div>
                    <div class="small fw-semibold"><?= e($p['username']) ?></div>
                    <?= verified_tick($p) ?>
                    <div class="small text-muted-2">VIP <?= (int)($p['vip_level'] ?? 0) ?></div>
                </div>
                <div class="forum-post-body">
                    <div class="small text-muted-2 mb-2"><?= e(date('d/m/Y H:i', strtotime((string)$p['created_at']))) ?></div>
                    <?php if (!empty($p['is_solution'])): ?>
                        <div class="alert alert-success py-1 px-2 small mb-2">
                            <i class="fas fa-check-circle me-1"></i>Đã được chọn làm câu trả lời đúng
                        </div>
                    <?php endif; ?>
                    <div class="forum-post-text"><?= nl2br(e((string)$p['body'])) ?></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (empty($topic['is_locked'])): ?>
        <div class="panel-card mt-4">
            <h6 class="mb-3"><i class="fas fa-reply me-2"></i>Trả lời</h6>
            <form method="POST" action="<?= url('/services/forum/reply') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                <textarea name="body" class="form-control mb-3" rows="5" required placeholder="Nội dung câu trả lời..."></textarea>
                <button type="submit" class="btn btn-gold">Gửi trả lời</button>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4">Chủ đề đã khoá, không thể trả lời thêm.</div>
    <?php endif; ?>
</div>
