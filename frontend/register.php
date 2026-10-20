<?php
/**
 * Đăng ký - POST /api/auth/register (Auth Service)
 * Giao diện chuẩn Stripe/Mercury, chuyên nghiệp và đồng bộ với login.php.
 */
require_once __DIR__ . '/includes/layout.php';

if (!empty($_SESSION['access_token'])) {
    redirect('index.php');
}

$old = [
    'fullName'    => trim($_POST['fullName'] ?? ''),
    'username'    => trim($_POST['username'] ?? ''),
    'email'       => trim($_POST['email'] ?? ''),
    'phoneNumber' => trim($_POST['phoneNumber'] ?? ''),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($old['username'] === '' || $old['email'] === '' || $password === '') {
        $error = 'Vui lòng điền đầy đủ tên đăng nhập, email và mật khẩu.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu cần tối thiểu 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        $res = api('POST', '/api/auth/register', $old + ['password' => $password]);
        if ($res['ok']) {
            flash('success', 'Tạo tài khoản thành công! Hãy đăng nhập để bắt đầu.');
            $_POST = ['username' => $old['username']];
            redirect('login.php');
        }
        $error = $res['status'] === 409 ? 'Tên đăng nhập hoặc email đã được sử dụng.' : ($res['error'] ?? 'Đăng ký không thành công.');
    }
}

renderHead('Tạo tài khoản', 'auth-body');
include __DIR__ . '/includes/auth_backdrop.php';
?>
<main class="auth-stage">
    <!-- Cột trái: Giới thiệu -->
    <section class="auth-hero">
        <div class="auth-tag">
            <i class="fa-solid fa-user-plus"></i> Thành viên Mới · Khởi tạo Miễn phí
        </div>

        <h1 class="auth-title">
            Bắt đầu kiểm soát<br>
            tài chính <span>thông minh.</span>
        </h1>

        <p class="auth-desc">
            Thiết lập tài khoản tập trung để đồng bộ số dư, tự động hoá ngân sách và theo dõi dòng tiền minh bạch trên mọi microservices.
        </p>

        <div class="auth-props">
            <div class="auth-prop">
                <div class="auth-prop-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="auth-prop-content">
                    <strong>Bảo mật PBKDF2 & JWT</strong>
                    <p>Mật khẩu được băm 210.000 vòng với muối bảo vệ, phiên làm việc chuẩn JWT.</p>
                </div>
            </div>

            <div class="auth-prop">
                <div class="auth-prop-icon"><i class="fa-solid fa-bell"></i></div>
                <div class="auth-prop-content">
                    <strong>Cảnh báo ngân sách</strong>
                    <p>Nhận thông báo trực tiếp qua Server-Sent Events khi chi tiêu chạm ngưỡng 80%.</p>
                </div>
            </div>

            <div class="auth-prop">
                <div class="auth-prop-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="auth-prop-content">
                    <strong>Tích hợp ngân hàng</strong>
                    <p>Đồng bộ tài khoản thanh toán và mô phỏng giao dịch biến động tức thời.</p>
                </div>
            </div>

            <div class="auth-prop">
                <div class="auth-prop-icon"><i class="fa-solid fa-coins"></i></div>
                <div class="auth-prop-content">
                    <strong>Tỷ giá ngoại tệ</strong>
                    <p>Cập nhật tỷ giá trực tiếp, hỗ trợ chuyển đổi đa tiền tệ nhanh chóng.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Cột phải: Form đăng ký -->
    <section class="auth-box">
        <div class="auth-box-head">
            <h2>Đăng ký tài khoản</h2>
            <p>Điền thông tin để tạo mới hồ sơ quản trị tài chính</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label for="fullName">Họ và tên</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-id-card"></i>
                    <input class="input" id="fullName" name="fullName" value="<?= e($old['fullName']) ?>" placeholder="Nguyễn Văn A">
                </div>
            </div>

            <div class="input-group" style="margin-bottom: 0">
                <div class="field" style="flex: 1">
                    <label for="username">Tên đăng nhập *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input class="input" id="username" name="username" value="<?= e($old['username']) ?>" placeholder="nguyenvana" required>
                    </div>
                </div>
                <div class="field" style="flex: 1">
                    <label for="phoneNumber">Số điện thoại</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone"></i>
                        <input class="input" id="phoneNumber" name="phoneNumber" value="<?= e($old['phoneNumber']) ?>" placeholder="0901234567">
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="email">Địa chỉ Email *</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="ban@email.com" required>
                </div>
            </div>

            <div class="input-group" style="margin-bottom: 0">
                <div class="field" style="flex: 1">
                    <label for="password">Mật khẩu *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input class="input has-toggle" type="password" id="password" name="password" minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" required>
                        <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <div class="field" style="flex: 1">
                    <label for="confirm">Xác nhận mật khẩu *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input class="input has-toggle" type="password" id="confirm" name="confirm" minlength="6" placeholder="••••••••" autocomplete="new-password" required>
                        <button type="button" class="pw-toggle" data-pw-toggle="confirm" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-blue btn-block" style="padding: 0.75rem 1rem; font-size: 0.92rem; margin-top: 0.5rem">
                <span>Hoàn tất đăng ký</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <p style="font-size: 0.82rem; color: var(--slate-400); text-align: center; margin-top: 0.25rem">
            Đã có tài khoản? <a href="login.php" style="color: #60a5fa; font-weight: 600">Đăng nhập ngay</a>
        </p>
    </section>
</main>
<script>
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-pw-toggle]');
        if (!btn) return;
        e.preventDefault();
        const input = document.getElementById(btn.dataset.pwToggle);
        if (!input) return;
        const show = input.type === 'password';
        const start = input.selectionStart, end = input.selectionEnd;
        const icon = btn.querySelector('i');
        btn.classList.add('is-swapping');
        setTimeout(() => {
            input.type = show ? 'text' : 'password';
            icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            const label = show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            btn.classList.toggle('is-visible', show);
            btn.classList.remove('is-swapping');
            input.focus();
            try { input.setSelectionRange(start, end); } catch (_) {}
        }, 120);
    });
</script>
</body>
</html>
