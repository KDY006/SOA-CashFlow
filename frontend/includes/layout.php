<?php
/**
 * Layout dùng chung - Phong cách Apple/Stripe FinTech Luxury nguyên bản của user.
 * Tương thích 100% với CSS gốc và các trang con.
 */
require_once __DIR__ . '/config.php';

const NAV_ITEMS = [
    'dashboard'    => ['index.php',        'fa-gauge-high',       'Tổng quan'],
    'transactions' => ['transactions.php', 'fa-receipt',          'Thu chi'],
    'budgets'      => ['budgets.php',      'fa-bullseye',         'Ngân sách'],
    'assets'       => ['assets.php',       'fa-vault',            'Tài sản & Vay'],
    'analytics'    => ['analytics.php',    'fa-chart-line',       'Phân tích'],
    'banking'      => ['banking.php',      'fa-building-columns', 'Ngân hàng'],
];

function renderHead(string $title, string $bodyClass = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> · CashFlow iBanking</title>
    <meta name="description" content="CashFlow - Hệ sinh thái quản lý tài chính và dòng tiền cá nhân chuyên nghiệp trên nền tảng 6 microservices.">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
    <script src="assets/vendor/chart.umd.min.js"></script>
</head>
<body class="<?= e($bodyClass) ?>">
    <?php
}

function renderHeader(string $title, string $active): void
{
    renderHead($title);
    $user = currentUser();
    $name = $user['fullName'] ?? $user['username'] ?? 'Người dùng';

    // Badge chuông: số thông báo chưa đọc (cache 30s, SSE cập nhật realtime)
    $unread = unreadCount();
    ?>
<!-- Ambient stage background glows -->
<div class="stage-ambient-glow-wrap" aria-hidden="true">
    <div class="stage-glow-orb stage-glow-left"></div>
    <div class="stage-glow-orb stage-glow-right"></div>
    <div class="stage-dot-pattern"></div>
</div>

<header class="navbar-container" id="appNavbar">
    <div class="nav-progress" id="navProgress" aria-hidden="true"></div>
    <div class="navbar-wrapper">
        <a href="index.php" class="navbar-brand">
            <span class="navbar-brand-logo-wrap">
                <img src="tdtu-logo.png" alt="TDTU Logo" class="navbar-brand-logo">
            </span>
            <div class="navbar-brand-text">
                <span class="navbar-brand-title">CashFlow <span class="brand-accent">iBanking</span></span>
                <span class="navbar-brand-subtitle">SOA 6 MICROSERVICES</span>
            </div>
        </a>

        <nav class="navbar-nav" id="mainNav" aria-label="Điều hướng chính">
            <span class="nav-indicator" aria-hidden="true"></span>
            <?php $i = 0; foreach (NAV_ITEMS as $key => [$href, $icon, $label]): ?>
                <a href="<?= $href ?>" class="nav-item <?= $key === $active ? 'active' : '' ?>" style="--i:<?= $i++ ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="navbar-actions">
            <!-- Notifications -->
            <a href="notifications.php" class="icon-action-btn bell-btn <?= $active === 'notifications' ? 'active' : '' ?> <?= $unread ? 'has-unread' : '' ?>" title="Thông báo" aria-label="Thông báo<?= $unread ? " ($unread chưa đọc)" : '' ?>">
                <i class="fa-solid fa-bell"></i>
                <span class="badge-dot" id="notifBadge" <?= $unread ? '' : 'hidden' ?>><?= $unread > 99 ? '99+' : $unread ?></span>
            </a>

            <!-- User Menu -->
            <div class="user-profile-menu">
                <div class="nav-avatar" aria-hidden="true">
                    <span class="nav-avatar-inner"><?= e(initials($name)) ?></span>
                    <span class="nav-avatar-status"></span>
                </div>
                <div class="user-info">
                    <span class="user-name" title="<?= e($name) ?>"><?= e($name) ?></span>
                    <span class="user-role-badge">
                        <?= strtoupper($user['role'] ?? '') === 'ADMIN' ? 'Quản trị viên' : 'Thành viên' ?>
                    </span>
                </div>
                <a href="logout.php" class="logout-btn" title="Đăng xuất khỏi hệ thống" aria-label="Đăng xuất">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>

            <button type="button" class="nav-burger" id="navBurger" aria-label="Mở menu" aria-expanded="false" aria-controls="mobileNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- Mobile drawer -->
    <nav class="mobile-nav" id="mobileNav" aria-label="Menu di động">
        <?php $i = 0; foreach (NAV_ITEMS as $key => [$href, $icon, $label]): ?>
            <a href="<?= $href ?>" class="mobile-nav-item <?= $key === $active ? 'active' : '' ?>" style="--i:<?= $i++ ?>">
                <span class="mobile-nav-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                <span><?= $label ?></span>
                <i class="fa-solid fa-chevron-right mobile-nav-arrow"></i>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<div class="mobile-nav-backdrop" id="mobileNavBackdrop" aria-hidden="true"></div>

<main class="page-container">
    <?php foreach (takeFlashes() as $f): ?>
        <div class="alert-box alert-<?= e($f['type']) ?>" data-autohide>
            <i class="fa-solid <?= $f['type'] === 'success' ? 'fa-circle-check' : ($f['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation') ?>"></i>
            <span><?= e($f['message']) ?></span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer">&times;</button>
        </div>
    <?php endforeach;
}

