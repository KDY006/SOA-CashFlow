<?php
/**
 * Đăng nhập - Phong cách Apple & Stripe Luxury FinTech nguyên bản của TDTU iBanking
 * Tương thích 100% với CSS gốc 3654 dòng của user.
 */
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['access_token'])) {
    redirect('index.php');
}

$error = isset($_GET['expired']) ? 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.' : '';
$username = trim($_POST['username'] ?? 'admin');
$password = (string) ($_POST['password'] ?? 'password123');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        $res = api('POST', '/api/auth/login', ['username' => $username, 'password' => $password]);
        if ($res['ok'] && !empty($res['body']['accessToken'])) {
            session_regenerate_id(true);
            unset($_SESSION['_logged_out']);
            $_SESSION['access_token'] = $res['body']['accessToken'];
            $_SESSION['refresh_token'] = $res['body']['refreshToken'] ?? null;
            $_SESSION['user'] = $res['body']['user'] ?? ['username' => $username];
            flash('success', 'Chào mừng trở lại, ' . ($_SESSION['user']['fullName'] ?? $username) . '!');
            redirect('index.php');
        }
        $error = $res['status'] === 401
            ? 'Tên đăng nhập hoặc mật khẩu không chính xác.'
            : ($res['error'] ?? 'Không thể kết nối đến máy chủ xác thực.');
    }
}

