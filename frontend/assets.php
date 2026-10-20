<?php
/**
 * Tài sản & Vay - Asset Service (C#)
 *  GET/POST /api/assets · PUT /api/assets/{id} · GET/POST /api/loans · GET/POST /api/investments
 *  GET /api/assets/net-worth · POST /api/assets/depreciation · POST /api/loans/calculate
 *  Lưu ý: Asset Service đọc user_id từ query/body, nên luôn gửi kèm user_id.
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();

const ASSET_TYPES = [
    'BANK_ACCOUNT' => ['Tài khoản NH', 'fa-building-columns', '#0066cc'],
    'CASH'         => ['Tiền mặt',      'fa-money-bill-wave',  '#10b981'],
    'REAL_ESTATE'  => ['Bất động sản',  'fa-house',            '#f59e0b'],
    'VEHICLE'      => ['Phương tiện',   'fa-car',              '#6366f1'],
    'ELECTRONICS'  => ['Thiết bị',      'fa-laptop',           '#06b6d4'],
    'STOCK'        => ['Cổ phiếu',      'fa-chart-line',       '#8b5cf6'],
    'CRYPTO'       => ['Tiền số',       'fa-bitcoin-sign',     '#f97316'],
    'GOLD'         => ['Vàng',          'fa-coins',            '#eab308'],
    'OTHER'        => ['Khác',          'fa-box',              '#64748b'],
];
const INVEST_TYPES = ['STOCK' => 'Cổ phiếu', 'CRYPTO' => 'Tiền số', 'GOLD' => 'Vàng', 'BOND' => 'Trái phiếu', 'FUND' => 'Chứng chỉ quỹ'];

function assetType(string $t): array
{
    return ASSET_TYPES[strtoupper($t)] ?? [ucwords(strtolower(str_replace('_', ' ', $t))), 'fa-box', '#64748b'];
}

/* ------------------------------ POST ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $num = fn($k) => (float) ($_POST[$k] ?? 0);

    if ($action === 'save_asset') {
        $id = (int) ($_POST['asset_id'] ?? 0);
        $payload = [
            'user_id'           => $uid,
            'asset_name'        => trim((string) ($_POST['asset_name'] ?? '')),
            'asset_type'        => strtoupper(trim((string) ($_POST['asset_type'] ?? 'OTHER'))),
            'purchase_price'    => $num('purchase_price'),
            'current_value'     => $num('current_value'),
            'salvage_value'     => $num('salvage_value'),
            'useful_life_years' => (int) ($_POST['useful_life_years'] ?? 0),
            'purchase_date'     => ($_POST['purchase_date'] ?? '') ?: null,
            'currency'          => 'VND',
            'note'              => trim((string) ($_POST['note'] ?? '')) ?: null,
        ];
        $r = $id ? api('PUT', "/api/assets/$id?user_id=$uid", $payload) : api('POST', "/api/assets?user_id=$uid", $payload);
        $r['ok'] ? flash('success', ($id ? 'Đã cập nhật tài sản ' : 'Đã thêm tài sản ') . $payload['asset_name'] . '.')
                 : flash('danger', 'Không lưu được tài sản: ' . $r['error']);
    } elseif ($action === 'add_loan') {
        $payload = [
            'user_id'          => $uid,
            'loan_title'       => trim((string) ($_POST['loan_title'] ?? '')),
            'principal_amount' => $num('principal_amount'),
            'interest_rate'    => $num('interest_rate'),
            'term_months'      => (int) ($_POST['term_months'] ?? 0),
            'start_date'       => ($_POST['start_date'] ?? '') ?: date('Y-m-d'),
        ];
        $r = api('POST', "/api/loans?user_id=$uid", $payload);
        $r['ok'] ? flash('success', 'Đã ghi nhận khoản vay ' . $payload['loan_title'] . '.')
                 : flash('danger', 'Không lưu được khoản vay: ' . $r['error']);
    } elseif ($action === 'add_investment') {
        $payload = [
            'user_id'         => $uid,
            'symbol'          => strtoupper(trim((string) ($_POST['symbol'] ?? ''))),
            'investment_type' => strtoupper((string) ($_POST['investment_type'] ?? 'STOCK')),
            'quantity'        => $num('quantity'),
            'buy_price'       => $num('buy_price'),
            'current_price'   => $num('current_price'),
        ];
        $r = api('POST', "/api/investments?user_id=$uid", $payload);
        $r['ok'] ? flash('success', 'Đã thêm khoản đầu tư ' . $payload['symbol'] . '.')
                 : flash('danger', 'Không lưu được khoản đầu tư: ' . $r['error']);
    }
    redirect('assets.php');
}

/* ------------------------------ GET ------------------------------ */
['net' => $rNet, 'assets' => $rAssets, 'loans' => $rLoans, 'inv' => $rInv] = apiAll([
    'net'    => ['GET', "/api/assets/net-worth?user_id=$uid"],
    'assets' => ['GET', "/api/assets?user_id=$uid"],
    'loans'  => ['GET', "/api/loans?user_id=$uid"],
    'inv'    => ['GET', "/api/investments?user_id=$uid"],
]);

