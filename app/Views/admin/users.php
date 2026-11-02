<?php /** @var array $users */ $adminPage = 'users'; $q = $q ?? ''; ?>
<h1 class="page-title">Quản lý người dùng</h1>
<p class="page-sub">Tạo tài khoản mới, tìm kiếm, chỉnh sửa trạng thái và số dư người chơi.</p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-user-plus me-2"></i>Tạo tài khoản mới
    </button>
</div>

<div class="panel-card">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm username, email, họ tên...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Tìm</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Quyền</th><th class="text-end">Số dư</th><th class="text-end">Tổng nạp</th><th>VIP</th><th>Tick xanh</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-secondary"><?= (int)$u['id'] ?></td>
                    <td>
                        <?= e($u['username']) ?><?= verified_tick($u) ?>
                        <div class="text-secondary small"><?= e($u['full_name']) ?></div>
                    </td>
                    <td class="text-secondary small"><?= e($u['email']) ?></td>
                    <td><span class="badge-status st-processing"><?= e(strtoupper($u['role'])) ?></span></td>
                    <td class="text-end text-gold"><?= money((float)$u['balance']) ?></td>
                    <td class="text-end"><?= money((float)$u['total_deposit']) ?></td>
                    <td class="text-secondary"><?= (int)$u['vip_level'] ?></td>
                    <td class="text-center">
                        <form method="POST" action="<?= url('/admin/users/verify') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm <?= !empty($u['is_verified']) ? 'btn-primary' : 'btn-soft' ?>"
                                    title="<?= !empty($u['is_verified']) ? 'Thu hồi tick xanh' : 'Cấp tick xanh' ?>">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </form>
                    </td>
                    <td><span class="badge-status st-<?= e($u['status']) ?>"><?= e(strtoupper($u['status'])) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUser<?= (int)$u['id'] ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ Modal: tạo tài khoản mới ============ -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:var(--bg-card);border-color:var(--border-subtle)">
            <form method="POST" action="<?= url('/admin/users/create') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tạo tài khoản mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="new_username">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_username" name="username"
                                   placeholder="VD: admin01" required
                                   pattern="[a-zA-Z0-9_]{3,50}">
                            <div class="form-text">3-50 ký tự, chỉ chữ, số và dấu gạch dưới.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="new_email" name="email"
                                   placeholder="VD: admin01@tenmien.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_full_name">Họ và tên</label>
                            <input type="text" class="form-control" id="new_full_name" name="full_name"
                                   placeholder="VD: Nguyễn Văn A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_role">Vai trò</label>
                            <select class="form-select" id="new_role" name="role">
                                <option value="user" selected>User</option>
                                <option value="operator">Operator</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_vip_level">VIP level</label>
                            <input type="number" class="form-control" id="new_vip_level" name="vip_level"
                                   value="0" min="0" max="60">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="password"
                                   placeholder="Tối thiểu 8 ký tự" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password_confirm">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password_confirm" name="password_confirm"
                                   placeholder="Nhập lại mật khẩu" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="new_balance">Số dư ban đầu (đ)</label>
                            <input type="number" step="1000" class="form-control" id="new_balance" name="balance"
                                   value="0" min="0">
                            <div class="form-text">Để 0 nếu không cần cấp số dư. Thay đổi sẽ được ghi vào lịch sử giao dịch.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button class="btn btn-primary"><i class="fas fa-check me-2"></i>Tạo tài khoản</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($users as $u): ?>
<div class="modal fade" id="editUser<?= (int)$u['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:var(--bg-card);border-color:var(--border-subtle)">
            <form method="POST" action="<?= url('/admin/users/update') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa <?= e($u['username']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Số dư (đ)</label>
                        <input type="number" step="1000" class="form-control" name="balance" value="<?= (float)$u['balance'] ?>">
                        <div class="form-text" style="color:#6c6479">Thay đổi sẽ được ghi vào lịch sử giao dịch.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role">
                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="operator" <?= $u['role'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">VIP level</label>
                            <input type="number" class="form-control" name="vip_level" value="<?= (int)$u['vip_level'] ?>" min="0" max="60">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="banned" <?= $u['status'] === 'banned' ? 'selected' : '' ?>>Cấm</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