function renderApiErrors(array $errors): void
{
    if (!$errors) return;
    ?>
    <div class="alert-box alert-danger">
        <i class="fa-solid fa-plug-circle-xmark"></i>
        <span><strong>Trạng thái kết nối microservice:</strong> <?= e(implode(' · ', $errors)) ?></span>
    </div>
    <?php
}

function renderFooter(): void
{
    ?>
</main>

<footer class="apple-bank-footer">
    <div class="apple-footer-inner">
        <p>
            Hotline: <strong>037 484 3823</strong> • Email: <strong>phongtaichinh@tdtu.edu.vn</strong> • © 2026 made by SOA team (TDTU)
        </p>
        <p>Đồ án môn Kiến trúc Hướng dịch vụ (SOA / Microservices) • Đại học Tôn Đức Thắng</p>
    </div>
</footer>

<div id="toastStack" class="toast-stack" aria-live="polite"></div>

<script>
    window.CASHFLOW_CSRF = <?= json_encode(csrfToken()) ?>;
    window.CASHFLOW = {
        sseUrl: <?= json_encode(NOTIFICATION_SSE_URL . '/api/notifications/stream?user_id=' . uid()) ?>
    };
</script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="assets/js/navbar.js?v=<?= filemtime(__DIR__ . '/../assets/js/navbar.js') ?>"></script>
</body>
</html>
    <?php
}