$netWorth = (float) (apiData($rNet, [])['total_net_worth'] ?? 0);
$assets = apiData($rAssets, []);
$loans = apiData($rLoans, []);
$investments = apiData($rInv, []);
$errors = collectErrors($rNet, $rAssets, $rLoans, $rInv);

$totAssets = array_sum(array_map(fn($a) => (float) $a['current_value'], $assets));
$totDebt = array_sum(array_map(fn($l) => strtoupper($l['status'] ?? 'ACTIVE') === 'ACTIVE' ? (float) $l['principal_amount'] : 0, $loans));
$invCost = $invValue = 0;
foreach ($investments as $i) {
    $invCost += (float) $i['quantity'] * (float) $i['buy_price'];
    $invValue += (float) $i['quantity'] * (float) $i['current_price'];
}
$invPL = $invValue - $invCost;
$debtRatio = $totAssets > 0 ? round($totDebt / $totAssets * 100, 1) : 0;

// Cơ cấu tài sản theo loại
$alloc = [];
foreach ($assets as $a) {
    $k = strtoupper($a['asset_type']);
    $alloc[$k] = ($alloc[$k] ?? 0) + (float) $a['current_value'];
}
arsort($alloc);

function monthlyPayment(float $p, float $rate, int $n): float
{
    if ($n <= 0) return 0;
    $r = $rate / 100 / 12;
    return $r == 0 ? $p / $n : $p * $r * pow(1 + $r, $n) / (pow(1 + $r, $n) - 1);
}

renderHeader('Tài sản & Vay', 'assets');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'ADMINISTRATOR');
$cardWidget = renderHeroBankCard(
    'Asset Vault',
    'SMART ASSET & WEALTH',
    '9704 •••• •••• ' . (substr((string)$uid, -4) ?: '8888'),
    $holder,
    'TỔNG TÀI SẢN',
    shortVnd($totAssets),
    'TÀI SẢN RÒNG',
    vnd($netWorth),
    'VISA',
    'visa'
);

$heroDesc = 'Bảng theo dõi giá trị tổng tài sản, dư nợ các khoản vay và danh mục đầu tư tài chính. Tự động tính toán tài sản ròng và lịch khấu hao tài sản.'
    . '<div class="luxury-hero-actions">'
    . '<button class="btn-hero-pri" data-open="assetModal" data-new-asset><i class="fa-solid fa-plus"></i> Thêm tài sản mới</button>'
    . '<button class="btn-hero-sec" data-open="loanModal"><i class="fa-solid fa-hand-holding-dollar"></i> Ghi khoản vay</button>'
    . '<button class="btn-hero-sec" data-open="investModal"><i class="fa-solid fa-chart-line"></i> Đầu tư mới</button>'
    . '</div>';

