<?php
/**
 * Ngân sách - Transaction Service
 *  GET /api/budgets/progress · POST /api/budgets · PUT/DELETE /api/budgets/{id}
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['budget_id'] ?? 0);

    if ($action === 'delete' && $id) {
        $r = api('DELETE', "/api/budgets/$id");
        $r['ok'] ? flash('success', 'Đã xoá ngân sách.') : flash('danger', 'Không xoá được: ' . $r['error']);
        redirect('budgets.php');
    }

    if ($action === 'save') {
        $period = ($_POST['period'] ?? 'MONTHLY') === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        if (!$start || !$end) {
            $start = $period === 'YEARLY' ? date('Y-01-01') : date('Y-m-01');
            $end = $period === 'YEARLY' ? date('Y-12-31') : date('Y-m-t');
        }
        $payload = [
            'user_id'      => $uid,
            'category_id'  => (int) ($_POST['category_id'] ?? 0),
            'amount_limit' => (float) ($_POST['amount_limit'] ?? 0),
            'period'       => $period,
            'start_date'   => $start,
            'end_date'     => $end,
        ];
        if ($payload['amount_limit'] <= 0 || !$payload['category_id']) {
            flash('danger', 'Vui lòng chọn danh mục và nhập hạn mức hợp lệ.');
            redirect('budgets.php');
        }
        $r = $id ? api('PUT', "/api/budgets/$id", $payload) : api('POST', '/api/budgets', $payload);
        $r['ok'] ? flash('success', $id ? 'Đã cập nhật ngân sách.' : 'Đã thiết lập ngân sách mới ' . vnd($payload['amount_limit']) . '.')
                 : flash('danger', 'Không lưu được ngân sách: ' . $r['error']);
        redirect('budgets.php');
    }
    redirect('budgets.php');
}

$rProg = api('GET', "/api/budgets/progress?user_id=$uid");
$budgets = apiData($rProg, []);
$errors = collectErrors($rProg);
$categories = loadCategories();
$expenseCats = array_values(array_filter($categories, fn($c) => strtoupper($c['type'] ?? '') === 'EXPENSE'));

$totLimit = array_sum(array_map(fn($b) => (float) $b['amount_limit'], $budgets));
$totSpent = array_sum(array_map(fn($b) => (float) $b['spent'], $budgets));
$totPct = $totLimit > 0 ? round($totSpent / $totLimit * 100, 1) : 0;
$nOver = count(array_filter($budgets, fn($b) => $b['status'] === 'EXCEEDED'));
$nWarn = count(array_filter($budgets, fn($b) => $b['status'] === 'WARNING'));

renderHeader('Ngân sách', 'budgets');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    'Budget Cap',
    'SMART SPENDING LIMIT',
    '9704 •••• •••• ' . (substr((string)$uid, -4) ?: '8888'),
    $holder,
    'ĐÃ CHI TIÊU (' . $totPct . '%)',
    shortVnd($totSpent),
    'TỔNG HẠN MỨC',
    shortVnd($totLimit),
    'VISA',
    $totPct >= 100 ? 'visa' : 'napas'
);

$heroDesc = 'Thiết lập hạn mức chi tiêu cho từng danh mục sinh hoạt. Hệ thống tự động kích hoạt cảnh báo khi đạt ngưỡng <strong>80%</strong> và tức thời phát tín hiệu SSE khi vượt hạn mức.'
    . '<div class="luxury-hero-actions">'
    . '<button class="btn-hero-pri" data-open="budgetModal" data-new-budget><i class="fa-solid fa-plus"></i> Thiết lập ngân sách mới</button>'
    . '<a class="btn-hero-sec" href="transactions.php"><i class="fa-solid fa-receipt"></i> Xem các khoản chi</a>'
    . '</div>';

pageHero('Transaction & Analytics Service',
    'Ngân Sách & Quản Trị Chi Tiêu',
    $heroDesc,
    $cardWidget);
?>

<section class="grid grid-4 mb">
    <div class="stat blue anim-fade-in anim-delay-1">
        <div class="stat-top"><span class="stat-label">Tổng hạn mức ngân sách</span><span class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></span></div>
        <div class="stat-value"><?= money($totLimit) ?></div>
        <div class="stat-foot"><span class="pill blue"><?= count($budgets) ?> ngân sách</span> đang theo dõi</div>
    </div>
    <div class="stat red anim-fade-in anim-delay-2">
        <div class="stat-top"><span class="stat-label">Tổng tiền đã chi</span><span class="stat-icon"><i class="fa-solid fa-fire"></i></span></div>
        <div class="stat-value t-red"><?= money($totSpent) ?></div>
        <div class="stat-foot">Đạt <?= $totPct ?>% trần hạn mức</div>
    </div>
    <div class="stat green anim-fade-in anim-delay-3">
        <div class="stat-top"><span class="stat-label">Hạn mức khả dụng</span><span class="stat-icon"><i class="fa-solid fa-shield-heart"></i></span></div>
        <div class="stat-value t-green"><?= money(max(0, $totLimit - $totSpent)) ?></div>
        <div class="stat-foot"><i class="fa-solid fa-circle-check" style="color:#059669"></i> An toàn tiếp tục chi</div>
    </div>
    <div class="stat amber anim-fade-in anim-delay-4">
        <div class="stat-top"><span class="stat-label">Cảnh báo chạm ngưỡng</span><span class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></span></div>
        <div class="stat-value"><?= $nOver + $nWarn ?></div>
        <div class="stat-foot"><span class="pill red"><?= $nOver ?> vượt</span> <span class="pill amber"><?= $nWarn ?> sắp chạm</span></div>
    </div>
</section>

<?php if (!$budgets): ?>
    <section class="panel"><?= emptyState('fa-bullseye', 'Bạn chưa đặt ngân sách nào', 'Bấm "Tạo ngân sách" để bắt đầu kiểm soát chi tiêu.') ?></section>
<?php else: ?>
    <section class="grid grid-3">
        <?php foreach ($budgets as $b):
            $pct = (float) $b['percentage'];
            $st = $b['status'];
            $c = $st === 'EXCEEDED' ? '#ef4444' : ($st === 'WARNING' ? '#f59e0b' : '#10b981');
            $cls = $st === 'EXCEEDED' ? 'over' : ($st === 'WARNING' ? 'warn' : '');
            $daysLeft = max(0, (int) ceil((strtotime($b['end_date'] . ' 23:59:59') - time()) / 86400));
            $perDay = $daysLeft > 0 ? $b['remaining'] / $daysLeft : 0;
            $editJson = json_encode([
                'id' => (int) $b['budget_id'], 'category_id' => (int) $b['category_id'], 'amount_limit' => (float) $b['amount_limit'],
                'period' => $b['period'], 'start_date' => $b['start_date'], 'end_date' => $b['end_date'],
            ]); ?>
            <article class="panel budget-card">
                <div class="budget-top">
                    <div class="ring" style="--p:<?= min(100, $pct) ?>;--c:<?= $c ?>"><b><?= round($pct) ?>%</b></div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
                            <span class="cat-icon" style="--c:<?= catColor($b['category_name']) ?>;width:30px;height:30px;font-size:.8rem"><i class="<?= catIcon($b['category_icon'] ?? null) ?>"></i></span>
                            <h3><?= e($b['category_name']) ?></h3>
                        </div>
                        <span class="pill <?= $st === 'EXCEEDED' ? 'red' : ($st === 'WARNING' ? 'amber' : 'green') ?>">
                            <?= $st === 'EXCEEDED' ? 'Vượt hạn mức' : ($st === 'WARNING' ? 'Sắp chạm hạn mức' : 'An toàn') ?>
                        </span>
                        <div class="small t-muted" style="margin-top:.35rem">
                            <i class="fa-regular fa-calendar"></i> <?= dateVN($b['start_date']) ?> – <?= dateVN($b['end_date']) ?>
                            · <?= $b['period'] === 'YEARLY' ? 'Năm' : 'Tháng' ?>
                        </div>
                    </div>
                </div>
                <div class="progress <?= $cls ?>" style="margin-bottom:1rem"><span data-width="<?= $pct ?>"></span></div>
                <div class="kv">
                    <div><span>Hạn mức</span><b><?= shortVnd($b['amount_limit']) ?></b></div>
                    <div><span>Đã chi</span><b class="t-red"><?= shortVnd($b['spent']) ?></b></div>
                    <div><span>Còn lại</span><b class="t-green"><?= shortVnd($b['remaining']) ?></b></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem">
                    <span class="small t-muted">
                        <?php if ($st === 'EXCEEDED'): ?>
                            <i class="fa-solid fa-circle-exclamation t-red"></i> Vượt <?= money($b['spent'] - $b['amount_limit']) ?>
                        <?php elseif ($daysLeft > 0): ?>
                            <i class="fa-solid fa-lightbulb t-amber"></i> ~<?= shortVnd($perDay) ?>/ngày · còn <?= $daysLeft ?> ngày
                        <?php else: ?>
                            <i class="fa-regular fa-clock"></i> Đã kết thúc kỳ
                        <?php endif; ?>
                    </span>
                    <span style="white-space:nowrap">
                        <button type="button" class="btn-icon edit" title="Sửa" data-edit-budget='<?= e($editJson) ?>'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline" data-confirm="Xoá ngân sách <?= e($b['category_name']) ?>?">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="budget_id" value="<?= (int) $b['budget_id'] ?>">
                            <button type="submit" class="btn-icon" title="Xoá" data-nobusy><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- Modal tạo / sửa ngân sách -->
<div class="modal" id="budgetModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon amber"><i class="fa-solid fa-bullseye"></i></span>
            <div style="flex:1"><h3 id="budgetTitle">Tạo ngân sách</h3><p>Đặt hạn mức chi tiêu cho một danh mục</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" id="budgetForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="budget_id" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label for="bCat">Danh mục chi tiêu</label>
                        <select class="select" id="bCat" name="category_id" required>
                            <?php foreach ($expenseCats as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="bLimit">Hạn mức</label>
                        <div class="input-wrap"><i class="fa-solid fa-coins"></i>
                            <input class="input input-money" id="bLimit" name="amount_limit" type="number" min="10000" step="10000" required placeholder="0">
                            <span class="suffix">VND</span>
                        </div>
                    </div>
                    <div class="field">
                        <label for="bPeriod">Chu kỳ</label>
                        <select class="select" id="bPeriod" name="period">
                            <option value="MONTHLY">Hàng tháng</option>
                            <option value="YEARLY">Hàng năm</option>
                        </select>
                    </div>
                    <div class="field"></div>
                    <div class="field">
                        <label for="bStart">Từ ngày</label>
                        <input class="input" type="date" id="bStart" name="start_date" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="field">
                        <label for="bEnd">Đến ngày</label>
                        <input class="input" type="date" id="bEnd" name="end_date" value="<?= date('Y-m-t') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu ngân sách</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const f = document.getElementById('budgetForm');
    const pad = n => String(n).padStart(2, '0');
    function setPeriodDates() {
        const d = new Date(), y = d.getFullYear(), m = d.getMonth();
        if (f.period.value === 'YEARLY') { f.start_date.value = `${y}-01-01`; f.end_date.value = `${y}-12-31`; }
        else { f.start_date.value = `${y}-${pad(m + 1)}-01`; f.end_date.value = `${y}-${pad(m + 1)}-${pad(new Date(y, m + 1, 0).getDate())}`; }
    }
    f.period.addEventListener('change', setPeriodDates);
    document.addEventListener('click', e => {
        if (e.target.closest('[data-new-budget]')) {
            f.reset(); f.budget_id.value = ''; setPeriodDates();
            document.getElementById('budgetTitle').textContent = 'Tạo ngân sách';
        }
        const b = e.target.closest('[data-edit-budget]');
        if (!b) return;
        const d = JSON.parse(b.dataset.editBudget);
        f.budget_id.value = d.id; f.category_id.value = d.category_id; f.amount_limit.value = Math.round(d.amount_limit);
        f.period.value = d.period || 'MONTHLY'; f.start_date.value = d.start_date; f.end_date.value = d.end_date;
        document.getElementById('budgetTitle').textContent = 'Sửa ngân sách';
        CF.openModal('budgetModal');
    });
})();
</script>

<?php renderFooter(); ?>