$demoAccounts = [
    [
        'username' => 'admin',
        'password' => 'password123',
        'fullName' => 'System Administrator',
        'role'     => 'ADMIN',
        'balance'  => 50000000,
    ],
    [
        'username' => 'member1',
        'password' => 'password123',
        'fullName' => 'Nguyen Van A',
        'role'     => 'USER',
        'balance'  => 20000000,
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập · CashFlow iBanking</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body>
<div class="login-experience-wrapper">
    <!-- 1. Background Campus Photo & Animated Rising Bubbles -->
    <div class="login-campus-backdrop" aria-hidden="true">
        <div class="login-campus-image" style="background-image: url('tdtu-campus.jpg')"></div>
        <div class="login-campus-overlay"></div>
    </div>

    <!-- Animated Rising Glass Bubbles -->
    <div class="bubbles-container" aria-hidden="true">
        <?php for ($i = 1; $i <= 20; $i++): ?>
            <span class="bubble b-<?= $i ?>"></span>
        <?php endfor; ?>
    </div>

    <!-- 2. FULL-WIDTH FROSTED BLUE HEADER BAR -->
    <header class="apple-fullwidth-navbar">
        <div class="navbar-fullwidth-inner">
            <div class="navbar-brand-wrap">
                <div class="navbar-logo-badge">
                    <img src="tdtu-logo.png" alt="TDTU Logo" class="navbar-tdtu-logo">
                </div>
                <div class="navbar-brand-text">
                    <h1>CASHFLOW · TDTU iBANKING</h1>
                    <p>HỆ SINH THÁI QUẢN LÝ DÒNG TIỀN & TÀI CHÍNH · 6 MICROSERVICES</p>
                </div>
            </div>

            <div class="navbar-contact-pills">
                <span class="contact-item">
                    <i class="fa-solid fa-phone"></i> <span>Hotline: <strong>037 484 3823</strong></span>
                </span>
            </div>

            <div class="navbar-action-group">
                <button type="button" class="campus-view-toggle-btn" id="toggleCampusBtn" onclick="toggleCampus()" title="Bấm để ẩn các box và ngắm toàn cảnh trường">
                    <i class="fa-solid fa-eye" id="campusIcon"></i>
                    <span id="campusText">Toàn cảnh TDTU</span>
                </button>
            </div>
        </div>
    </header>

    <!-- 3. MAIN 2-COLUMN STAGE GLASSMORPHISM -->
    <main class="apple-main-stage" id="mainStage">
        <!-- CỘT TRÁI: THÔNG BÁO VÀ QUY TRÌNH 4 BƯỚC NỘP HỌC PHÍ & TÀI CHÍNH -->
        <section class="apple-info-card">
            <div class="info-announcement-header">
                <div class="semester-pill-badge">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Đồ án SOA Giữa kỳ · 6 Microservices Phân tán</span>
                </div>
                <h2 class="info-main-heading">
                    Cổng quản trị dòng tiền & tài sản thông minh
                </h2>
                <p class="info-sub-paragraph">
                    Hệ sinh thái kết nối thời gian thực giữa 6 microservices: xác thực bảo mật, ghi chép giao dịch, ngân sách tự động, phân tích dữ liệu và tích hợp ngân hàng.
                </p>
            </div>

            <!-- KHU VỰC THẺ THANH TOÁN SỐ CHUẨN THỰC TẾ (TDTU VISA SIGNATURE & NAPAS SMART CARD) -->
            <div class="cards-showcase-wrapper">
                <div class="cards-showcase-header">
                    <div class="cards-showcase-badge">
                        <i class="fa-solid fa-credit-card"></i>
                        <span>Thẻ iBanking & Smart Spending Liên Kết</span>
                    </div>
                    <div class="card-switch-pills">
                        <button type="button" class="card-pill-btn active" id="btnVisa" onclick="switchCard('visa')">
                            Thẻ Visa Signature
                        </button>
                        <button type="button" class="card-pill-btn" id="btnNapas" onclick="switchCard('napas')">
                            Thẻ Sinh Viên Napas
                        </button>
                    </div>
                </div>

                <div class="realistic-card-stage">
                    <div class="realistic-bank-card card-theme-visa" id="bankCard">
                        <div class="card-specular-glint"></div>
                        <div class="real-card-top">
                            <div class="card-bank-header-group">
                                <img src="tdtu-logo.png" alt="TDTU Logo" class="card-tdtu-mini-logo">
                                <div>
                                    <div class="card-bank-title" id="cardBankTitle">CashFlow iBanking</div>
                                    <div class="card-sub-brand" id="cardSubBrand">DEBIT PLATINUM</div>
                                </div>
                            </div>
                            <span class="card-tier-pill" id="cardTierPill">Signature</span>
                        </div>

                        <div class="real-card-hardware-row">
                            <div class="real-emv-chip" title="EMV Smart Security Chip">
                                <div class="emv-pad"></div><div class="emv-pad"></div>
                                <div class="emv-pad"></div><div class="emv-pad"></div>
                                <div class="emv-pad"></div><div class="emv-pad"></div>
                                <div class="emv-chip-groove"></div>
                            </div>
                            <i class="fa-solid fa-wifi contactless-wave-svg" style="font-size: 1.3rem"></i>
                        </div>

                        <div class="real-card-number" id="cardNum">4111 88•• •••• 9988</div>

                        <div class="real-card-bottom">
                            <div>
                                <div class="card-label-caption">CHỦ THẺ / CARDHOLDER</div>
                                <div class="card-embossed-name" id="cardHolderName">ADMINISTRATOR</div>
                            </div>
                            <div style="text-align: center">
                                <div class="card-label-caption">VALID THRU</div>
                                <div class="card-expiry-val">12/30</div>
                            </div>
                            <div>
                                <div id="cardNetworkBadge" style="font-size: 1.4rem; font-weight: 900; font-style: italic; color: #fff; letter-spacing: 0.05em">
                                    VISA
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUY TRÌNH 4 BƯỚC QUẢN TRỊ TÀI CHÍNH -->
            <div>
                <h3 class="workflow-heading">Quy trình 4 trụ cột quản lý tài chính:</h3>
                <div class="workflow-grid-4">
                    <div class="workflow-step-box">
                        <div class="step-circle-badge">1</div>
                        <div class="step-text-wrap">
                            <h4>Thu chi & Giao dịch</h4>
                            <p>Ghi chép nhanh, lọc theo thời gian và tự động phân loại danh mục.</p>
                        </div>
                    </div>
                    <div class="workflow-step-box">
                        <div class="step-circle-badge">2</div>
                        <div class="step-text-wrap">
                            <h4>Ngân sách thông minh</h4>
                            <p>Cảnh báo tức thời qua SSE khi chạm 80% hoặc vượt hạn mức.</p>
                        </div>
                    </div>
                    <div class="workflow-step-box">
                        <div class="step-circle-badge">3</div>
                        <div class="step-text-wrap">
                            <h4>Tài sản & Khoản vay</h4>
                            <p>Tài sản ròng, khấu hao định kỳ và theo dõi lịch trả nợ vay.</p>
                        </div>
                    </div>
                    <div class="workflow-step-box">
                        <div class="step-circle-badge">4</div>
                        <div class="step-text-wrap">
                            <h4>Phân tích & Dự báo</h4>
                            <p>Biểu đồ xu hướng 6 tháng, phát hiện chi tiêu bất thường tự động.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CỘT PHẢI: FORM ĐĂNG NHẬP APPLE GLASSMORPHISM -->
        <section class="apple-login-card">
            <div class="login-title-row">
                <h2 class="login-heading">Đăng nhập iBanking</h2>
                <p class="login-subhead">Vui lòng nhập tài khoản để truy cập bảng điều khiển tài chính</p>
            </div>

            <form method="post" autocomplete="on" id="loginForm">
                <?php if ($error): ?>
                    <div class="alert-box alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php foreach (takeFlashes() as $f): ?>
                    <div class="alert-box alert-<?= e($f['type']) ?>">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= e($f['message']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="form-group">
                    <label class="form-label" for="username">Tên đăng nhập / Mã khách hàng</label>
                    <div class="input-container">
                        <input type="text" class="apple-input" id="username" name="username" value="<?= e($username) ?>" placeholder="Ví dụ: admin hoặc member1" required autofocus>
                        <i class="fa-solid fa-user input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mật khẩu truy cập</label>
                    <div class="input-container">
                        <input type="password" class="apple-input has-toggle" id="password" name="password" value="<?= e($password) ?>" placeholder="••••••••" autocomplete="current-password" required>
                        <i class="fa-solid fa-lock input-icon-left"></i>
                        <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Hiện mật khẩu" title="Hiện mật khẩu">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-apple-primary" id="submitBtn">
                    <span>Đăng nhập hệ thống</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted)">
                Chưa có tài khoản? <a href="register.php" style="color: var(--stripe-bright); font-weight: 700">Đăng ký thành viên mới</a>
            </p>

            <!-- KHU VỰC TÀI KHOẢN MẪU KIỂM THỬ (APPLE WALLET CARDS) -->
            <div class="test-accounts-section">
                <div class="test-accounts-head">
                    <span class="test-title-text">
                        <i class="fa-solid fa-flask" style="color: var(--stripe-bright); margin-right: 0.35rem"></i>
                        Dữ liệu mẫu kiểm thử (ĐỒ ÁN SOA):
                    </span>
                    <span class="test-action-tip">Bấm để tự điền</span>
                </div>

                <div class="test-accounts-list">
                    <?php foreach ($demoAccounts as $acc):
                        $isSelected = ($username === $acc['username']);
                    ?>
                        <div class="wallet-user-card <?= $isSelected ? 'active-selected' : '' ?>"
                             onclick="quickFill(this, '<?= e($acc['username']) ?>', '<?= e($acc['password']) ?>', '<?= e($acc['fullName']) ?>')"
                             title="Bấm để đăng nhập bằng tài khoản <?= e($acc['fullName']) ?>">
                            <div class="wallet-user-left">
                                <div class="wallet-avatar-bubble">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div>
                                    <div class="wallet-user-name"><?= e($acc['fullName']) ?></div>
                                    <div class="wallet-user-creds"><?= e($acc['username']) ?> • pass: <?= e($acc['password']) ?></div>
                                </div>
                            </div>
                            <span class="wallet-badge-pill <?= $acc['role'] === 'ADMIN' ? 'pill-violet' : 'pill-green' ?>">
                                <?= $acc['role'] === 'ADMIN' ? 'Quản trị viên' : 'Thành viên' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- 4. CHÂN TRANG FOOTER -->
    <footer class="apple-bank-footer">
        <div class="apple-footer-inner">
            <p>
                Hotline: <strong>037 484 3823</strong> • Email: <strong>phongtaichinh@tdtu.edu.vn</strong> • © 2026 made by SOA team (TDTU)
            </p>
            <p>Đồ án môn Kiến trúc Hướng dịch vụ (SOA / Microservices) • Đại học Tôn Đức Thắng</p>
        </div>
    </footer>
</div>

<script>
    var showFull = false;
    function toggleCampus() {
        showFull = !showFull;
        const stage = document.getElementById('mainStage');
        const icon = document.getElementById('campusIcon');
        const txt = document.getElementById('campusText');
        const btn = document.getElementById('toggleCampusBtn');
        if (showFull) {
            stage.classList.add('stage-hidden');
            btn.classList.add('active');
            icon.className = 'fa-solid fa-eye-slash';
            txt.textContent = 'Hiện form';
        } else {
            stage.classList.remove('stage-hidden');
            btn.classList.remove('active');
            icon.className = 'fa-solid fa-eye';
            txt.textContent = 'Toàn cảnh TDTU';
        }
    }

    function switchCard(type) {
        const card = document.getElementById('bankCard');
        const btnV = document.getElementById('btnVisa');
        const btnN = document.getElementById('btnNapas');
        const sub = document.getElementById('cardSubBrand');
        const tier = document.getElementById('cardTierPill');
        const num = document.getElementById('cardNum');
        const badge = document.getElementById('cardNetworkBadge');

        if (type === 'visa') {
            card.className = 'realistic-bank-card card-theme-visa';
            btnV.classList.add('active');
            btnN.classList.remove('active');
            sub.textContent = 'DEBIT PLATINUM';
            tier.textContent = 'Signature';
            num.textContent = '4111 88•• •••• 9988';
            badge.textContent = 'VISA';
        } else {
            card.className = 'realistic-bank-card card-theme-napas';
            btnN.classList.add('active');
            btnV.classList.remove('active');
            sub.textContent = 'SMART STUDENT ID';
            tier.textContent = 'Thẻ Sinh Viên';
            num.textContent = '9704 22•• •••• 0888';
            badge.innerHTML = '<span style="color:#0066b3">na</span><span style="color:#f37021">pas</span>';
        }
    }

    function quickFill(el, u, p, name) {
        document.getElementById('username').value = u;
        document.getElementById('password').value = p;
        document.getElementById('cardHolderName').textContent = name.toUpperCase();
        document.querySelectorAll('.wallet-user-card').forEach(c => c.classList.remove('active-selected'));
        if (el) el.classList.add('active-selected');
    }

    document.getElementById('username').addEventListener('input', e => {
        const v = e.target.value.trim();
        document.getElementById('cardHolderName').textContent = (v || 'CASHFLOW USER').toUpperCase();
    });

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
