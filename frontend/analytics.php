<?php
/**
 * Phân tích - Analytics Service (Python)
 *  GET /api/analytics/summary · /trends · /category-breakdown
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();
$preset = $_GET['range'] ?? 'month';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$valid = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

if (!($valid($from) && $valid($to))) {
    switch ($preset) {
        case 'last':    $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('last day of last month')); break;
        case 'quarter': $from = date('Y-m-01', strtotime('-2 months')); $to = date('Y-m-d'); break;
        case 'year':    $from = date('Y-01-01'); $to = date('Y-m-d'); break;
        default:        $preset = 'month'; $from = date('Y-m-01'); $to = date('Y-m-d');
    }
} else {
    $preset = 'custom';
}
$months = max(3, min(12, (int) ($_GET['months'] ?? 6)));
$range = http_build_query(['user_id' => $uid, 'from_date' => $from, 'to_date' => $to]);

['sum' => $rSum, 'break' => $rBreak, 'trend' => $rTrend] = apiAll([
    'sum'   => ['GET', "/api/analytics/summary?$range"],
    'break' => ['GET', "/api/analytics/category-breakdown?$range"],
    'trend' => ['GET', "/api/analytics/trends?months=$months&user_id=$uid"],
]);

$sum = apiData($rSum, []);
$breakdown = apiData($rBreak, []);
$trend = apiData($rTrend, []);
$history = $trend['history'] ?? [];
$forecast = $trend['forecast'] ?? null;
$anomalies = $trend['anomalies'] ?? [];
$errors = collectErrors($rSum, $rBreak, $rTrend);

$income = (float) ($sum['income'] ?? 0);
$expense = (float) ($sum['expense'] ?? 0);
$net = (float) ($sum['net_cashflow'] ?? $income - $expense);
$count = (int) ($sum['transaction_count'] ?? 0);
$days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
$avgDaily = $expense / $days;
$avgIncome = $history ? array_sum(array_column($history, 'income')) / count($history) : 0;
$avgExpense = $history ? array_sum(array_column($history, 'expense')) / count($history) : 0;
$topCat = $breakdown[0] ?? null;

renderHeader('Phân tích', 'analytics');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$nextCashflow = isset($forecast['next_month_net_cashflow']) ? vnd($forecast['next_month_net_cashflow'], true) : '—';
$cardWidget = renderHeroBankCard(
    'AI Engine',
    'PREDICTIVE ANALYTICS',
    'ML-FORECAST •••• ' . (substr((string)$uid, -4) ?: '8888'),
    $holder,
    'MÔ HÌNH HỌC MÁY',
    strtoupper(str_replace('_', ' ', $forecast['method'] ?? 'Moving Average')),
    'DỰ BÁO DÒNG TIỀN',
    $nextCashflow,
    'VISA',
    'visa'
);

$heroDesc = 'Phân hệ phân tích và dự báo dòng tiền ứng dụng Machine Learning (Python FastAPI). Tự động phân tích chuỗi thời gian, phát hiện chi tiêu bất thường và ước tính xu hướng tài chính tương lai.'
    . '<div class="luxury-hero-actions">'
    . '<a class="btn-hero-pri" href="analytics.php?range=month&months=6"><i class="fa-solid fa-chart-line"></i> Xu hướng 6 tháng</a>'
    . '<a class="btn-hero-sec" href="transactions.php"><i class="fa-solid fa-receipt"></i> Dữ liệu giao dịch</a>'
    . '</div>';

pageHero('Analytics Service · Python ML',
    'Phân Tích & Dự Báo Dòng Tiền',
    $heroDesc,
    $cardWidget);
?>

<section class="panel mb">
    <form class="toolbar" method="get" style="flex-wrap:wrap;justify-content:space-between">
        <div class="tabs">
            <?php foreach (['month' => 'Tháng này', 'last' => 'Tháng trước', 'quarter' => '3 tháng', 'year' => 'Năm nay'] as $k => $l): ?>
                <a href="analytics.php?range=<?= $k ?>&months=<?= $months ?>" class="<?= $preset === $k ? 'active' : '' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap">
            <div class="field"><label for="aFrom">Từ ngày</label><input class="input" type="date" id="aFrom" name="from" value="<?= e($from) ?>"></div>
            <div class="field"><label for="aTo">Đến ngày</label><input class="input" type="date" id="aTo" name="to" value="<?= e($to) ?>"></div>
            <div class="field" style="min-width:120px"><label for="aMonths">Xu hướng</label>
                <select class="select" id="aMonths" name="months">
                    <?php foreach ([3, 6, 12] as $m): ?><option value="<?= $m ?>" <?= $m === $months ? 'selected' : '' ?>><?= $m ?> tháng</option><?php endforeach; ?>
                </select></div>
            <button class="btn btn-primary" type="submit" data-nobusy><i class="fa-solid fa-magnifying-glass-chart"></i> Phân tích</button>
        </div>
    </form>
</section>

<section class="grid grid-4 mb">
    <div class="stat green anim-fade-in anim-delay-1">
        <div class="stat-top"><span class="stat-label">Tổng thu nhập kỳ này</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span></div>
        <div class="stat-value t-green"><?= money($income) ?></div>
        <div class="stat-foot"><?= dateVN($from) ?> – <?= dateVN($to) ?></div>
    </div>
    <div class="stat red anim-fade-in anim-delay-2">
        <div class="stat-top"><span class="stat-label">Tổng chi tiêu kỳ này</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-down"></i></span></div>
        <div class="stat-value t-red"><?= money($expense) ?></div>
        <div class="stat-foot">Bình quân <b class="money"><?= shortVnd($avgDaily) ?></b> / ngày</div>
    </div>
    <div class="stat blue anim-fade-in anim-delay-3">
        <div class="stat-top"><span class="stat-label">Dòng tiền thuần (Net)</span><span class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></span></div>
        <div class="stat-value <?= $net < 0 ? 't-red' : 't-blue' ?>"><?= money($net, true) ?></div>
        <div class="stat-foot"><?= $count ?> giao dịch phát sinh</div>
    </div>
    <div class="stat amber anim-fade-in anim-delay-4">
        <div class="stat-top"><span class="stat-label">Chi tiêu lớn nhất</span><span class="stat-icon"><i class="fa-solid fa-crown"></i></span></div>
        <div class="stat-value" style="font-size:1.25rem"><?= $topCat ? e($topCat['category']) : '—' ?></div>
        <div class="stat-foot"><?= $topCat ? money($topCat['amount']) . ' · ' . round($topCat['percentage']) . '%' : 'Chưa có khoản chi' ?></div>
    </div>
</section>

<section class="panel mb">
    <div class="panel-head">
        <div class="panel-title">
            <span class="p-icon"><i class="fa-solid fa-chart-line"></i></span>
            <div><h2>Xu hướng <?= $months ?> tháng & dự báo</h2><p>TB thu <?= shortVnd($avgIncome) ?> · TB chi <?= shortVnd($avgExpense) ?> mỗi tháng</p></div>
        </div>
    </div>
    <?php if ($history): ?>
        <div class="chart-box" style="height:330px"><canvas id="trendChart"></canvas></div>
    <?php else: ?>
        <?= emptyState('fa-chart-line', 'Chưa có dữ liệu xu hướng') ?>
    <?php endif; ?>
</section>

<section class="grid grid-main mb">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon violet"><i class="fa-solid fa-list-ol"></i></span>
                <div><h3>Chi tiêu theo danh mục</h3><p>Trong kỳ đã chọn</p></div>
            </div>
        </div>
        <?php if (!$breakdown): ?>
            <?= emptyState('fa-chart-pie', 'Không có khoản chi trong kỳ') ?>
        <?php else: ?>
            <?php foreach ($breakdown as $i => $b): ?>
                <div class="budget-item">
                    <div class="budget-head">
                        <span class="cat-icon" style="--c:<?= catColor($b['category']) ?>"><b style="font-size:.8rem">#<?= $i + 1 ?></b></span>
                        <strong><?= e($b['category']) ?></strong>
                        <span class="money t-ink small"><?= money($b['amount']) ?></span>
                    </div>
                    <div class="progress"><span data-width="<?= (float) $b['percentage'] ?>" style="background:<?= catColor($b['category']) ?>"></span></div>
                    <div class="budget-meta"><span><?= round((float) $b['percentage'], 1) ?>% tổng chi</span><span>~<?= shortVnd($b['amount'] / $days) ?>/ngày</span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon violet"><i class="fa-solid fa-chart-pie"></i></span>
                <div><h3>Tỷ trọng</h3><p>Cơ cấu chi tiêu</p></div>
            </div>
        </div>
        <?php if ($breakdown): ?>
            <div class="chart-box"><canvas id="catChart"></canvas></div>
        <?php else: ?>
            <?= emptyState('fa-chart-pie', 'Chưa có dữ liệu') ?>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div class="panel-title">
            <span class="p-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><h3>Giao dịch bất thường</h3><p>Khoản chi vượt ngưỡng so với trung vị của danh mục</p></div>
        </div>
        <span class="pill <?= $anomalies ? 'amber' : 'green' ?>"><?= count($anomalies) ?> phát hiện</span>
    </div>
    <?php if (!$anomalies): ?>
        <?= emptyState('fa-shield-halved', 'Không phát hiện chi tiêu bất thường', 'Chi tiêu của bạn đang ổn định.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Giao dịch</th><th>Danh mục</th><th>Ngày</th><th class="t-right">Số tiền</th><th class="t-right">Trung vị</th><th class="t-right">Ngưỡng</th><th class="t-right">Gấp</th></tr></thead>
                <tbody>
                <?php foreach ($anomalies as $a): $x = (float) $a['category_median'] > 0 ? $a['amount'] / $a['category_median'] : 0; ?>
                    <tr>
                        <td><strong class="t-ink">#<?= (int) $a['transaction_id'] ?></strong></td>
                        <td><span class="pill amber"><?= e($a['category']) ?></span></td>
                        <td class="small"><?= dateVN($a['transaction_date'], true) ?></td>
                        <td class="t-right money t-red"><?= money($a['amount']) ?></td>
                        <td class="t-right money small"><?= money($a['category_median']) ?></td>
                        <td class="t-right money small"><?= money($a['threshold']) ?></td>
                        <td class="t-right"><span class="pill red">×<?= round($x, 1) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const history = <?= json_encode($history) ?>;
    const forecast = <?= json_encode($forecast) ?>;
    const breakdown = <?= json_encode(array_map(fn($b) => $b + ['color' => catColor($b['category'])], $breakdown), JSON_UNESCAPED_UNICODE) ?>;
    const short = v => Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1).replace('.0', '') + 'tr' : (v / 1e3).toFixed(0) + 'k';

    const tEl = document.getElementById('trendChart');
    if (tEl && history.length) {
        const labels = history.map(h => 'T' + h.month.slice(5) + '/' + h.month.slice(2, 4));
        const net = history.map(h => h.net_cashflow);
        const fc = history.map(() => null);
        if (forecast && forecast.next_month_net_cashflow != null) {
            const [y, m] = history[history.length - 1].month.split('-').map(Number);
            const nm = m === 12 ? 1 : m + 1, ny = m === 12 ? y + 1 : y;
            labels.push('T' + String(nm).padStart(2, '0') + '/' + String(ny).slice(2) + ' (dự báo)');
            fc[fc.length - 1] = net[net.length - 1];
            fc.push(forecast.next_month_net_cashflow);
        }
        const ctx = tEl.getContext('2d');
        const gNet = ctx.createLinearGradient(0, 0, 0, 320); gNet.addColorStop(0, 'rgba(0,102,204,.25)'); gNet.addColorStop(1, 'rgba(0,102,204,0)');
        new Chart(tEl, {
            data: {
                labels,
                datasets: [
                    { type: 'bar', label: 'Thu nhập', data: history.map(h => h.income), backgroundColor: '#34d399', borderRadius: 8, maxBarThickness: 26 },
                    { type: 'bar', label: 'Chi tiêu', data: history.map(h => h.expense), backgroundColor: '#fb7185', borderRadius: 8, maxBarThickness: 26 },
                    { type: 'line', label: 'Dòng tiền ròng', data: net, borderColor: '#0066cc', backgroundColor: gNet, fill: true, tension: .4, borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderWidth: 2.5 },
                    { type: 'line', label: 'Dự báo', data: fc, borderColor: '#8b5cf6', borderDash: [6, 6], borderWidth: 2.5, pointRadius: 5, pointBackgroundColor: '#8b5cf6', spanGaps: true }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end' }, tooltip: { callbacks: { label: c => c.raw == null ? null : ' ' + c.dataset.label + ': ' + CF.vnd(c.raw) } } },
                scales: { y: { ticks: { callback: short }, grid: { color: '#eef2f7' }, border: { display: false } }, x: { grid: { display: false } } }
            }
        });
    }

    const cEl = document.getElementById('catChart');
    if (cEl && breakdown.length) {
        new Chart(cEl, {
            type: 'polarArea',
            data: { labels: breakdown.map(b => b.category), datasets: [{ data: breakdown.map(b => b.amount), backgroundColor: breakdown.map(b => b.color + 'cc'), borderColor: '#fff', borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { r: { ticks: { display: false }, grid: { color: '#eef2f7' } } },
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ' ' + CF.vnd(c.raw) } } } }
        });
    }
});
</script>

<?php renderFooter(); ?>
