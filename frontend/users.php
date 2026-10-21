<?php
/**
 * Hồ sơ cá nhân & Quản trị người dùng (IAM) - Auth Service (Java · 8081)
 * Tích hợp đầy đủ các API mới từ nhánh feature/auth-user-management:
 *  - GET    /api/profile
 *  - PUT    /api/profile
 *  - PUT    /api/profile/password
 *  - GET    /api/admin/users
 *  - GET    /api/admin/users/{id}
 *  - POST   /api/admin/users
 *  - PUT    /api/admin/users/{id}
 *  - DELETE /api/admin/users/{id}
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$sessUser = currentUser();
$isAdmin  = strtoupper($sessUser['role'] ?? '') === 'ADMIN';

/** Định dạng timestamp trả về từ Java Auth Service (epoch ms hoặc chuỗi ISO) */
function fmtAuthTime($val): string
{
    if ($val === null || $val === '') {
        return '—';
    }
    if (is_numeric($val)) {
        $ts = (float) $val;
        if ($ts > 1e11) {
            $ts = $ts / 1000;
        }
        return date('d/m/Y H:i', (int) $ts);
    }
    return dateVN((string) $val, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $payload = [
            'fullName'    => trim((string) ($_POST['fullName'] ?? '')),
            'phoneNumber' => trim((string) ($_POST['phoneNumber'] ?? '')),
        ];
        $r = api('PUT', '/api/profile', $payload);
        if ($r['ok']) {
            $updatedProfile = $r['body']['profile'] ?? [];
            if (is_array($updatedProfile) && $updatedProfile) {
                $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $updatedProfile);
            } else {
                $_SESSION['user']['fullName']    = $payload['fullName'];
                $_SESSION['user']['phoneNumber'] = $payload['phoneNumber'];
            }
            flash('success', 'Đã cập nhật thông tin hồ sơ cá nhân thành công.');
        } else {
            flash('danger', 'Không thể cập nhật hồ sơ: ' . $r['error']);
        }
        redirect('users.php');
    } elseif ($action === 'change_password') {
        $curPwd = (string) ($_POST['currentPassword'] ?? '');
        $newPwd = (string) ($_POST['newPassword'] ?? '');
        $cfmPwd = (string) ($_POST['confirmPassword'] ?? '');
        if ($newPwd !== $cfmPwd) {
            flash('danger', 'Mật khẩu xác nhận không khớp với mật khẩu mới.');
            redirect('users.php');
        }
        $r = api('PUT', '/api/profile/password', [
            'currentPassword' => $curPwd,
            'newPassword'     => $newPwd,
        ]);
        if ($r['ok']) {
            flash('success', 'Đã đổi mật khẩu bảo mật thành công.');
        } else {
            flash('danger', 'Đổi mật khẩu thất bại: ' . $r['error']);
        }
        redirect('users.php');
    } elseif ($action === 'admin_create_user' && $isAdmin) {
        $payload = [
            'username'    => trim((string) ($_POST['username'] ?? '')),
            'email'       => trim((string) ($_POST['email'] ?? '')),
            'password'    => (string) ($_POST['password'] ?? ''),
            'fullName'    => trim((string) ($_POST['fullName'] ?? '')),
            'phoneNumber' => trim((string) ($_POST['phoneNumber'] ?? '')),
            'role'        => strtoupper(trim((string) ($_POST['role'] ?? 'USER'))),
            'status'      => strtoupper(trim((string) ($_POST['status'] ?? 'ACTIVE'))),
        ];
        $r = api('POST', '/api/admin/users', $payload);
        if ($r['ok']) {
            flash('success', 'Đã tạo người dùng @' . $payload['username'] . ' (' . $payload['role'] . ') thành công.');
        } else {
            flash('danger', 'Không tạo được người dùng: ' . $r['error']);
        }
        redirect('users.php#adminUsersSection');
    } elseif ($action === 'admin_update_user' && $isAdmin) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $payload = [
            'username'    => trim((string) ($_POST['username'] ?? '')),
            'email'       => trim((string) ($_POST['email'] ?? '')),
            'fullName'    => trim((string) ($_POST['fullName'] ?? '')),
            'phoneNumber' => trim((string) ($_POST['phoneNumber'] ?? '')),
            'role'        => strtoupper(trim((string) ($_POST['role'] ?? 'USER'))),
            'status'      => strtoupper(trim((string) ($_POST['status'] ?? 'ACTIVE'))),
        ];
        $r = api('PUT', '/api/admin/users/' . $targetId, $payload);
        if ($r['ok']) {
            if ($targetId === uid() && !empty($r['body']['user'])) {
                $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $r['body']['user']);
            }
            flash('success', 'Đã cập nhật tài khoản #' . $targetId . ' (@' . $payload['username'] . ') thành công.');
        } else {
            flash('danger', 'Cập nhật thất bại: ' . $r['error']);
        }
        redirect('users.php#adminUsersSection');
    } elseif ($action === 'admin_delete_user' && $isAdmin) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === uid()) {
            flash('warning', 'Không thể tự xoá tài khoản Quản trị viên đang đăng nhập.');
            redirect('users.php#adminUsersSection');
        }
        $r = api('DELETE', '/api/admin/users/' . $targetId);
        if ($r['ok']) {
            flash('success', 'Đã xoá người dùng #' . $targetId . ' khỏi hệ thống.');
        } else {
            flash('danger', 'Không thể xoá người dùng: ' . $r['error']);
        }
        redirect('users.php#adminUsersSection');
    }
    redirect('users.php');
}

