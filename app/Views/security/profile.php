<?php
/**
 * Hồ sơ cá nhân: avatar, tên, ngôn ngữ, theme, liên kết mạng xã hội, đổi mật khẩu.
 *
 * @var array $user
 * @var array $socials
 */
$linked = [];
foreach ($socials as $s) {
    $linked[$s['provider']] = true;
}
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <h1 class="page-title"><i class="fas fa-user-gear text-gold me-2"></i>Hồ sơ &amp; bảo mật</h1>
            <p class="page-sub">Quản lý thông tin cá nhân, ảnh đại diện, ngôn ngữ và giao diện.</p>

            <div class="row g-4">

                <!-- ===== Avatar ===== -->
                <div class="col-lg-3">
                    <div class="panel-card text-center">
                        <div class="mb-3">
                            <?= user_avatar($user, 'avatar avatar-xl') ?>
                        </div>
                        <h4 class="mb-1" style="font-size:1rem">
                            <?= e($user['full_name'] ?: $user['username']) ?> <?= verified_tick($user, true) ?>
                        </h4>
                        <p class="text-muted-2 small mb-0">@<?= e($user['username']) ?></p>
                        <p class="mt-2 mb-0">
                            <span class="badge-status st-active">VIP <?= (int)($user['vip_level'] ?? 0) ?></span>
                        </p>
                        <?php if (!empty($user['is_verified'])): ?>
                            <p class="form-hint mt-2 mb-0"><i class="fas fa-badge-check text-info me-1"></i>Tài khoản đã xác minh</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== Thông tin ===== -->
                <div class="col-lg-9">

                    <!-- Hồ sơ -->
                    <div class="panel-card mb-4">
                        <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-id-card text-gold me-2"></i>Thông tin cá nhân</h3>
                        <form method="POST" action="<?= url('/security/profile') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="full_name">Họ tên</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?= e($user['full_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="phone">Số điện thoại</label>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                           value="<?= e($user['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="username">Tên đăng nhập</label>
                                    <input type="text" class="form-control" id="username" value="<?= e($user['username']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email</label>
                                    <input type="email" class="form-control" id="email" value="<?= e($user['email']) ?>" disabled>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="avatar">Đổi ảnh đại diện</label>
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                    <div class="form-hint mt-1">JPG/PNG/GIF/WEBP, tối đa 2MB.</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="language">Ngôn ngữ</label>
                                    <select class="form-select" id="language" name="language">
                                        <?php foreach (available_locales() as $code => $info): ?>
                                            <option value="<?= e($code) ?>" <?= $code === ($user['language'] ?? 'vi') ? 'selected' : '' ?>>
                                                <?= $info['flag'] ?> <?= e($info['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="theme">Giao diện</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="dark"   <?= ($user['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Tối</option>
                                        <option value="light"  <?= ($user['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Sáng</option>
                                        <option value="system" <?= ($user['theme'] ?? '') === 'system' ? 'selected' : '' ?>>Theo hệ thống</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-gold mt-4"><i class="fas fa-save me-2"></i>Lưu thay đổi</button>
                        </form>
                    </div>

                    <!-- Liên kết mạng xã hội -->
                    <div class="panel-card mb-4">
                        <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-share-nodes text-gold me-2"></i>Liên kết mạng xã hội</h3>
                        <div class="row g-3">
                            <?php foreach (['google', 'facebook', 'telegram'] as $p): ?>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-2 p-2" style="border:1px solid var(--casino-line-soft);border-radius:.7rem">
                                        <i class="fab fa-<?= $p === 'telegram' ? 'telegram-plane' : $p ?> fs-5"></i>
                                        <div class="flex-grow-1">
                                            <div class="small fw-semibold"><?= e(ucfirst($p)) ?></div>
                                            <div class="form-hint"><?= isset($linked[$p]) ? 'Đã liên kết' : 'Chưa liên kết' ?></div>
                                        </div>
                                        <?php if (isset($linked[$p])): ?>
                                            <form method="POST" action="<?= url('/security/social/' . $p . '/disconnect') ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger" title="Gỡ liên kết">
                                                    <i class="fas fa-xmark"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-gold" href="<?= url('/auth/social/' . $p) ?>">
                                                <i class="fas fa-link"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Đổi mật khẩu -->
                    <div class="panel-card mb-4">
                        <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-lock text-gold me-2"></i>Đổi mật khẩu</h3>
                        <form method="POST" action="<?= url('/security/password') ?>">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="new_password">Mật khẩu mới</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="new_password_confirm">Xác nhận</label>
                                    <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" required>
                                </div>
                            </div>
                            <button class="btn btn-outline-gold mt-4"><i class="fas fa-key me-2"></i>Đổi mật khẩu</button>
                        </form>
                    </div>

                    <!-- 2FA -->
                    <div class="panel-card">
                        <h3 class="mb-3" style="font-size:1.05rem"><i class="fas fa-shield-halved text-gold me-2"></i>Bảo mật 2FA</h3>
                        <p class="text-muted-2 small">Bật xác thực 2 lớp bằng Google Authenticator để bảo vệ tài khoản.</p>
                        <a href="<?= url('/security/2fa') ?>" class="btn btn-gold">
                            <i class="fas fa-qrcode me-2"></i>Quản lý 2FA
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
