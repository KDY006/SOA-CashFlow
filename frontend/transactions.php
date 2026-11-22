<?php
/**
 * Thu chi - Transaction Service (PHP)
 *  GET /api/transactions · POST /api/transactions · PUT/DELETE /api/transactions/{id}
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();

/* ------------------------- POST: lưu / xoá ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $allowedReturn = ['index.php', 'transactions.php', 'budgets.php', 'analytics.php'];
    $ret = basename((string) ($_POST['return'] ?? 'transactions.php'));
    if (!in_array(strtok($ret, '?'), $allowedReturn, true)) $ret = 'transactions.php';
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['tx_id'] ?? 0);
        $r = api('DELETE', "/api/transactions/$id");
        $r['ok'] ? flash('success', "Đã xoá giao dịch #$id.") : flash('danger', 'Không xoá được: ' . $r['error']);
        redirect($ret);
    }

    if ($action === 'save') {
        $id = (int) ($_POST['tx_id'] ?? 0);
        $dt = strtotime((string) ($_POST['transaction_date'] ?? '')) ?: time();
        $catId = (int) ($_POST['category_id'] ?? 0);
        $catName = null;
        foreach (loadCategories() as $c) if ((int) $c['id'] === $catId) $catName = $c['name'];

        $payload = [
            'amount'           => (float) ($_POST['amount'] ?? 0),
            'type'             => ($_POST['type'] ?? 'EXPENSE') === 'INCOME' ? 'INCOME' : 'EXPENSE',
            'category_id'      => $catId,
            'description'      => trim((string) ($_POST['description'] ?? '')),
            'transaction_date' => date('Y-m-d H:i:s', $dt),
        ];
        if ($catName) $payload['category'] = $catName;

        $r = $id ? api('PUT', "/api/transactions/$id", $payload) : api('POST', '/api/transactions', $payload);
        if ($r['ok']) {
            flash('success', $id ? "Đã cập nhật giao dịch #$id." : 'Đã ghi chép giao dịch ' . vnd($payload['amount']) . '.');
            $alert = $r['body']['data']['budget_alert'] ?? null;
            if ($alert) {
                flash(($alert['status'] ?? '') === 'EXCEEDED' ? 'danger' : 'warning',
                    $alert['message'] ?? ('Ngân sách ' . ($alert['category'] ?? '') . ' sắp chạm hạn mức.'));
            }
        } else {
            flash('danger', 'Không lưu được giao dịch: ' . $r['error']);
        }
        redirect($ret);
    }
    redirect('transactions.php');
}

/* ------------------------- GET: danh sách ------------------------- */
$type  = in_array($_GET['type'] ?? '', ['INCOME', 'EXPENSE'], true) ? $_GET['type'] : '';
$catF  = (int) ($_GET['category_id'] ?? 0);
$fromF = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$toF   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$q     = trim((string) ($_GET['q'] ?? ''));

$query = ['limit' => 200, 'user_id' => $uid];
if ($type)  $query['type'] = $type;
if ($catF)  $query['category_id'] = $catF;
if ($fromF) $query['from_date'] = $fromF;
if ($toF)   $query['to_date'] = $toF . ' 23:59:59';

$rTx = api('GET', '/api/transactions?' . http_build_query($query));
$rows = apiData($rTx, []);
if ($q !== '') {
    $rows = array_values(array_filter($rows, fn($t) =>
        mb_stripos(($t['description'] ?? '') . ' ' . ($t['category'] ?? '') . ' ' . ($t['category_name_rel'] ?? ''), $q) !== false));
}

$sumIn = $sumEx = 0;
foreach ($rows as $t) {
    strtoupper($t['type']) === 'INCOME' ? $sumIn += (float) $t['amount'] : $sumEx += (float) $t['amount'];
}
$categories = loadCategories();
$errors = collectErrors($rTx);

// Gom theo ngày để hiển thị dạng sổ thu chi
$groups = [];
foreach ($rows as $t) {
    $groups[substr((string) $t['transaction_date'], 0, 10)][] = $t;
}

function filterUrl(array $over): string
{
    $p = array_merge(array_intersect_key($_GET, array_flip(['type', 'category_id', 'from', 'to', 'q'])), $over);
    return 'transactions.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
}