pageHero('Asset Service · C# .NET',
    'Tài Sản, Khoản Vay & Đầu Tư',
    $heroDesc,
    $cardWidget);
?>

<section class="grid grid-4 mb">
    <div class="stat blue anim-fade-in anim-delay-1">
        <div class="stat-top"><span class="stat-label">Tài sản ròng (Net Worth)</span><span class="stat-icon"><i class="fa-solid fa-gem"></i></span></div>
        <div class="stat-value <?= $netWorth < 0 ? 't-red' : '' ?>"><?= money($netWorth) ?></div>
        <div class="stat-foot"><i class="fa-solid fa-circle-check" style="color:#059669"></i> Đồng bộ realtime từ C# Service</div>
    </div>
    <div class="stat green anim-fade-in anim-delay-2">
        <div class="stat-top"><span class="stat-label">Tổng giá trị tài sản</span><span class="stat-icon"><i class="fa-solid fa-vault"></i></span></div>
        <div class="stat-value t-green"><?= money($totAssets) ?></div>
        <div class="stat-foot"><span class="pill green"><?= count($assets) ?> tài sản</span> trong danh mục</div>
    </div>
    <div class="stat red anim-fade-in anim-delay-3">
        <div class="stat-top"><span class="stat-label">Tổng dư nợ hiện tại</span><span class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span></div>
        <div class="stat-value t-red"><?= money($totDebt) ?></div>
        <div class="stat-foot">Tỷ lệ nợ/tài sản: <span class="pill <?= $debtRatio > 50 ? 'red' : ($debtRatio > 30 ? 'amber' : 'green') ?>"><?= $debtRatio ?>%</span></div>
    </div>
    <div class="stat violet anim-fade-in anim-delay-4">
        <div class="stat-top"><span class="stat-label">Lãi/lỗ danh mục đầu tư</span><span class="stat-icon"><i class="fa-solid fa-chart-line"></i></span></div>
        <div class="stat-value <?= $invPL >= 0 ? 't-green' : 't-red' ?>"><?= money($invPL, true) ?></div>
        <div class="stat-foot">Giá trị thị trường: <b class="money"><?= shortVnd($invValue) ?></b></div>
    </div>
</section>

