<?php
/**
 * Tổng quan - tổng hợp dữ liệu từ nhiều service qua Gateway:
 *  Asset (net worth) · Analytics (summary, trends, breakdown) · Transaction (giao dịch, ngân sách) · Integration (tỷ giá)
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();
$from = date('Y-m-01');
$to = date('Y-m-d');
$range = "from_date=$from&to_date=$to&user_id=$uid";

// Gọi song song 8 API -> thời gian = request chậm nhất thay vì cộng dồn
[
    'net' => $rNet, 'summary' => $rSummary, 'trends' => $rTrends, 'break' => $rBreak,
    'tx' => $rTx, 'budget' => $rBudget, 'rates' => $rRates, 'acc' => $rAcc,
] = apiAll([
    'net'     => ['GET', "/api/assets/net-worth?user_id=$uid"],
    'summary' => ['GET', "/api/analytics/summary?$range"],
    'trends'  => ['GET', "/api/analytics/trends?months=6&user_id=$uid"],
    'break'   => ['GET', "/api/analytics/category-breakdown?$range"],
    'tx'      => ['GET', "/api/transactions?limit=6&user_id=$uid"],
    'budget'  => ['GET', "/api/budgets/progress?user_id=$uid"],
    'rates'   => ['GET', '/api/integrations/rates'],
    'acc'     => ['GET', '/api/integrations/accounts'],
]);

$netWorth  = (float) (apiData($rNet, [])['total_net_worth'] ?? 0);
$summary   = apiData($rSummary, []);
$income    = (float) ($summary['income'] ?? 0);
$expense   = (float) ($summary['expense'] ?? 0);
$net       = (float) ($summary['net_cashflow'] ?? ($income - $expense));
$saveRate  = $income > 0 ? round($net / $income * 100, 1) : 0;
$trends    = apiData($rTrends, []);
$history   = $trends['history'] ?? [];
$forecast  = $trends['forecast']['next_month_net_cashflow'] ?? null;
$anomalies = $trends['anomalies'] ?? [];
$breakdown = apiData($rBreak, []);
$recent    = apiData($rTx, []);
$budgets   = apiData($rBudget, []);
$rates     = array_column(apiData($rRates, []), null, 'currency_code');
$accounts  = apiData($rAcc, []);
$curBal    = array_sum(array_map(fn($a) => (float) ($a['balance'] ?? 0), $accounts));

$errors = collectErrors($rNet, $rSummary, $rTrends, $rTx, $rBudget, $rRates, $rAcc);
$categories = loadCategories();
$user = currentUser();
$firstName = trim(explode(' ', trim($user['fullName'] ?? $user['username'] ?? ''))[0] ?? '') ?: 'bạn';
$hour = (int) date('G');
$greet = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));

renderHeader('Tổng quan', 'dashboard');
renderApiErrors($errors);

$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    'Signature',
    'DEBIT PLATINUM',
    '9704 22•• •••• ' . (substr((string)$uid, -4) ?: '0888'),
    $holder,
    'SỐ DƯ GIAO DỊCH',
    shortVnd($curBal),
    'TÀI SẢN RÒNG',
    vnd($netWorth),
    'VISA',
    'visa'
);

$heroDesc = "Bạn đã có <strong>" . (int) ($summary['transaction_count'] ?? 0) . "</strong> giao dịch trong tháng " . date('m/Y') . ". "
    . ($net >= 0 ? "Dòng tiền đang dương, đạt mục tiêu tích lũy lý tưởng." : "Chi tiêu đang vượt thu nhập, hãy tối ưu hóa các khoản ngân sách.")
    . '<div class="luxury-hero-actions">'
    . '<button class="btn-hero-pri" data-open="txModal"><i class="fa-solid fa-plus"></i> Thêm giao dịch</button>'
    . '<a class="btn-hero-sec" href="budgets.php"><i class="fa-solid fa-bullseye"></i> Đặt ngân sách</a>'
    . '<a class="btn-hero-sec" href="banking.php"><i class="fa-solid fa-qrcode"></i> Quét VietQR</a>'
    . '</div>';

pageHero(
    'Tháng ' . date('m/Y') . ' · Đồng bộ Live Gateway',
    e("$greet, $firstName!"),
    $heroDesc,
    $cardWidget
);
?>

<!-- Quick Shortcuts -->
<div class="quick-actions-bar anim-fade-in anim-delay-1">
    <a href="#" class="action-tile-btn" data-open="txModal">
        <div class="action-tile-icon" style="background: #eff6ff; color: #0066cc"><i class="fa-solid fa-plus"></i></div>
        <div class="action-tile-text"><strong>Ghi chép thu chi</strong><span>Giao dịch tức thì</span></div>
    </a>
    <a href="transactions.php" class="action-tile-btn">
        <div class="action-tile-icon" style="background: #ecfdf5; color: #059669"><i class="fa-solid fa-receipt"></i></div>
        <div class="action-tile-text"><strong>Lịch sử đối soát</strong><span>Tra cứu hóa đơn</span></div>
    </a>
    <a href="budgets.php" class="action-tile-btn">
        <div class="action-tile-icon" style="background: #fffbeb; color: #d97706"><i class="fa-solid fa-bullseye"></i></div>
        <div class="action-tile-text"><strong>Ngân sách hạn mức</strong><span>Cảnh báo bội chi</span></div>
    </a>
    <a href="assets.php" class="action-tile-btn">
        <div class="action-tile-icon" style="background: #f5f3ff; color: #7c3aed"><i class="fa-solid fa-vault"></i></div>
        <div class="action-tile-text"><strong>Quản lý tài sản</strong><span>Tính tài sản ròng</span></div>
    </a>
    <a href="banking.php" class="action-tile-btn">
        <div class="action-tile-icon" style="background: #f0fdfa; color: #0d9488"><i class="fa-solid fa-building-columns"></i></div>
        <div class="action-tile-text"><strong>Tích hợp ngân hàng</strong><span>VietQR & Tỷ giá</span></div>
    </a>
</div>

<!-- KPI -->
<section class="grid grid-4 mb">
    <div class="stat green anim-fade-in anim-delay-1">
        <div class="stat-top"><span class="stat-label">Tổng thu nhập tháng</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span></div>
        <div class="stat-value t-green"><?= money($income) ?></div>
        <div class="stat-foot"><i class="fa-regular fa-calendar" style="color:#059669"></i> Từ <?= dateVN($from) ?> đến nay</div>
    </div>
    <div class="stat red anim-fade-in anim-delay-2">
        <div class="stat-top"><span class="stat-label">Tổng chi tiêu tháng</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-down"></i></span></div>
        <div class="stat-value t-red"><?= money($expense) ?></div>
        <div class="stat-foot"><span class="pill red"><?= count($breakdown) ?> danh mục</span> phát sinh chi phí</div>
    </div>
    <div class="stat blue anim-fade-in anim-delay-3">
        <div class="stat-top"><span class="stat-label">Dòng tiền thuần (Net)</span><span class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></span></div>
        <div class="stat-value <?= $net >= 0 ? 't-blue' : 't-red' ?>"><?= money($net, true) ?></div>
        <div class="stat-foot">
            <?php if ($forecast !== null): ?>
                Dự báo AI tháng tới: <b class="money <?= $forecast >= 0 ? 't-green' : 't-red' ?>"><?= shortVnd($forecast) ?></b>
            <?php else: ?>Thu nhập − chi tiêu<?php endif; ?>
        </div>
    </div>
    <div class="stat amber anim-fade-in anim-delay-4">
        <div class="stat-top"><span class="stat-label">Tỷ lệ tiết kiệm</span><span class="stat-icon"><i class="fa-solid fa-piggy-bank"></i></span></div>
        <div class="stat-value"><?= pct($saveRate) ?></div>
        <div class="stat-foot">
            <span class="pill <?= $saveRate >= 20 ? 'green' : ($saveRate >= 0 ? 'amber' : 'red') ?>">
                <?= $saveRate >= 20 ? 'Rất tốt' : ($saveRate >= 0 ? 'Cần cải thiện' : 'Bội chi') ?>
            </span> mục tiêu ≥ 20%
        </div>
    </div>
</section>

<!-- Charts -->
<section class="grid grid-main mb">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon"><i class="fa-solid fa-chart-column"></i></span>
                <div><h2>Dòng tiền 6 tháng</h2><p>Thu nhập, chi tiêu và chênh lệch theo tháng</p></div>
            </div>
            <a href="analytics.php" class="panel-link">Phân tích chi tiết →</a>
        </div>
        <?php if ($history): ?>
            <div class="chart-box"><canvas id="trendChart"></canvas></div>
        <?php else: ?>
            <?= emptyState('fa-chart-column', 'Chưa có dữ liệu xu hướng', 'Hãy thêm vài giao dịch để xem biểu đồ.') ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon violet"><i class="fa-solid fa-chart-pie"></i></span>
                <div><h2>Cơ cấu chi tiêu</h2><p>Tháng <?= date('m/Y') ?></p></div>
            </div>
        </div>
        <?php if ($breakdown): ?>
            <div class="chart-box sm"><canvas id="catChart"></canvas></div>
            <div class="legend" style="margin-top:1rem">
                <?php foreach (array_slice($breakdown, 0, 5) as $b): ?>
                    <div class="legend-item">
                        <i style="background:<?= catColor($b['category']) ?>"></i>
                        <span><?= e($b['category']) ?></span>
                        <b><?= money($b['amount']) ?></b>
                        <small class="t-muted" style="width:46px;text-align:right"><?= round($b['percentage']) ?>%</small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= emptyState('fa-chart-pie', 'Chưa có khoản chi nào trong tháng') ?>
        <?php endif; ?>
    </div>
</section>

<!-- Recent + Budgets + Rates -->
<section class="grid grid-main">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon green"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <div><h2>Giao dịch gần đây</h2><p>Đồng bộ từ Transaction Service</p></div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button class="btn btn-ghost btn-sm" data-open="txModal"><i class="fa-solid fa-plus"></i> Thêm</button>
                <a href="transactions.php" class="panel-link">Xem tất cả →</a>
            </div>
        </div>
        <?php if ($recent): ?>
            <div class="tx-list">
                <?php foreach ($recent as $t) echo txRow($t); ?>
            </div>
        <?php else: ?>
            <?= emptyState('fa-receipt', 'Chưa có giao dịch nào', 'Bấm "Thêm" để ghi chép khoản thu chi đầu tiên.') ?>
        <?php endif; ?>

        <?php if ($anomalies): ?>
            <div style="margin-top:1rem">
                <?php foreach (array_slice($anomalies, 0, 2) as $a): ?>
                    <div class="anomaly">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div class="small"><strong>Chi tiêu bất thường:</strong> <?= money($a['amount']) ?> cho <em><?= e($a['category']) ?></em>
                            (trung vị <?= money($a['category_median']) ?>) ngày <?= dateVN($a['transaction_date']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($accounts): ?>
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="p-icon"><i class="fa-solid fa-building-columns"></i></span>
                    <div><h3>Tài khoản liên kết</h3><p>Tổng số dư <?= money($curBal) ?></p></div>
                </div>
                <a href="banking.php" class="panel-link">Quản lý →</a>
            </div>
            <div class="dash-bank-list">
                <?php foreach ($accounts as $ac):
                    $bPct = $curBal > 0 ? round(((float) $ac['balance'] / $curBal) * 100) : 0;
                    $bClr = bankBrandColor($ac['bank_code']);
                ?>
                    <a href="banking.php?acc=<?= (int) $ac['id'] ?>" class="dash-bank-row" style="--b-clr: <?= $bClr ?>">
                        <?= bankLogoSvg($ac['bank_code'], 38) ?>
                        <div class="dash-bank-meta">
                            <strong><?= e(bankName($ac['bank_code'])) ?></strong>
                            <span>•••• <?= e(substr(preg_replace('/\D/', '', $ac['account_no']), -4)) ?> · <?= $bPct ?>% tỷ trọng</span>
                        </div>
                        <div class="dash-bank-bal"><?= money($ac['balance']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="p-icon amber"><i class="fa-solid fa-bullseye"></i></span>
                    <div><h3>Ngân sách</h3><p>Mức sử dụng hạn mức</p></div>
                </div>
                <a href="budgets.php" class="panel-link">Quản lý →</a>
            </div>
            <?php if ($budgets): ?>
                <?php foreach (array_slice($budgets, 0, 4) as $b):
                    $pct = (float) $b['percentage'];
                    $cls = $pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : ''); ?>
                    <div class="budget-item">
                        <div class="budget-head">
                            <span class="cat-icon" style="--c:<?= catColor($b['category_name']) ?>"><i class="<?= catIcon($b['category_icon'] ?? null) ?>"></i></span>
                            <strong><?= e($b['category_name']) ?></strong>
                            <span class="pill <?= $cls === 'over' ? 'red' : ($cls === 'warn' ? 'amber' : 'green') ?>"><?= round($pct) ?>%</span>
                        </div>
                        <div class="progress <?= $cls ?>"><span data-width="<?= $pct ?>"></span></div>
                        <div class="budget-meta"><span><?= money($b['spent']) ?></span><span>/ <?= money($b['amount_limit']) ?></span></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?= emptyState('fa-bullseye', 'Chưa đặt ngân sách', 'Đặt hạn mức để được cảnh báo khi chi quá tay.') ?>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="p-icon"><i class="fa-solid fa-coins"></i></span>
                    <div><h3>Tỷ giá hôm nay</h3><p>Integration Service</p></div>
                </div>
                <a href="banking.php" class="panel-link">Chi tiết →</a>
            </div>
            <?php if ($rates): ?>
                <?php foreach (['USD', 'EUR', 'JPY', 'SGD'] as $code): if (empty($rates[$code])) continue; ?>
                    <div class="rate-row">
                        <span class="flag"><?= $code ?></span>
                        <strong><?= $code ?> / VND<small>Cập nhật <?= timeAgo($rates[$code]['updated_at'] ?? null) ?></small></strong>
                        <span class="money t-ink"><?= number_format((float) $rates[$code]['rate_to_vnd'], $code === 'JPY' ? 2 : 0, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?= emptyState('fa-coins', 'Chưa có dữ liệu tỷ giá') ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
$returnTo = 'index.php';
include __DIR__ . '/includes/tx_modal.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const history = <?= json_encode($history) ?>;
    const breakdown = <?= json_encode(array_map(fn($b) => $b + ['color' => catColor($b['category'])], $breakdown)) ?>;
    const fmt = v => new Intl.NumberFormat('vi-VN').format(v) + ' ₫';
    const short = v => Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1).replace('.0', '') + 'tr' : (v / 1e3).toFixed(0) + 'k';

    const tEl = document.getElementById('trendChart');
    if (tEl && history.length) {
        const ctx = tEl.getContext('2d');
        const gIn = ctx.createLinearGradient(0, 0, 0, 280); gIn.addColorStop(0, '#34d399'); gIn.addColorStop(1, '#10b981');
        const gEx = ctx.createLinearGradient(0, 0, 0, 280); gEx.addColorStop(0, '#fb7185'); gEx.addColorStop(1, '#ef4444');
        new Chart(tEl, {
            data: {
                labels: history.map(h => 'T' + h.month.slice(5) + '/' + h.month.slice(2, 4)),
                datasets: [
                    { type: 'bar', label: 'Thu nhập', data: history.map(h => h.income), backgroundColor: gIn, borderRadius: 8, maxBarThickness: 28 },
                    { type: 'bar', label: 'Chi tiêu', data: history.map(h => h.expense), backgroundColor: gEx, borderRadius: 8, maxBarThickness: 28 },
                    { type: 'line', label: 'Chênh lệch', data: history.map(h => h.net_cashflow), borderColor: '#0066cc', backgroundColor: '#0066cc',
                      tension: .4, borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderWidth: 2.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end' }, tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + fmt(c.raw) } } },
                scales: { y: { ticks: { callback: short }, grid: { color: '#eef2f7' }, border: { display: false } }, x: { grid: { display: false } } }
            }
        });
    }

    const cEl = document.getElementById('catChart');
    if (cEl && breakdown.length) {
        new Chart(cEl, {
            type: 'doughnut',
            data: { labels: breakdown.map(b => b.category), datasets: [{ data: breakdown.map(b => b.amount), backgroundColor: breakdown.map(b => b.color), borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + fmt(c.raw) } } } },
            plugins: [{
                id: 'center', afterDraw(chart) {
                    const { ctx, chartArea: a } = chart; const total = breakdown.reduce((s, b) => s + b.amount, 0);
                    ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = '#64748b'; ctx.font = '700 11px Plus Jakarta Sans';
                    ctx.fillText('TỔNG CHI', (a.left + a.right) / 2, (a.top + a.bottom) / 2 - 8);
                    ctx.fillStyle = '#0f172a'; ctx.font = '700 16px JetBrains Mono';
                    ctx.fillText(short(total), (a.left + a.right) / 2, (a.top + a.bottom) / 2 + 12); ctx.restore();
                }
            }]
        });
    }
});
</script>

<?php renderFooter(); ?>