// Gọi song song GET /api/profile (+ GET /api/admin/users nếu là ADMIN)
$calls = [
    'profile'  => ['GET', '/api/profile'],
    'validate' => ['GET', '/api/auth/validate'],
];
if ($isAdmin) {
    $calls['users'] = ['GET', '/api/admin/users'];
}
$resAll = apiAll($calls);
$rProfile  = $resAll['profile'];
$rValidate = $resAll['validate'];
$rUsers    = $resAll['users'] ?? null;

$profile = ($rProfile['ok'] && !empty($rProfile['body']['profile']))
    ? $rProfile['body']['profile']
    : $sessUser;

if ($rProfile['ok'] && !empty($rProfile['body']['profile'])) {
    $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $profile);
}

$allUsers = ($rUsers && $rUsers['ok'] && !empty($rUsers['body']['users']))
    ? $rUsers['body']['users']
    : [];

$errors = collectErrors($rProfile, ...($rUsers ? [$rUsers] : []));

$totalUsers   = count($allUsers);
$activeUsers  = count(array_filter($allUsers, fn($u) => strtoupper($u['status'] ?? '') === 'ACTIVE'));
$blockedUsers = count(array_filter($allUsers, fn($u) => strtoupper($u['status'] ?? '') === 'BLOCKED'));
$adminCount   = count(array_filter($allUsers, fn($u) => strtoupper($u['role'] ?? '') === 'ADMIN'));

renderHeader('Hồ sơ & Quản trị IAM', 'users');
renderApiErrors($errors);