renderHeader('Thu chi', 'transactions');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    'Ledger ID',
    'TRANSACTION LOGS',
    'TXN-9704 •••• ' . (substr((string)$uid, -4) ?: '8888'),
    $holder,
    'TỔNG BẢN GHI',
    count($rows) . ' giao dịch',
    'DÒNG TIỀN THUẦN',
    vnd($sumIn - $sumEx, true),
    'VISA',
    'napas'
);

$heroDesc = 'Hệ thống theo dõi chi tiết biên lai, dòng tiền thu nhập và chi tiêu. Hỗ trợ tra cứu nhanh, lọc nâng cao theo thời gian và xuất biên lai thanh toán.'
    . '<div class="luxury-hero-actions">'
    . '<button class="btn-hero-pri" data-open="txModal"><i class="fa-solid fa-plus"></i> Ghi chép giao dịch mới</button>'
    . '<a class="btn-hero-sec" href="budgets.php"><i class="fa-solid fa-bullseye"></i> Đối soát ngân sách</a>'
    . '</div>';

pageHero('Transaction Service · PHP PDO',
    'Toàn Bộ Lịch Sử Giao Dịch',
    $heroDesc,
    $cardWidget);
?>

<section class="grid grid-4 mb">
    <div class="stat blue anim-fade-in anim-delay-1">
        <div class="stat-top"><span class="stat-label">Số lượng giao dịch</span><span class="stat-icon"><i class="fa-solid fa-list-check"></i></span></div>
        <div class="stat-value"><?= count($rows) ?></div>
        <div class="stat-foot"><i class="fa-solid fa-filter" style="color:#0066cc"></i> Theo bộ lọc hiện tại</div>
    </div>
    <div class="stat green anim-fade-in anim-delay-2">
        <div class="stat-top"><span class="stat-label">Tổng tiền thu vào</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span></div>
        <div class="stat-value t-green"><?= money($sumIn) ?></div>
        <div class="stat-foot"><span class="pill green"><?= count(array_filter($rows, fn($t) => strtoupper($t['type']) === 'INCOME')) ?> khoản</span> thu nhập</div>
    </div>
    <div class="stat red anim-fade-in anim-delay-3">
        <div class="stat-top"><span class="stat-label">Tổng tiền chi ra</span><span class="stat-icon"><i class="fa-solid fa-arrow-trend-down"></i></span></div>
        <div class="stat-value t-red"><?= money($sumEx) ?></div>
        <div class="stat-foot"><span class="pill red"><?= count(array_filter($rows, fn($t) => strtoupper($t['type']) === 'EXPENSE')) ?> khoản</span> chi tiêu</div>
    </div>
    <div class="stat amber anim-fade-in anim-delay-4">
        <div class="stat-top"><span class="stat-label">Chi tiêu trung bình</span><span class="stat-icon"><i class="fa-solid fa-calculator"></i></span></div>
        <?php $nEx = count(array_filter($rows, fn($t) => strtoupper($t['type']) === 'EXPENSE')); ?>
        <div class="stat-value"><?= money($nEx ? $sumEx / $nEx : 0) ?></div>
        <div class="stat-foot">Bình quân / mỗi khoản chi</div>
    </div>
</section>

