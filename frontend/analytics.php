<?php
/**
 * Phân tích - Analytics Service (Python + SQLite)
 *  GET /api/analytics/summary · /trends · /category-breakdown · /anomalies · /reports
 *  POST /api/analytics/aggregate
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
$repYear = isset($_GET['rep_year']) && $_GET['rep_year'] !== '' ? (int) $_GET['rep_year'] : null;
$repMonth = isset($_GET['rep_month']) && $_GET['rep_month'] !== '' ? (int) $_GET['rep_month'] : null;

/* ------------------------------ POST ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'aggregate_report') {
        $aggFrom = trim((string) ($_POST['from_date'] ?? $from));
        $aggTo   = trim((string) ($_POST['to_date'] ?? $to));
        $payload = [
            'user_id'   => $uid,
            'from_date' => $aggFrom,
            'to_date'   => $aggTo,
        ];
        $r = api('POST', '/api/analytics/aggregate', $payload);
        if ($r['ok']) {
            $rep = apiData($r, []);
            $ym = sprintf('%02d/%04d', (int) ($rep['month'] ?? date('m')), (int) ($rep['year'] ?? date('Y')));
            flash('success', "Đã tổng hợp và lưu báo cáo tháng $ym vào SQLite (" . (int) ($rep['transaction_count'] ?? 0) . " giao dịch).");
        } else {
            flash('danger', 'Không thể tạo báo cáo tổng hợp: ' . $r['error']);
        }
    }
    redirect('analytics.php?' . http_build_query(['range' => $preset, 'from' => $from, 'to' => $to, 'months' => $months]));
}

/* ------------------------------ GET ------------------------------ */
$range = http_build_query(['user_id' => $uid, 'from_date' => $from, 'to_date' => $to]);
$repQuery = http_build_query(array_filter([
    'user_id' => $uid,
    'year'    => $repYear,
    'month'   => $repMonth,
], fn($v) => $v !== null && $v !== ''));

['sum' => $rSum, 'break' => $rBreak, 'trend' => $rTrend, 'anom' => $rAnom, 'reports' => $rReports] = apiAll([
    'sum'     => ['GET', "/api/analytics/summary?$range"],
    'break'   => ['GET', "/api/analytics/category-breakdown?$range"],
    'trend'   => ['GET', "/api/analytics/trends?months=$months&user_id=$uid"],
    'anom'    => ['GET', "/api/analytics/anomalies?$range"],
    'reports' => ['GET', "/api/analytics/reports?$repQuery"],
]);

$sum = apiData($rSum, []);
$breakdown = apiData($rBreak, []);
$trend = apiData($rTrend, []);
$history = $trend['history'] ?? [];
$forecast = $trend['forecast'] ?? null;
$anomalies = $rAnom['ok'] ? apiData($rAnom, []) : ($trend['anomalies'] ?? []);
$savedReports = apiData($rReports, []);
$errors = collectErrors($rSum, $rBreak, $rTrend, $rAnom, $rReports);

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

$heroDesc = 'Phân hệ phân tích và dự báo dòng tiền ứng dụng thống kê & Machine Learning (Python Native + SQLite). Tự động phân tích chuỗi thời gian, phát hiện chi tiêu bất thường (MAD Z-Score) và lưu trữ báo cáo tổng hợp hàng tháng.'
    . '<div class="luxury-hero-actions">'
    . '<button type="button" class="btn-hero-pri" data-open="aggregateModal"><i class="fa-solid fa-file-Waveform"></i> <i class="fa-solid fa-database"></i> Chốt & Lưu báo cáo tháng (SQLite)</button>'
    . '<a class="btn-hero-sec" href="#savedReportsSection"><i class="fa-solid fa-folder-tree"></i> Báo cáo đã lưu (' . count($savedReports) . ')</a>'
    . '<a class="btn-hero-sec" href="transactions.php"><i class="fa-solid fa-receipt"></i> Dữ liệu giao dịch</a>'
    . '</div>';