$dispName = $profile['fullName'] ?: ($profile['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    strtoupper($profile['role'] ?? 'USER') === 'ADMIN' ? 'IAM Root' : 'Member Pass',
    'IDENTITY & ACCESS MANAGEMENT',
    'UID #' . str_pad((string) ($profile['id'] ?? 1), 4, '0', STR_PAD_LEFT) . ' •••• ' . strtoupper($profile['status'] ?? 'ACTIVE'),
    mb_strtoupper($dispName),
    'VAI TRÒ HỆ THỐNG',
    strtoupper($profile['role'] ?? 'USER'),
    'TRẠNG THÁI JWT',
    ($rValidate['ok'] && !empty($rValidate['body']['valid'])) ? 'VERIFIED' : 'ACTIVE',
    'VISA',
    'visa'
);

$heroActions = '<div class="luxury-hero-actions">'
    . '<a href="#profileSection" class="btn-hero-pri"><i class="fa-solid fa-id-badge"></i> Hồ sơ & Bảo mật</a>'
    . ($isAdmin ? '<button type="button" class="btn-hero-sec" data-open="createUserModal"><i class="fa-solid fa-user-plus"></i> Tạo người dùng mới</button>' : '')
    . '</div>';

pageHero(
    'Auth Service · Java 17 (Port 8081)',
    'Hồ Sơ Cá Nhân & Quản Trị IAM',
    'Quản lý thông tin định danh cá nhân, đổi mật khẩu bảo mật BCrypt và phân quyền người dùng (RBAC Admin User CRUD) trực tiếp qua API Gateway.' . $heroActions,
    $cardWidget
);
?>

<!-- KHỐI 1: HỒ SƠ CÁ NHÂN & ĐỔI MẬT KHẨU (Dành cho mọi tài khoản) -->
<section class="grid grid-2 mb" id="profileSection">
    <!-- Card Trái: Thông tin cá nhân (GET / PUT /api/profile) -->
    <div class="panel iam-profile-panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon"><i class="fa-solid fa-id-card-clip"></i></span>
                <div>
                    <h2>Hồ sơ định danh cá nhân</h2>
                    <p>GET &amp; PUT /api/profile · Cập nhật họ tên và số điện thoại</p>
                </div>
            </div>
            <span class="pill <?= strtoupper($profile['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'green' : 'red' ?>">
                <i class="fa-solid <?= strtoupper($profile['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'fa-circle-check' : 'fa-ban' ?>"></i>
                <?= e($profile['status'] ?? 'ACTIVE') ?>
            </span>
        </div>

        <div class="iam-identity-banner">
            <div class="iam-avatar-lg">
                <span><?= e(initials($dispName)) ?></span>
                <i class="iam-status-dot <?= strtoupper($profile['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'is-online' : 'is-blocked' ?>"></i>
            </div>
            <div class="iam-identity-meta">
                <div class="iam-identity-name-row">
                    <h3><?= e($dispName) ?></h3>
                    <span class="pill <?= strtoupper($profile['role'] ?? '') === 'ADMIN' ? 'violet' : 'blue' ?>">
                        <i class="fa-solid <?= strtoupper($profile['role'] ?? '') === 'ADMIN' ? 'fa-crown' : 'fa-user' ?>"></i>
                        <?= strtoupper($profile['role'] ?? '') === 'ADMIN' ? 'ADMIN' : 'USER' ?>
                    </span>
                </div>
                <div class="iam-identity-sub">
                    <span><i class="fa-solid fa-at"></i> <?= e($profile['username'] ?? '') ?></span>
                    <span>·</span>
                    <span><i class="fa-regular fa-envelope"></i> <?= e($profile['email'] ?? '') ?></span>
                </div>
                <div class="iam-identity-dates">
                    <span><i class="fa-regular fa-calendar-plus"></i> Tạo: <?= fmtAuthTime($profile['createdAt'] ?? null) ?></span>
                    <span><i class="fa-solid fa-clock-rotate-left"></i> Cập nhật: <?= fmtAuthTime($profile['updatedAt'] ?? null) ?></span>
                </div>
            </div>
        </div>

        <form method="post" class="iam-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="form-grid">
                <div class="field">
                    <label>Tên đăng nhập (Cố định)</label>
                    <input class="input" type="text" value="<?= e($profile['username'] ?? '') ?>" disabled title="Username không thể thay đổi qua /api/profile">
                </div>
                <div class="field">
                    <label>Địa chỉ Email (Cố định)</label>
                    <input class="input" type="email" value="<?= e($profile['email'] ?? '') ?>" disabled title="Email chỉ Quản trị viên mới được phép thay đổi">
                </div>
                <div class="field">
                    <label for="pfFullName">Họ và tên hiển thị</label>
                    <input class="input" id="pfFullName" name="fullName" type="text" maxlength="100"
                        value="<?= e($profile['fullName'] ?? '') ?>" placeholder="Nhập họ và tên đầy đủ">
                </div>
                <div class="field">
                    <label for="pfPhone">Số điện thoại liên hệ</label>
                    <input class="input" id="pfPhone" name="phoneNumber" type="text" pattern="\+?[0-9]{9,15}"
                        value="<?= e($profile['phoneNumber'] ?? '') ?>" placeholder="VD: 0901234567 (9–15 chữ số)">
                </div>
            </div>
            <div class="iam-form-foot">
                <span class="small t-muted"><i class="fa-solid fa-lock"></i> Role &amp; Status được bảo vệ ở tầng Auth Service</span>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi hồ sơ</button>
            </div>
        </form>
    </div>

    <!-- Card Phải: Đổi mật khẩu bảo mật (PUT /api/profile/password) -->
    <div class="panel iam-security-panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon amber"><i class="fa-solid fa-key"></i></span>
                <div>
                    <h2>Bảo mật &amp; Đổi mật khẩu</h2>
                    <p>PUT /api/profile/password · Xác thực mật khẩu hiện tại &amp; băm BCrypt</p>
                </div>
            </div>
            <span class="pill blue"><i class="fa-solid fa-shield-halved"></i> JWT HS256</span>
        </div>

        <form method="post" class="iam-form" id="pwdForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="form-grid">
                <div class="field full">
                    <label for="curPwd">Mật khẩu hiện tại</label>
                    <div class="pwd-input-wrap">
                        <input class="input" id="curPwd" name="currentPassword" type="password" required autocomplete="current-password" placeholder="Nhập mật khẩu đang sử dụng">
                        <button type="button" class="pwd-toggle-btn" data-toggle-pwd="curPwd" title="Hiện/ẩn mật khẩu"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>
                <div class="field">
                    <label for="newPwd">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                    <div class="pwd-input-wrap">
                        <input class="input" id="newPwd" name="newPassword" type="password" minlength="6" maxlength="100" required autocomplete="new-password" placeholder="Tối thiểu 6 ký tự, khác mật khẩu cũ">
                        <button type="button" class="pwd-toggle-btn" data-toggle-pwd="newPwd" title="Hiện/ẩn mật khẩu"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>
                <div class="field">
                    <label for="cfmPwd">Xác nhận mật khẩu mới</label>
                    <div class="pwd-input-wrap">
                        <input class="input" id="cfmPwd" name="confirmPassword" type="password" minlength="6" maxlength="100" required autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
                        <button type="button" class="pwd-toggle-btn" data-toggle-pwd="cfmPwd" title="Hiện/ẩn mật khẩu"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <div class="pwd-strength-box">
                <div class="pwd-strength-head">
                    <span>Độ mạnh mật khẩu mới</span>
                    <strong id="pwdStrengthText">Chưa nhập</strong>
                </div>
                <div class="pwd-strength-bar"><span id="pwdStrengthFill" style="width:0%"></span></div>
            </div>

            <div class="iam-jwt-status-strip">
                <div>
                    <span class="small t-muted">Phiên JWT hiện tại (GET /api/auth/validate)</span>
                    <div><strong>UID #<?= (int) ($profile['id'] ?? 1) ?> · @<?= e($profile['username'] ?? '') ?></strong> · Vai trò <span class="t-ink"><?= e($profile['role'] ?? 'USER') ?></span></div>
                </div>
                <span class="pill green"><i class="fa-solid fa-check-double"></i> Hợp lệ</span>
            </div>

            <div class="iam-form-foot">
                <span class="small t-muted"><i class="fa-solid fa-circle-info"></i> Mật khẩu mới phải từ 6–100 ký tự và khác mật khẩu cũ</span>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Cập nhật mật khẩu</button>
            </div>
        </form>
    </div>
</section>

<!-- KHỐI 2: QUẢN TRỊ NGƯỜI DÙNG HỆ THỐNG (ADMIN USER CRUD - /api/admin/users) -->
<section class="panel mb" id="adminUsersSection">
    <div class="panel-head" style="flex-wrap:wrap;gap:1rem">
        <div class="panel-title">
            <span class="p-icon violet"><i class="fa-solid fa-users-gear"></i></span>
            <div>
                <h2>Quản trị người dùng hệ thống (Admin User CRUD)</h2>
                <p>GET · POST · PUT · DELETE /api/admin/users · Phân quyền RBAC &amp; Quản lý trạng thái tài khoản</p>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <div style="display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap">
                <div class="iam-kpi-pills">
                    <span class="pill blue" title="Tổng số người dùng"><i class="fa-solid fa-users"></i> <?= $totalUsers ?> Users</span>
                    <span class="pill green" title="Đang hoạt động"><i class="fa-solid fa-circle-check"></i> <?= $activeUsers ?> Active</span>
                    <?php if ($blockedUsers > 0): ?>
                        <span class="pill red" title="Đang bị khoá"><i class="fa-solid fa-ban"></i> <?= $blockedUsers ?> Blocked</span>
                    <?php endif; ?>
                    <span class="pill violet" title="Số Quản trị viên"><i class="fa-solid fa-crown"></i> <?= $adminCount ?> Admin</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-open="createUserModal">
                    <i class="fa-solid fa-user-plus"></i> Tạo người dùng mới
                </button>
            </div>
        <?php else: ?>
            <span class="pill amber"><i class="fa-solid fa-lock"></i> Yêu cầu quyền ADMIN</span>
        <?php endif; ?>
    </div>

    <?php if (!$isAdmin): ?>
        <div class="alert alert-warning" style="margin:0.5rem 0">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Bạn đang đăng nhập với vai trò <strong>USER (@<?= e($profile['username'] ?? '') ?>)</strong>. Các API <code>/api/admin/users/*</code> được bảo vệ bởi chốt chặn RBAC tại <code>AdminUserHandler.java</code> (chỉ cho phép <code>role = ADMIN</code>, trả về <code>403 Forbidden</code> với tài khoản thường). Hãy chuyển sang tài khoản <strong>admin</strong> để thao tác toàn quyền Quản trị viên.</span>
        </div>
    <?php else: ?>
        <!-- Thanh tìm kiếm & lọc nhanh danh sách người dùng -->
        <div class="toolbar iam-user-toolbar" style="margin-bottom:1rem;flex-wrap:wrap">
            <div class="field" style="flex:1;min-width:220px">
                <label for="userSearchInput">Tìm kiếm nhanh</label>
                <input class="input" id="userSearchInput" type="search" placeholder="Tìm theo username, họ tên, email, số điện thoại...">
            </div>
            <div class="field" style="min-width:150px">
                <label for="userRoleFilter">Vai trò (Role)</label>
                <select class="select" id="userRoleFilter">
                    <option value="">Tất cả vai trò</option>
                    <option value="ADMIN">ADMIN (Quản trị)</option>
                    <option value="USER">USER (Thành viên)</option>
                </select>
            </div>
            <div class="field" style="min-width:160px">
                <label for="userStatusFilter">Trạng thái (Status)</label>
                <select class="select" id="userStatusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="ACTIVE">ACTIVE (Hoạt động)</option>
                    <option value="BLOCKED">BLOCKED (Đã khoá)</option>
                </select>
            </div>
        </div>

        <?php if (!$allUsers): ?>
            <?= emptyState('fa-users', 'Chưa có người dùng nào', 'Bấm "Tạo người dùng mới" để thêm tài khoản vào hệ thống.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table" id="adminUsersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Người dùng</th>
                            <th>Liên hệ (Email / SĐT)</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th class="t-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allUsers as $u):
                        $uId     = (int) ($u['id'] ?? 0);
                        $uName   = $u['fullName'] ?: ($u['username'] ?? 'User');
                        $uRole   = strtoupper($u['role'] ?? 'USER');
                        $uStatus = strtoupper($u['status'] ?? 'ACTIVE');
                        $isSelf  = $uId === uid();
                        $searchBlob = strtolower(($u['username'] ?? '') . ' ' . ($u['fullName'] ?? '') . ' ' . ($u['email'] ?? '') . ' ' . ($u['phoneNumber'] ?? ''));
                    ?>
                        <tr data-user-row
                            data-search="<?= e($searchBlob) ?>"
                            data-role="<?= e($uRole) ?>"
                            data-status="<?= e($uStatus) ?>">
                            <td class="money small t-muted">#<?= $uId ?></td>
                            <td>
                                <div class="cell-flex">
                                    <span class="cat-icon" style="--c:<?= $uRole === 'ADMIN' ? '#7c3aed' : '#2563eb' ?>">
                                        <span style="font-weight:800;font-size:0.78rem"><?= e(initials($uName)) ?></span>
                                    </span>
                                    <div>
                                        <strong class="t-ink"><?= e($uName) ?></strong>
                                        <?php if ($isSelf): ?>
                                            <span class="pill blue" style="font-size:0.68rem;padding:0.1rem 0.45rem;margin-left:0.25rem">Bạn</span>
                                        <?php endif; ?>
                                        <div class="small t-muted">@<?= e($u['username'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-envelope t-muted"></i> <?= e($u['email'] ?? '—') ?></div>
                                <div class="small t-muted"><i class="fa-solid fa-phone"></i> <?= e($u['phoneNumber'] ?: 'Chưa cập nhật') ?></div>
                            </td>
                            <td>
                                <span class="pill <?= $uRole === 'ADMIN' ? 'violet' : 'blue' ?>">
                                    <i class="fa-solid <?= $uRole === 'ADMIN' ? 'fa-crown' : 'fa-user' ?>"></i>
                                    <?= e($uRole) ?>
                                </span>
                            </td>
                            <td>
                                <span class="pill <?= $uStatus === 'ACTIVE' ? 'green' : 'red' ?>">
                                    <i class="fa-solid <?= $uStatus === 'ACTIVE' ? 'fa-circle-check' : 'fa-ban' ?>"></i>
                                    <?= e($uStatus) ?>
                                </span>
                            </td>
                            <td class="small t-muted"><?= fmtAuthTime($u['createdAt'] ?? null) ?></td>
                            <td class="t-right">
                                <div class="iam-row-actions">
                                    <button type="button" class="btn-icon"
                                        title="Xem chi tiết (GET /api/admin/users/<?= $uId ?>)"
                                        data-inspect-user="<?= $uId ?>">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn-icon edit"
                                        title="Chỉnh sửa người dùng #<?= $uId ?>"
                                        data-edit-user="<?= $uId ?>"
                                        data-username="<?= e($u['username'] ?? '') ?>"
                                        data-email="<?= e($u['email'] ?? '') ?>"
                                        data-fullname="<?= e($u['fullName'] ?? '') ?>"
                                        data-phone="<?= e($u['phoneNumber'] ?? '') ?>"
                                        data-role="<?= e($uRole) ?>"
                                        data-status="<?= e($uStatus) ?>">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </button>
                                    <?php if (!$isSelf): ?>
                                        <button type="button" class="btn-icon del"
                                            title="Xoá người dùng #<?= $uId ?>"
                                            data-del-user="<?= $uId ?>"
                                            data-del-username="<?= e($u['username'] ?? '') ?>"
                                            data-del-fullname="<?= e($uName) ?>"
                                            data-del-email="<?= e($u['email'] ?? '') ?>"
                                            data-del-role="<?= e($uRole) ?>">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($isAdmin): ?>
<!-- Modal Tạo người dùng mới (POST /api/admin/users) -->
<div class="modal" id="createUserModal">
    <div class="modal-dialog" style="max-width:560px">
        <div class="modal-head">
            <span class="p-icon violet"><i class="fa-solid fa-user-plus"></i></span>
            <div style="flex:1">
                <h3>Tạo người dùng mới</h3>
                <p>POST /api/admin/users · Hỗ trợ gán quyền USER hoặc ADMIN</p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="admin_create_user">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="cuUsername">Tên đăng nhập (Username) *</label>
                        <input class="input" id="cuUsername" name="username" required pattern="[A-Za-z0-9._]{3,50}" placeholder="3–50 ký tự (chữ, số, ., _)">
                    </div>
                    <div class="field">
                        <label for="cuEmail">Email *</label>
                        <input class="input" id="cuEmail" name="email" type="email" required maxlength="100" placeholder="user@cashflow.local">
                    </div>
                    <div class="field full">
                        <label for="cuPassword">Mật khẩu khởi tạo *</label>
                        <input class="input" id="cuPassword" name="password" type="password" required minlength="6" maxlength="100" placeholder="Tối thiểu 6 ký tự">
                    </div>
                    <div class="field">
                        <label for="cuFullName">Họ và tên</label>
                        <input class="input" id="cuFullName" name="fullName" maxlength="100" placeholder="Nguyễn Văn B">
                    </div>
                    <div class="field">
                        <label for="cuPhone">Số điện thoại</label>
                        <input class="input" id="cuPhone" name="phoneNumber" pattern="\+?[0-9]{9,15}" placeholder="0901234567">
                    </div>
                    <div class="field">
                        <label for="cuRole">Vai trò (Role)</label>
                        <select class="select" id="cuRole" name="role">
                            <option value="USER">USER (Thành viên)</option>
                            <option value="ADMIN">ADMIN (Quản trị viên)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="cuStatus">Trạng thái (Status)</label>
                        <select class="select" id="cuStatus" name="status">
                            <option value="ACTIVE">ACTIVE (Hoạt động)</option>
                            <option value="BLOCKED">BLOCKED (Tạm khoá)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Tạo người dùng</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cập nhật người dùng (PUT /api/admin/users/{id}) -->
<div class="modal" id="editUserModal">
    <div class="modal-dialog" style="max-width:560px">
        <div class="modal-head">
            <span class="p-icon"><i class="fa-solid fa-user-pen"></i></span>
            <div style="flex:1">
                <h3>Cập nhật thông tin người dùng</h3>
                <p id="euSubtitle">PUT /api/admin/users/{id}</p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="admin_update_user">
            <input type="hidden" name="user_id" id="euId" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="euUsername">Tên đăng nhập (Username) *</label>
                        <input class="input" id="euUsername" name="username" required pattern="[A-Za-z0-9._]{3,50}">
                    </div>
                    <div class="field">
                        <label for="euEmail">Email *</label>
                        <input class="input" id="euEmail" name="email" type="email" required maxlength="100">
                    </div>
                    <div class="field">
                        <label for="euFullName">Họ và tên</label>
                        <input class="input" id="euFullName" name="fullName" maxlength="100">
                    </div>
                    <div class="field">
                        <label for="euPhone">Số điện thoại</label>
                        <input class="input" id="euPhone" name="phoneNumber" pattern="\+?[0-9]{9,15}">
                    </div>
                    <div class="field">
                        <label for="euRole">Vai trò (Role)</label>
                        <select class="select" id="euRole" name="role">
                            <option value="USER">USER (Thành viên)</option>
                            <option value="ADMIN">ADMIN (Quản trị viên)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="euStatus">Trạng thái (Status)</label>
                        <select class="select" id="euStatus" name="status">
                            <option value="ACTIVE">ACTIVE (Hoạt động)</option>
                            <option value="BLOCKED">BLOCKED (Khoá tài khoản)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Xem chi tiết JSON 1 người dùng (GET /api/admin/users/{id}) -->
<div class="modal" id="inspectUserModal">
    <div class="modal-dialog" style="max-width:520px">
        <div class="modal-head">
            <span class="p-icon blue"><i class="fa-solid fa-magnifying-glass"></i></span>
            <div style="flex:1">
                <h3>Chi tiết hồ sơ người dùng</h3>
                <p id="iuSubtitle">GET /api/admin/users/{id}</p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="iuBody">
            <div class="small t-muted">Đang tải dữ liệu từ Auth Service...</div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close>Đóng</button>
        </div>
    </div>
</div>

<!-- Modal Xác nhận Xoá người dùng (DELETE /api/admin/users/{id}) -->
<div class="modal" id="deleteUserModal">
    <div class="modal-dialog" style="max-width:480px">
        <div class="modal-head">
            <span class="p-icon red"><i class="fa-solid fa-user-xmark"></i></span>
            <div style="flex:1">
                <h3>Xác nhận xoá người dùng</h3>
                <p id="duSubtitle">DELETE /api/admin/users/{id}</p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="admin_delete_user">
            <input type="hidden" name="user_id" id="duId" value="">
            <div class="modal-body">
                <div class="del-bank-preview-card">
                    <div class="del-bank-preview-top">
                        <div class="del-bank-code-badge" id="duRoleBadge">USER</div>
                        <div class="del-bank-meta">
                            <strong id="duFullName">Người dùng</strong>
                            <span id="duUsername">@username</span>
                        </div>
                    </div>
                    <div class="del-bank-preview-foot">
                        <span><i class="fa-regular fa-envelope"></i> <strong id="duEmail">email</strong></span>
                        <span class="del-status-tag"><i class="fa-solid fa-triangle-exclamation"></i> Xoá vĩnh viễn</span>
                    </div>
                </div>
                <div class="alert alert-danger" style="margin-top:1rem;margin-bottom:0">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Hành động này sẽ xoá tài khoản khỏi cơ sở dữ liệu <code>auth_db</code> và thu hồi toàn bộ Refresh Token của người dùng này.</span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Giữ lại</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Xác nhận xoá</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Ẩn/hiện mật khẩu
    document.querySelectorAll('[data-toggle-pwd]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePwd);
            if (!input) return;
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) icon.className = isPwd ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        });
    });

    // Đo độ mạnh mật khẩu mới
    const newPwd = document.getElementById('newPwd');
    const strengthFill = document.getElementById('pwdStrengthFill');
    const strengthText = document.getElementById('pwdStrengthText');
    if (newPwd && strengthFill && strengthText) {
        newPwd.addEventListener('input', () => {
            const v = newPwd.value;
            let score = 0;
            if (v.length >= 6) score += 25;
            if (v.length >= 10) score += 25;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score += 25;
            if (/[0-9]/.test(v) || /[^A-Za-z0-9]/.test(v)) score += 25;
            strengthFill.style.width = score + '%';
            const labels = { 0: ['Chưa đủ 6 ký tự', '#94a3b8'], 25: ['Trung bình', '#f59e0b'], 50: ['Khá tốt', '#3b82f6'], 75: ['Mạnh', '#10b981'], 100: ['Rất mạnh', '#059669'] };
            const [lbl, clr] = labels[score] || labels[0];
            strengthText.textContent = v.length === 0 ? 'Chưa nhập' : lbl;
            strengthFill.style.background = clr;
        });
    }

    // Lọc nhanh bảng người dùng
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('userRoleFilter');
    const statusFilter = document.getElementById('userStatusFilter');
    const filterRows = () => {
        const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const r = roleFilter ? roleFilter.value : '';
        const s = statusFilter ? statusFilter.value : '';
        document.querySelectorAll('[data-user-row]').forEach(tr => {
            const matchQ = !q || (tr.dataset.search || '').includes(q);
            const matchR = !r || tr.dataset.role === r;
            const matchS = !s || tr.dataset.status === s;
            tr.style.display = (matchQ && matchR && matchS) ? '' : 'none';
        });
    };
    searchInput && searchInput.addEventListener('input', filterRows);
    roleFilter && roleFilter.addEventListener('change', filterRows);
    statusFilter && statusFilter.addEventListener('change', filterRows);

    // Sự kiện nút Sửa / Xem chi tiết / Xoá user
    document.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-user]');
        if (editBtn) {
            const uid = editBtn.dataset.editUser;
            document.getElementById('euId').value = uid;
            document.getElementById('euSubtitle').textContent = `PUT /api/admin/users/${uid}`;
            document.getElementById('euUsername').value = editBtn.dataset.username || '';
            document.getElementById('euEmail').value = editBtn.dataset.email || '';
            document.getElementById('euFullName').value = editBtn.dataset.fullname || '';
            document.getElementById('euPhone').value = editBtn.dataset.phone || '';
            document.getElementById('euRole').value = editBtn.dataset.role || 'USER';
            document.getElementById('euStatus').value = editBtn.dataset.status || 'ACTIVE';
            if (window.CF) window.CF.openModal('editUserModal');
            return;
        }

        const delBtn = e.target.closest('[data-del-user]');
        if (delBtn) {
            const uid = delBtn.dataset.delUser;
            document.getElementById('duId').value = uid;
            document.getElementById('duSubtitle').textContent = `DELETE /api/admin/users/${uid}`;
            document.getElementById('duRoleBadge').textContent = delBtn.dataset.delRole || 'USER';
            document.getElementById('duFullName').textContent = delBtn.dataset.delFullname || '';
            document.getElementById('duUsername').textContent = '@' + (delBtn.dataset.delUsername || '');
            document.getElementById('duEmail').textContent = delBtn.dataset.delEmail || '';
            if (window.CF) window.CF.openModal('deleteUserModal');
            return;
        }

        const inspectBtn = e.target.closest('[data-inspect-user]');
        if (inspectBtn) {
            const uid = inspectBtn.dataset.inspectUser;
            const bodyEl = document.getElementById('iuBody');
            document.getElementById('iuSubtitle').textContent = `GET /api/admin/users/${uid}`;
            bodyEl.innerHTML = '<div class="small t-muted">Đang gọi GET /api/admin/users/' + uid + '...</div>';
            if (window.CF) window.CF.openModal('inspectUserModal');
            try {
                const res = await fetch(`api.php?path=${encodeURIComponent('/api/admin/users/' + uid)}`);
                const data = await res.json();
                const u = data.user || {};
                bodyEl.innerHTML = `
                    <div class="kv" style="grid-template-columns:repeat(2,1fr);margin-bottom:0.85rem">
                        <div><span>ID</span><b>#${u.id ?? uid}</b></div>
                        <div><span>Username</span><b>@${CF.esc(u.username || '')}</b></div>
                        <div><span>Họ và tên</span><b>${CF.esc(u.fullName || '—')}</b></div>
                        <div><span>Số điện thoại</span><b>${CF.esc(u.phoneNumber || '—')}</b></div>
                        <div><span>Vai trò</span><b>${CF.esc(u.role || 'USER')}</b></div>
                        <div><span>Trạng thái</span><b>${CF.esc(u.status || 'ACTIVE')}</b></div>
                    </div>
                    <pre style="margin:0;padding:0.85rem;background:#0f172a;color:#e2e8f0;border-radius:12px;font-size:0.78rem;overflow:auto">${CF.esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (err) {
                bodyEl.innerHTML = `<div class="alert alert-danger">${CF.esc(err.message)}</div>`;
            }
        }
    });
});
</script>

<?php renderFooter(); ?>