<section class="grid grid-main mb">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon green"><i class="fa-solid fa-vault"></i></span>
                <div><h2>Danh sách tài sản</h2><p>Giá trị hiện tại và khấu hao</p></div>
            </div>
            <button class="btn btn-ghost btn-sm" data-open="assetModal" data-new-asset><i class="fa-solid fa-plus"></i> Thêm</button>
        </div>
        <?php if (!$assets): ?>
            <?= emptyState('fa-vault', 'Chưa có tài sản nào', 'Thêm tài khoản, tiền mặt, bất động sản... để tính tài sản ròng.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tài sản</th><th>Loại</th><th class="t-right">Giá mua</th><th class="t-right">Hiện tại</th><th class="t-right">Biến động</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($assets as $a):
                        [$tLabel, $tIcon, $tColor] = assetType($a['asset_type']);
                        $chg = (float) $a['current_value'] - (float) $a['purchase_price'];
                        $chgPct = (float) $a['purchase_price'] > 0 ? $chg / (float) $a['purchase_price'] * 100 : 0;
                        $json = json_encode([
                            'id' => (int) $a['id'], 'asset_name' => $a['asset_name'], 'asset_type' => strtoupper($a['asset_type']),
                            'purchase_price' => (float) $a['purchase_price'], 'current_value' => (float) $a['current_value'],
                            'salvage_value' => (float) $a['salvage_value'], 'useful_life_years' => (int) $a['useful_life_years'],
                            'purchase_date' => $a['purchase_date'] ? substr((string) $a['purchase_date'], 0, 10) : '', 'note' => $a['note'] ?? '',
                        ], JSON_UNESCAPED_UNICODE); ?>
                        <tr>
                            <td><div class="cell-flex">
                                <span class="cat-icon" style="--c:<?= $tColor ?>"><i class="fa-solid <?= $tIcon ?>"></i></span>
                                <div><strong class="t-ink"><?= e($a['asset_name']) ?></strong>
                                    <div class="small t-muted"><?= $a['purchase_date'] ? 'Mua ' . dateVN($a['purchase_date']) : '' ?><?= !empty($a['note']) ? ' · ' . e($a['note']) : '' ?></div></div>
                            </div></td>
                            <td><span class="pill blue"><?= e($tLabel) ?></span></td>
                            <td class="t-right money small"><?= money($a['purchase_price']) ?></td>
                            <td class="t-right money t-ink"><?= money($a['current_value']) ?></td>
                            <td class="t-right"><span class="pill <?= $chg >= 0 ? 'green' : 'red' ?>"><?= $chg >= 0 ? '▲ +' : '▼ ' ?><?= round(abs($chgPct), 1) ?>%</span></td>
                            <td class="t-right"><button class="btn-icon edit" title="Sửa" data-edit-asset='<?= e($json) ?>'><i class="fa-solid fa-pen"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon violet"><i class="fa-solid fa-chart-pie"></i></span>
                <div><h3>Cơ cấu tài sản</h3><p>Theo loại tài sản</p></div>
            </div>
        </div>
        <?php if ($alloc): ?>
            <div class="chart-box sm"><canvas id="allocChart"></canvas></div>
            <div class="legend" style="margin-top:1rem">
                <?php foreach ($alloc as $k => $v): [$l, , $c] = assetType($k); ?>
                    <div class="legend-item"><i style="background:<?= $c ?>"></i><span><?= e($l) ?></span><b><?= shortVnd($v) ?></b>
                        <small class="t-muted" style="width:46px;text-align:right"><?= $totAssets > 0 ? round($v / $totAssets * 100) : 0 ?>%</small></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= emptyState('fa-chart-pie', 'Chưa có dữ liệu') ?>
        <?php endif; ?>
    </div>
</section>

<section class="grid grid-2 mb">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon red"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                <div><h3>Khoản vay & nợ</h3><p>Trả góp đều hàng tháng (annuity)</p></div>
            </div>
            <button class="btn btn-ghost btn-sm" data-open="loanModal"><i class="fa-solid fa-plus"></i> Thêm</button>
        </div>
        <?php if (!$loans): ?>
            <?= emptyState('fa-hand-holding-dollar', 'Không có khoản vay nào', 'Tuyệt vời! Bạn đang không có dư nợ.') ?>
        <?php else: foreach ($loans as $l):
            $active = strtoupper($l['status'] ?? 'ACTIVE') === 'ACTIVE';
            $start = strtotime((string) $l['start_date']);
            $term = (int) $l['term_months'];
            $elapsed = $start ? max(0, min($term, (int) floor((time() - $start) / (86400 * 30.44)))) : 0;
            $pctPaid = $term > 0 ? round($elapsed / $term * 100) : 0;
            $mp = monthlyPayment((float) $l['principal_amount'], (float) $l['interest_rate'], $term); ?>
            <div class="budget-item">
                <div class="budget-head">
                    <span class="cat-icon" style="--c:#ef4444"><i class="fa-solid fa-landmark"></i></span>
                    <strong><?= e($l['loan_title']) ?>
                        <span class="small t-muted" style="display:block;font-weight:600"><?= (float) $l['interest_rate'] ?>%/năm · <?= $term ?> tháng · từ <?= dateVN($l['start_date']) ?></span></strong>
                    <span class="pill <?= $active ? 'amber' : 'green' ?>"><?= $active ? 'Đang vay' : 'Đã tất toán' ?></span>
                </div>
                <div class="progress"><span data-width="<?= $active ? $pctPaid : 100 ?>"></span></div>
                <div class="budget-meta">
                    <span>Gốc <b class="money t-ink"><?= money($l['principal_amount']) ?></b></span>
                    <span>Trả ~<b class="money t-red"><?= money($mp) ?></b>/tháng · <?= $elapsed ?>/<?= $term ?> kỳ</span>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon"><i class="fa-solid fa-arrow-trend-up"></i></span>
                <div><h3>Danh mục đầu tư</h3><p>Giá vốn và lãi/lỗ tạm tính</p></div>
            </div>
            <button class="btn btn-ghost btn-sm" data-open="invModal"><i class="fa-solid fa-plus"></i> Thêm</button>
        </div>
        <?php if (!$investments): ?>
            <?= emptyState('fa-chart-line', 'Chưa có khoản đầu tư', 'Thêm cổ phiếu, vàng, tiền số để theo dõi lãi lỗ.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Mã</th><th class="t-right">SL</th><th class="t-right">Giá vốn</th><th class="t-right">Giá hiện tại</th><th class="t-right">Lãi/Lỗ</th></tr></thead>
                    <tbody>
                    <?php foreach ($investments as $i):
                        $cost = (float) $i['quantity'] * (float) $i['buy_price'];
                        $val = (float) $i['quantity'] * (float) $i['current_price'];
                        $pl = $val - $cost; $plPct = $cost > 0 ? $pl / $cost * 100 : 0; ?>
                        <tr>
                            <td><div class="cell-flex"><span class="flag"><?= e(mb_substr($i['symbol'], 0, 4)) ?></span>
                                <div><strong class="t-ink"><?= e($i['symbol']) ?></strong><div class="small t-muted"><?= e(INVEST_TYPES[strtoupper($i['investment_type'])] ?? $i['investment_type']) ?></div></div></div></td>
                            <td class="t-right money small"><?= rtrim(rtrim(number_format((float) $i['quantity'], 4, ',', '.'), '0'), ',') ?></td>
                            <td class="t-right money small"><?= shortVnd($i['buy_price']) ?></td>
                            <td class="t-right">
                                <div class="money small <?= $pl >= 0 ? 't-green' : 't-red' ?>"><?= money($pl, true) ?></div>
                                <div style="margin-top:3px"><span class="pill <?= $pl >= 0 ? 'green' : 'red' ?>"><?= $pl >= 0 ? '▲ +' : '▼ ' ?><?= round(abs($plPct), 2) ?>%</span></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Công cụ tính toán -->
<section class="grid grid-2">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon amber"><i class="fa-solid fa-calculator"></i></span>
                <div><h3>Tính trả góp khoản vay</h3><p>POST /api/loans/calculate</p></div>
            </div>
        </div>
        <form id="loanCalc" class="form-grid" data-ajax="1">
            <div class="field full">
                <label for="lcP">Số tiền vay</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-coins"></i>
                    <input class="input input-money" id="lcP" name="principal_amount" type="number" min="1" value="500000000" required>
                    <span class="suffix">VND</span>
                </div>
            </div>
            <div class="field">
                <label for="lcR">Lãi suất (%/năm)</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-percent"></i>
                    <input class="input" id="lcR" name="interest_rate" type="number" step="0.01" min="0" value="9.5" required>
                    <span class="suffix">%/năm</span>
                </div>
            </div>
            <div class="field">
                <label for="lcN">Kỳ hạn (tháng)</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-calendar-days"></i>
                    <input class="input" id="lcN" name="term_months" type="number" min="1" value="120" required>
                    <span class="suffix">tháng</span>
                </div>
            </div>
            <div class="field full">
                <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-bolt"></i> Tính toán trả góp</button>
            </div>
        </form>
        <div id="loanCalcResult"></div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <span class="p-icon violet"><i class="fa-solid fa-chart-area"></i></span>
                <div><h3>Tính khấu hao tài sản</h3><p>POST /api/assets/depreciation · đường thẳng</p></div>
            </div>
        </div>
        <form id="depCalc" class="form-grid" data-ajax="1">
            <div class="field full">
                <label for="dcP">Giá mua ban đầu</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-tag"></i>
                    <input class="input input-money" id="dcP" name="purchase_price" type="number" min="0" value="30000000" required>
                    <span class="suffix">VND</span>
                </div>
            </div>
            <div class="field">
                <label for="dcS">Giá trị thu hồi ước tính</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                    <input class="input input-money" id="dcS" name="salvage_value" type="number" min="0" value="3000000" required>
                    <span class="suffix">VND</span>
                </div>
            </div>
            <div class="field">
                <label for="dcN">Số năm sử dụng</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <input class="input" id="dcN" name="useful_life_years" type="number" min="1" value="5" required>
                    <span class="suffix">năm</span>
                </div>
            </div>
            <div class="field full">
                <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-bolt"></i> Tính khấu hao</button>
            </div>
        </form>
        <div id="depCalcResult"></div>
    </div>
</section>

<!-- Modal tài sản -->
<div class="modal" id="assetModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon green"><i class="fa-solid fa-vault"></i></span>
            <div style="flex:1"><h3 id="assetTitle">Thêm tài sản</h3><p>Lưu vào Asset Service</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" id="assetForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_asset">
            <input type="hidden" name="asset_id" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full"><label for="aName">Tên tài sản</label><input class="input" id="aName" name="asset_name" maxlength="100" required placeholder="vd: Tài khoản Vietcombank"></div>
                    <div class="field"><label for="aType">Loại</label>
                        <select class="select" id="aType" name="asset_type">
                            <?php foreach (ASSET_TYPES as $k => [$l]): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label for="aDate">Ngày mua</label><input class="input" type="date" id="aDate" name="purchase_date"></div>
                    <div class="field"><label for="aBuy">Giá mua (VND)</label><input class="input" type="number" min="0" id="aBuy" name="purchase_price" required></div>
                    <div class="field"><label for="aCur">Giá trị hiện tại (VND)</label><input class="input" type="number" min="0" id="aCur" name="current_value" required></div>
                    <div class="field"><label for="aSal">Giá trị thu hồi</label><input class="input" type="number" min="0" id="aSal" name="salvage_value" value="0"></div>
                    <div class="field"><label for="aLife">Số năm sử dụng</label><input class="input" type="number" min="0" id="aLife" name="useful_life_years" value="0"></div>
                    <div class="field full"><label for="aNote">Ghi chú</label><input class="input" id="aNote" name="note" maxlength="255"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu tài sản</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal khoản vay -->
<div class="modal" id="loanModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon red"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            <div style="flex:1"><h3>Ghi nhận khoản vay</h3><p>Dư nợ sẽ được trừ vào tài sản ròng</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_loan">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full"><label for="lTitle">Tên khoản vay</label><input class="input" id="lTitle" name="loan_title" maxlength="150" required placeholder="vd: Vay mua xe"></div>
                    <div class="field full"><label for="lP">Số tiền gốc (VND)</label><input class="input input-money" type="number" min="1" id="lP" name="principal_amount" required></div>
                    <div class="field"><label for="lR">Lãi suất (%/năm)</label><input class="input" type="number" step="0.01" min="0" id="lR" name="interest_rate" required></div>
                    <div class="field"><label for="lN">Kỳ hạn (tháng)</label><input class="input" type="number" min="1" id="lN" name="term_months" required></div>
                    <div class="field full"><label for="lD">Ngày bắt đầu</label><input class="input" type="date" id="lD" name="start_date" value="<?= date('Y-m-d') ?>" required></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu khoản vay</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal đầu tư -->
<div class="modal" id="invModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon"><i class="fa-solid fa-arrow-trend-up"></i></span>
            <div style="flex:1"><h3>Thêm khoản đầu tư</h3><p>Cổ phiếu, vàng, tiền số...</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_investment">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label for="iSym">Mã</label><input class="input" id="iSym" name="symbol" maxlength="20" required placeholder="vd: FPT"></div>
                    <div class="field"><label for="iType">Loại</label>
                        <select class="select" id="iType" name="investment_type">
                            <?php foreach (INVEST_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field full"><label for="iQty">Số lượng</label><input class="input" type="number" step="0.0001" min="0.0001" id="iQty" name="quantity" required></div>
                    <div class="field"><label for="iBuy">Giá mua (VND)</label><input class="input" type="number" min="0" id="iBuy" name="buy_price" required></div>
                    <div class="field"><label for="iCur">Giá hiện tại (VND)</label><input class="input" type="number" min="0" id="iCur" name="current_price" required></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const alloc = <?= json_encode(array_map(fn($k, $v) => ['label' => assetType($k)[0], 'value' => $v, 'color' => assetType($k)[2]], array_keys($alloc), $alloc), JSON_UNESCAPED_UNICODE) ?>;
    const el = document.getElementById('allocChart');
    if (el && alloc.length) {
        new Chart(el, {
            type: 'doughnut',
            data: { labels: alloc.map(a => a.label), datasets: [{ data: alloc.map(a => a.value), backgroundColor: alloc.map(a => a.color), borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + CF.vnd(c.raw) } } } }
        });
    }

    // Sửa tài sản
    const af = document.getElementById('assetForm');
    document.addEventListener('click', e => {
        if (e.target.closest('[data-new-asset]')) { af.reset(); af.asset_id.value = ''; document.getElementById('assetTitle').textContent = 'Thêm tài sản'; }
        const b = e.target.closest('[data-edit-asset]');
        if (!b) return;
        const a = JSON.parse(b.dataset.editAsset);
        af.asset_id.value = a.id; af.asset_name.value = a.asset_name; af.purchase_date.value = a.purchase_date;
        if (![...af.asset_type.options].some(o => o.value === a.asset_type)) af.asset_type.add(new Option(a.asset_type, a.asset_type));
        af.asset_type.value = a.asset_type;
        af.purchase_price.value = Math.round(a.purchase_price); af.current_value.value = Math.round(a.current_value);
        af.salvage_value.value = Math.round(a.salvage_value); af.useful_life_years.value = a.useful_life_years; af.note.value = a.note || '';
        document.getElementById('assetTitle').textContent = 'Sửa tài sản #' + a.id;
        CF.openModal('assetModal');
    });

    // Máy tính gọi API thật qua proxy
    function calc(formId, path, render) {
        const f = document.getElementById(formId), out = document.getElementById(formId + 'Result');
        f.addEventListener('submit', async e => {
            e.preventDefault();
            const btn = f.querySelector('button[type=submit]');
            const body = Object.fromEntries([...new FormData(f)].map(([k, v]) => [k, Number(v)]));
            CF.busy(btn, true, 'Đang tính...');
            try { out.innerHTML = render(await CF.api('POST', path, body), body); }
            catch (err) { out.innerHTML = ''; CF.toast('Không tính được', err.message, 'danger'); }
            finally { CF.busy(btn, false); }
        });
    }
    calc('loanCalc', '/api/loans/calculate', (r, b) => {
        const total = r.monthly_payment * b.term_months;
        return `<div class="calc-result"><span>Trả góp mỗi tháng</span><b>${CF.vnd(r.monthly_payment)}</b>
            <p>Tổng phải trả <strong>${CF.vnd(total)}</strong> · Tổng lãi <strong class="t-red">${CF.vnd(total - b.principal_amount)}</strong></p></div>`;
    });
    calc('depCalc', '/api/assets/depreciation', r => `<div class="calc-result"><span>Khấu hao mỗi năm</span><b>${CF.vnd(r.annual_depreciation)}</b>
        <p>Tương đương <strong>${CF.vnd(r.monthly_depreciation)}</strong> mỗi tháng</p></div>`);
});
</script>

<?php renderFooter(); ?>