/** Tiêu đề trang dạng Royal Sapphire Luxury FinTech Hero Banner */
function pageHero(string $pill, string $title, string $sub, string $rightHtml = ''): void
{
    ?>
    <section class="luxury-hero-banner anim-fade-in">
        <div class="hero-ambient-mesh" aria-hidden="true"></div>
        <div class="luxury-hero-inner">
            <div class="luxury-hero-text">
                <div class="luxury-hero-pill">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span><?= e($pill) ?></span>
                </div>
                <h1 class="luxury-hero-title"><?= $title ?></h1>
                <div class="luxury-hero-sub"><?= $sub ?></div>
            </div>
            <?php if ($rightHtml): ?>
                <div class="luxury-hero-widget-slot anim-float"><?= $rightHtml ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/** Render thẻ vật lý Realistic FinTech Bank Card với Specular Glint Sheen Animation 7s */
function renderHeroBankCard(
    string $tier = 'Signature',
    string $subBrand = 'SMART ASSET VAULT',
    string $cardNum = '9704 •••• •••• 8888',
    string $holderName = 'ADMINISTRATOR',
    string $stat1Label = 'TỔNG TÀI SẢN',
    string $stat1Val = '',
    string $stat2Label = 'TÀI SẢN RÒNG',
    string $stat2Val = '',
    string $network = 'VISA',
    string $theme = 'visa'
): string {
    ob_start(); ?>
    <div class="realistic-card-stage">
        <div class="realistic-bank-card card-theme-<?= e($theme) ?>">
            <!-- Vệt sáng phản quang Specular Sheen Animation 7s -->
            <div class="card-specular-glint"></div>

            <!-- Top Row: Logo + Bank Title + Tier Pill & Metric -->
            <div class="real-card-top">
                <div class="card-bank-header-group">
                    <img src="tdtu-logo.png" alt="TDTU Logo" class="card-tdtu-mini-logo">
                    <div>
                        <div class="card-bank-title">CashFlow iBanking</div>
                        <div class="card-sub-brand"><?= e($subBrand) ?></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.45rem">
                    <?php if ($stat1Val): ?>
                        <span class="card-stat-pill" title="<?= e($stat1Label) ?>"><?= e($stat1Val) ?></span>
                    <?php endif; ?>
                    <span class="card-tier-pill"><?= e($tier) ?></span>
                </div>
            </div>

            <!-- Hardware Row: EMV Gold Smart Chip 6-pad + Wave Contactless -->
            <div class="real-card-hardware-row">
                <div class="real-emv-chip" title="EMV Smart Security Chip">
                    <div class="emv-pad"></div><div class="emv-pad"></div>
                    <div class="emv-pad"></div><div class="emv-pad"></div>
                    <div class="emv-pad"></div><div class="emv-pad"></div>
                    <div class="emv-chip-groove"></div>
                </div>
                <i class="fa-solid fa-wifi contactless-wave-svg" title="NFC Contactless"></i>
            </div>

            <!-- 16-Digit Embossed Bank Card Number -->
            <div class="real-card-number"><?= e($cardNum) ?></div>

            <!-- Bottom Row: Cardholder, Values, Brand Network -->
            <div class="real-card-bottom">
                <div style="min-width: 0; flex: 1">
                    <div class="card-label-caption">CHỦ THẺ / CARDHOLDER</div>
                    <div class="card-embossed-name" title="<?= e($holderName) ?>"><?= e($holderName) ?></div>
                </div>
                <?php if ($stat2Val): ?>
                    <div style="text-align: right; padding: 0 0.5rem">
                        <div class="card-label-caption"><?= e($stat2Label) ?></div>
                        <div class="card-expiry-val"><?= e($stat2Val) ?></div>
                    </div>
                <?php endif; ?>
                <div>
                    <?php if (strtoupper($network) === 'VISA'): ?>
                        <div class="card-network-badge-visa">VISA</div>
                    <?php else: ?>
                        <div class="napas-brand-badge">NAPAS</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function emptyState(string $icon, string $title, string $sub = ''): string
{
    return '<div class="empty" style="padding: 3rem 1.5rem; text-align: center; color: #94a3b8"><i class="fa-solid ' . $icon . '" style="font-size: 2.2rem; margin-bottom: 0.5rem; color: #cbd5e1"></i><div style="font-weight:700;color:#334155;font-size:0.95rem">' . e($title) . '</div>'
        . ($sub ? '<div style="font-size:0.82rem;margin-top:0.25rem">' . e($sub) . '</div>' : '') . '</div>';
}

/** Một dòng giao dịch dạng list (dashboard) */
function txRow(array $t): string
{
    $type = strtoupper($t['type'] ?? 'EXPENSE');
    $cat = $t['category'] ?? $t['category_name_rel'] ?? 'Khác';
    $isIncome = $type === 'INCOME';
    $desc = trim((string) ($t['description'] ?? '')) ?: $cat;
    return '<div class="tx-row">'
        . '<span class="cat-icon" style="--c:' . catColor($cat) . '"><i class="' . catIcon($t['category_icon'] ?? null, $type) . '"></i></span>'
        . '<div class="tx-main"><strong>' . e($desc) . '</strong><span>' . e($cat) . ' · ' . dateVN($t['transaction_date'] ?? null, true) . '</span></div>'
        . '<div class="tx-amount"><span class="money ' . ($isIncome ? 't-green' : 't-red') . '">' . money(($isIncome ? 1 : -1) * abs((float) ($t['amount'] ?? 0)), true) . '</span></div>'
        . '</div>';
}

/** Danh sách các danh mục */
function loadCategories(): array
{
    $c = $_SESSION['_cats'] ?? null;
    if ($c && $c['at'] > time() - 300) {
        return $c['data'];
    }
    $data = apiData(api('GET', '/api/categories'), []);
    if ($data) {
        $_SESSION['_cats'] = ['at' => time(), 'data' => $data];
    }
    return $data;
}