pageHero('Analytics Service · Python + SQLite',
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
            <div><h2>Xu hướng <?= $months ?> tháng & dự báo</h2><p>GET /api/analytics/trends · TB thu <?= shortVnd($avgIncome) ?> · TB chi <?= shortVnd($avgExpense) ?> mỗi tháng</p></div>
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
                <div><h3>Chi tiêu theo danh mục</h3><p>GET /api/analytics/category-breakdown</p></div>
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

<section class="panel mb">
    <div class="panel-head">
        <div class="panel-title">
            <span class="p-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><h3>Giao dịch bất thường</h3><p>GET /api/analytics/anomalies · Khoản chi vượt ngưỡng (median + 3·1.4826·MAD) của danh mục</p></div>
        </div>
        <span class="pill <?= $anomalies ? 'amber' : 'green' ?>"><?= count($anomalies) ?> phát hiện</span>
    </div>
    <?php if (!$anomalies): ?>
        <?= emptyState('fa-shield-halved', 'Không phát hiện chi tiêu bất thường', 'Chi tiêu của bạn trong kỳ đang ổn định.') ?>
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

<!-- Kho báo cáo tháng đã lưu trong SQLite (GET /api/analytics/reports & POST /api/analytics/aggregate) -->
<section class="panel" id="savedReportsSection">
    <div class="panel-head" style="flex-wrap:wrap;gap:1rem">
        <div class="panel-title">
            <span class="p-icon blue"><i class="fa-solid fa-database"></i></span>
            <div>
                <h3>Báo cáo tháng đã lưu (SQLite)</h3>
                <p>GET /api/analytics/reports · POST /api/analytics/aggregate</p>
            </div>
        </div>
        <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap">
            <form method="get" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="range" value="<?= e($preset) ?>">
                <input type="hidden" name="from" value="<?= e($from) ?>">
                <input type="hidden" name="to" value="<?= e($to) ?>">
                <input type="hidden" name="months" value="<?= $months ?>">
                <select class="select" name="rep_month" style="min-width:110px;padding:.42rem .7rem">
                    <option value="">Tất cả tháng</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $repMonth === $m ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                    <?php endfor; ?>
                </select>
                <select class="select" name="rep_year" style="min-width:105px;padding:.42rem .7rem">
                    <option value="">Tất cả năm</option>
                    <?php for ($y = (int) date('Y') + 1; $y >= 2023; $y--): ?>
                        <option value="<?= $y ?>" <?= $repYear === $y ? 'selected' : '' ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-ghost btn-sm" data-nobusy><i class="fa-solid fa-filter"></i> Lọc</button>
                <?php if ($repYear || $repMonth): ?>
                    <a href="analytics.php?range=<?= e($preset) ?>&months=<?= $months ?>#savedReportsSection" class="btn btn-ghost btn-sm">Xoá lọc</a>
                <?php endif; ?>
            </form>
            <button type="button" class="btn btn-primary btn-sm" data-open="aggregateModal">
                <i class="fa-solid fa-bolt"></i> Chốt & Lưu báo cáo kỳ này
            </button>
        </div>
    </div>

    <?php if (!$savedReports): ?>
        <?= emptyState('fa-database', 'Chưa có báo cáo tháng nào trong SQLite', 'Nhấn "Chốt & Lưu báo cáo kỳ này" để tổng hợp dữ liệu giao dịch và lưu vào bảng monthly_reports.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kỳ báo cáo</th>
                        <th class="t-right">Số GD</th>
                        <th class="t-right">Tổng thu</th>
                        <th class="t-right">Tổng chi</th>
                        <th class="t-right">Dòng tiền ròng</th>
                        <th>Danh mục chi lớn nhất</th>
                        <th>Bất thường</th>
                        <th>Thời điểm lưu</th>
                        <th class="t-right">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($savedReports as $idx => $rep):
                    $ry = (int) ($rep['year'] ?? 0);
                    $rm = (int) ($rep['month'] ?? 0);
                    $rNetVal = (float) ($rep['net_cashflow'] ?? 0);
                    $rCats = $rep['category_breakdown'] ?? [];
                    $rTop = $rCats[0] ?? null;
                    $rAnoms = $rep['anomalies'] ?? [];
                    $repJson = json_encode($rep, JSON_UNESCAPED_UNICODE);
                ?>
                    <tr>
                        <td>
                            <div class="cell-flex">
                                <span class="cat-icon" style="--c:#0066cc"><i class="fa-solid fa-calendar-check"></i></span>
                                <div>
                                    <strong class="t-ink">Tháng <?= sprintf('%02d/%04d', $rm, $ry) ?></strong>
                                    <div class="small t-muted">User #<?= (int) ($rep['user_id'] ?? $uid) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="t-right"><span class="pill blue"><?= (int) ($rep['transaction_count'] ?? 0) ?> GD</span></td>
                        <td class="t-right money t-green"><?= money($rep['income'] ?? 0) ?></td>
                        <td class="t-right money t-red"><?= money($rep['expense'] ?? 0) ?></td>
                        <td class="t-right money <?= $rNetVal >= 0 ? 't-blue' : 't-red' ?>"><b><?= money($rNetVal, true) ?></b></td>
                        <td>
                            <?php if ($rTop): ?>
                                <span class="pill violet"><?= e($rTop['category']) ?></span>
                                <span class="small t-muted" style="margin-left:.25rem"><?= shortVnd($rTop['amount']) ?> (<?= round((float) $rTop['percentage']) ?>%)</span>
                            <?php else: ?>
                                <span class="small t-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pill <?= $rAnoms ? 'amber' : 'green' ?>">
                                <?= count($rAnoms) ?> cảnh báo
                            </span>
                        </td>
                        <td class="small t-muted"><?= !empty($rep['generated_at']) ? dateVN($rep['generated_at'], true) : '—' ?></td>
                        <td class="t-right">
                            <button type="button" class="btn-icon" title="Xem chi tiết báo cáo" data-view-report='<?= e($repJson) ?>'>
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Modal tạo báo cáo tổng hợp tháng (POST /api/analytics/aggregate) -->
<div class="modal" id="aggregateModal">
    <div class="modal-dialog" style="max-width:480px">
        <div class="modal-head">
            <span class="p-icon blue"><i class="fa-solid fa-database"></i></span>
            <div style="flex:1"><h3>Tổng hợp & Lưu báo cáo tháng</h3><p>POST /api/analytics/aggregate · Lưu vào SQLite</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="aggregate_report">
            <div class="modal-body">
                <p class="small t-muted" style="margin-top:0;margin-bottom:1rem">
                    Hệ thống sẽ tự động lấy toàn bộ giao dịch từ <b>Transaction Service</b> trong khoảng ngày bên dưới, tính toán tổng thu/chi, cơ cấu danh mục, điểm bất thường và lưu bản ghi vào bảng <code>monthly_reports</code> (SQLite) theo tháng/năm của ngày kết thúc.
                </p>
                <div class="form-grid">
                    <div class="field">
                        <label for="aggFrom">Từ ngày</label>
                        <input class="input" type="date" id="aggFrom" name="from_date" value="<?= e($from) ?>" required>
                    </div>
                    <div class="field">
                        <label for="aggTo">Đến ngày (xác định Tháng/Năm)</label>
                        <input class="input" type="date" id="aggTo" name="to_date" value="<?= e($to) ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Tổng hợp & Lưu SQLite</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal xem chi tiết báo cáo tháng -->
<div class="modal" id="reportDetailModal">
    <div class="modal-dialog" style="max-width:600px">
        <div class="modal-head">
            <span class="p-icon violet"><i class="fa-solid fa-file-invoice"></i></span>
            <div style="flex:1"><h3 id="repModalTitle">Chi tiết báo cáo tháng</h3><p id="repModalSub">Bản ghi từ SQLite monthly_reports</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="repModalBody"></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close>Đóng</button>
        </div>
    </div>
</div>

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

    // Xem chi tiết báo cáo tháng từ SQLite
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-view-report]');
        if (!btn) return;
        const rep = JSON.parse(btn.dataset.viewReport);
        const ym = String(rep.month || '').padStart(2, '0') + '/' + rep.year;
        document.getElementById('repModalTitle').textContent = 'Báo cáo Tháng ' + ym;
        document.getElementById('repModalSub').textContent = 'Lưu lúc: ' + (rep.generated_at || '—') + ' · ' + (rep.transaction_count || 0) + ' giao dịch';
        const cats = (rep.category_breakdown || []).map(c =>
            `<div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px dashed #e2e8f0">
                <span><b>${c.category}</b> (${c.percentage}%)</span>
                <span class="money">${CF.vnd(c.amount)}</span>
            </div>`
        ).join('') || '<p class="small t-muted">Không có khoản chi nào.</p>';

        document.getElementById('repModalBody').innerHTML = `
            <div class="grid grid-3" style="gap:.75rem;margin-bottom:1rem">
                <div style="padding:.75rem;border-radius:12px;background:#f0fdf4">
                    <div class="small t-muted">Tổng thu</div>
                    <strong class="money t-green">${CF.vnd(rep.income || 0)}</strong>
                </div>
                <div style="padding:.75rem;border-radius:12px;background:#fef2f2">
                    <div class="small t-muted">Tổng chi</div>
                    <strong class="money t-red">${CF.vnd(rep.expense || 0)}</strong>
                </div>
                <div style="padding:.75rem;border-radius:12px;background:#eff6ff">
                    <div class="small t-muted">Dòng tiền ròng</div>
                    <strong class="money t-blue">${CF.vnd(rep.net_cashflow || 0)}</strong>
                </div>
            </div>
            <h4 style="margin:.5rem 0">Cơ cấu chi tiêu theo danh mục</h4>
            <div style="margin-bottom:1rem">${cats}</div>
            <details>
                <summary class="small t-muted" style="cursor:pointer;font-weight:600">Xem JSON nguyên bản trong SQLite</summary>
                <pre style="margin-top:.5rem;padding:.75rem;background:#0f172a;color:#e2e8f0;border-radius:10px;font-size:.78rem;overflow:auto;max-height:200px">${JSON.stringify(rep, null, 2)}</pre>
            </details>
        `;
        CF.openModal('reportDetailModal');
    });
});
</script>

<?php renderFooter(); ?>