<section class="panel">
    <div class="panel-head" style="flex-wrap:wrap">
        <div class="panel-title">
            <span class="p-icon"><i class="fa-solid fa-receipt"></i></span>
            <div><h2>Lịch sử giao dịch</h2><p>Hiển thị tối đa 200 giao dịch mới nhất</p></div>
        </div>
        <div class="tabs">
            <a href="<?= e(filterUrl(['type' => ''])) ?>" class="<?= $type === '' ? 'active' : '' ?>">Tất cả</a>
            <a href="<?= e(filterUrl(['type' => 'INCOME'])) ?>" class="<?= $type === 'INCOME' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-down"></i> Thu</a>
            <a href="<?= e(filterUrl(['type' => 'EXPENSE'])) ?>" class="<?= $type === 'EXPENSE' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-up"></i> Chi</a>
        </div>
    </div>

    <form class="toolbar mb" method="get" style="flex-wrap:wrap">
        <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
        <div class="field" style="flex:1;min-width:200px">
            <label for="fQ">Tìm kiếm</label>
            <div class="input-wrap"><i class="fa-solid fa-magnifying-glass"></i>
                <input class="input" id="fQ" name="q" value="<?= e($q) ?>" placeholder="Ghi chú, danh mục...">
            </div>
        </div>
        <div class="field">
            <label for="fCat">Danh mục</label>
            <select class="select" id="fCat" name="category_id">
                <option value="">Tất cả danh mục</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $catF === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?> (<?= strtoupper($c['type']) === 'INCOME' ? 'Thu' : 'Chi' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="fFrom">Từ ngày</label>
            <input class="input" type="date" id="fFrom" name="from" value="<?= e($fromF) ?>">
        </div>
        <div class="field">
            <label for="fTo">Đến ngày</label>
            <input class="input" type="date" id="fTo" name="to" value="<?= e($toF) ?>">
        </div>
        <button class="btn btn-primary" type="submit" data-nobusy><i class="fa-solid fa-filter"></i> Lọc</button>
        <?php if ($catF || $fromF || $toF || $q): ?>
            <a class="btn btn-ghost" href="<?= e(filterUrl(['category_id' => '', 'from' => '', 'to' => '', 'q' => ''])) ?>"><i class="fa-solid fa-rotate-left"></i> Bỏ lọc</a>
        <?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <?= emptyState('fa-receipt', 'Không có giao dịch phù hợp', 'Thử thay đổi bộ lọc hoặc thêm giao dịch mới.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Giao dịch</th>
                    <th>Danh mục</th>
                    <th>Thời gian</th>
                    <th class="t-right">Số tiền</th>
                    <th class="t-right" style="width:110px">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $day => $items):
                    $dayNet = 0;
                    foreach ($items as $t) $dayNet += (strtoupper($t['type']) === 'INCOME' ? 1 : -1) * (float) $t['amount']; ?>
                    <tr class="day-row">
                        <td colspan="3"><strong><i class="fa-regular fa-calendar"></i> <?= dateVN($day) ?></strong>
                            <span class="t-muted small"> · <?= count($items) ?> giao dịch</span></td>
                        <td class="t-right"><span class="money small <?= $dayNet >= 0 ? 't-green' : 't-red' ?>"><?= money($dayNet, true) ?></span></td>
                        <td></td>
                    </tr>
                    <?php foreach ($items as $t):
                        $isIn = strtoupper($t['type']) === 'INCOME';
                        $cat = $t['category_name_rel'] ?? $t['category'] ?? 'Khác';
                        $desc = trim((string) ($t['description'] ?? '')) ?: $cat;
                        $editJson = json_encode([
                            'id' => (int) $t['id'], 'type' => strtoupper($t['type']), 'amount' => (float) $t['amount'],
                            'category_id' => $t['category_id'] ?? null, 'description' => $t['description'] ?? '',
                            'transaction_date' => $t['transaction_date'] ?? '',
                        ], JSON_UNESCAPED_UNICODE); ?>
                        <tr>
                            <td>
                                <div class="cell-flex">
                                    <span class="cat-icon" style="--c:<?= catColor($cat) ?>"><i class="<?= catIcon($t['category_icon'] ?? null, strtoupper($t['type'])) ?>"></i></span>
                                    <div><strong class="t-ink"><?= e($desc) ?></strong><div class="small t-muted">#<?= (int) $t['id'] ?></div></div>
                                </div>
                            </td>
                            <td><span class="pill <?= $isIn ? 'green' : 'blue' ?>"><?= e($cat) ?></span></td>
                            <td class="small"><?= date('H:i', strtotime($t['transaction_date'])) ?></td>
                            <td class="t-right"><span class="money <?= $isIn ? 't-green' : 't-red' ?>"><?= money(($isIn ? 1 : -1) * abs((float) $t['amount']), true) ?></span></td>
                            <td class="t-right" style="white-space:nowrap">
                                <button type="button" class="btn-icon edit" title="Sửa" data-edit-tx='<?= e($editJson) ?>'><i class="fa-solid fa-pen"></i></button>
                                <form method="post" style="display:inline" data-confirm="Xoá giao dịch #<?= (int) $t['id'] ?>?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tx_id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="return" value="transactions.php">
                                    <button type="submit" class="btn-icon" title="Xoá" data-nobusy><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
$returnTo = 'transactions.php';
include __DIR__ . '/includes/tx_modal.php';
renderFooter();
