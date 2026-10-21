<?php
/**
 * Ngân hàng - Integration Service (Go)
 *  Tài khoản liên kết · Biến động số dư (webhook) · Tỷ giá ngoại tệ · Kiểm thử đồng thời
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'link_account') {
        $payload = [
            'bank_code'       => strtoupper(trim((string) ($_POST['bank_code'] ?? ''))),
            'account_no'      => preg_replace('/\D/', '', (string) ($_POST['account_no'] ?? '')),
            'holder_name'     => mb_strtoupper(trim((string) ($_POST['holder_name'] ?? ''))),
            'initial_balance' => (float) ($_POST['initial_balance'] ?? 0),
        ];
        $r = api('POST', '/api/integrations/accounts', $payload);
        if ($r['ok']) {
            flash('success', 'Đã liên kết tài khoản ' . bankName($payload['bank_code']) . ' ••' . substr($payload['account_no'], -4) . '.');
            redirect('banking.php?acc=' . (int) ($r['body']['data']['id'] ?? 0));
        }
        flash('danger', $r['status'] === 409 ? 'Tài khoản này đã được liên kết trước đó.' : 'Không liên kết được: ' . $r['error']);
    } elseif ($action === 'webhook') {
        $acc = (int) ($_POST['acc'] ?? 0);
        $payload = [
            'provider'     => strtoupper((string) ($_POST['bank_code'] ?? '')),
            'external_ref' => 'WEB' . date('YmdHis') . random_int(100, 999),
            'bank_code'    => strtoupper((string) ($_POST['bank_code'] ?? '')),
            'account_no'   => (string) ($_POST['account_no'] ?? ''),
            'direction'    => ($_POST['direction'] ?? 'CREDIT') === 'DEBIT' ? 'DEBIT' : 'CREDIT',
            'amount'       => (float) ($_POST['amount'] ?? 0),
            'category'     => trim((string) ($_POST['category'] ?? '')),
            'description'  => trim((string) ($_POST['description'] ?? '')),
            'occurred_at'  => date('Y-m-d H:i:s'),
        ];
        $r = api('POST', '/api/integrations/webhooks/banking', $payload);
        $btx = is_array($r['body']) ? ($r['body']['data']['transaction'] ?? []) : [];
        $st  = $btx['status'] ?? '';
        if ($st === 'REJECTED') {
            // Go: số dư không đủ -> vẫn lưu bản ghi REJECTED, trả HTTP 422
            flash('warning', 'Giao dịch bị từ chối: số dư không đủ (đã lưu trạng thái REJECTED).');
        } elseif ($r['ok'] && !empty($r['body']['data']['duplicate'])) {
            flash('info', 'Giao dịch đã được ghi nhận trước đó, hệ thống bỏ qua (idempotency).');
        } elseif ($r['ok']) {
            $syncMsg = $st === 'SYNCED' ? ' Đã đồng bộ sang Thu chi.' : ($st === 'FAILED' ? ' Đồng bộ lỗi, có thể bấm “Thử lại”.' : ' Hệ thống sẽ tự đồng bộ sang Thu chi.');
            flash('success', 'Đã nhận biến động ' . ($payload['direction'] === 'CREDIT' ? '+' : '−') . vnd($payload['amount']) . '. Số dư mới ' . vnd((float) ($btx['balance_after'] ?? 0)) . '.' . $syncMsg);
        } else {
            flash('danger', 'Webhook thất bại: ' . $r['error']);
        }
        redirect('banking.php?acc=' . $acc);
    } elseif ($action === 'delete_account') {
        $accId = (int) ($_POST['account_id'] ?? 0);
        $r = api('DELETE', '/api/integrations/accounts/' . $accId);
        if ($r['ok']) {
            $delAcc = is_array($r['body']) ? ($r['body']['data'] ?? []) : [];
            $bName = !empty($delAcc['bank_code']) ? bankName($delAcc['bank_code']) : 'ngân hàng';
            $last4 = !empty($delAcc['account_no']) ? ' ••' . substr((string) $delAcc['account_no'], -4) : '';
            flash('success', "Đã xoá và huỷ liên kết tài khoản {$bName}{$last4} thành công.");
        } else {
            flash('danger', 'Không thể xoá tài khoản ngân hàng: ' . $r['error']);
        }
        redirect('banking.php');
    }
    redirect('banking.php');
}

['acc' => $rAcc, 'rates' => $rRates] = apiAll([
    'acc'   => ['GET', '/api/integrations/accounts'],
    'rates' => ['GET', '/api/integrations/rates'],
]);
$accounts = apiData($rAcc, []);
$rates = apiData($rRates, []);

$selId = (int) ($_GET['acc'] ?? 0);
$sel = null;
foreach ($accounts as $a) if ((int) $a['id'] === $selId) $sel = $a;
if (!$sel && $accounts) $sel = $accounts[0];

$bankTx = [];
$rBtx = null;
if ($sel) {
    $statusF = in_array($_GET['status'] ?? '', ['RECEIVED', 'SYNCING', 'SYNCED', 'FAILED', 'REJECTED'], true) ? $_GET['status'] : '';
    $rBtx = api('GET', '/api/integrations/accounts/' . (int) $sel['id'] . '/transactions?limit=50' . ($statusF ? "&status=$statusF" : ''));
    $bankTx = apiData($rBtx, []);
}
$errors = collectErrors($rAcc, $rRates, ...($rBtx ? [$rBtx] : []));
$totBalance = array_sum(array_map(fn($a) => (float) $a['balance'], $accounts));
$themes = ['theme-ocean', 'theme-emerald', 'theme-plum', 'theme-rose', 'theme-amber', ''];
$categories = loadCategories();

const TX_STATUS = [
    'RECEIVED' => ['Đã nhận', 'blue', 'fa-inbox'],
    'SYNCING'  => ['Đang đồng bộ', 'violet', 'fa-rotate'],
    'SYNCED'   => ['Đã đồng bộ', 'green', 'fa-circle-check'],
    'FAILED'   => ['Lỗi đồng bộ', 'red', 'fa-circle-exclamation'],
    'REJECTED' => ['Từ chối', 'amber', 'fa-ban'],
];

renderHeader('Ngân hàng', 'banking');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    'Banking Hub',
    'OPEN BANKING GATEWAY',
    $sel ? (bankName($sel['bank_code'] ?? 'VCB') . ' •••• ' . substr((string)($sel['account_no'] ?? '8888'), -4)) : '9704 •••• •••• 8888',
    $holder,
    'NGÂN HÀNG LIÊN KẾT',
    count($accounts) . ' Banks',
    'TỔNG SỐ DƯ LIÊN KẾT',
    vnd($totBalance),
    'NAPAS',
    'napas'
);

$heroDesc = 'Trung tâm kết nối Open Banking và tỷ giá ngoại tệ thời gian thực. Tích hợp Webhook mô phỏng biến động tài khoản ngân hàng (Vietcombank, MB Bank, Techcombank, VPBank, ACB).'
    . '<div class="luxury-hero-actions">'
    . '<button class="btn-hero-pri" data-open="linkModal"><i class="fa-solid fa-link"></i> Liên kết tài khoản</button>'
    . ($sel ? '<button class="btn-hero-sec" data-open="hookModal"><i class="fa-solid fa-bolt"></i> Giả lập biến động Webhook</button>' : '')
    . '</div>';

pageHero('Integration Service · Go / Node',
    'Tích Hợp Ngân Hàng & Tỷ Giá',
    $heroDesc,
    $cardWidget);
$knownBankCodes = ['vcb', 'tcb', 'mb', 'bidv', 'acb', 'vpb', 'tpb', 'vtb', 'agr', 'stb', 'momo', 'zalopay'];
?>

<!-- Thẻ tài khoản & Phân bổ số dư từng ngân hàng -->
<section class="panel bank-portfolio-panel mb">
    <div class="panel-head bank-portfolio-head">
        <div class="panel-title">
            <span class="p-icon"><i class="fa-solid fa-building-columns"></i></span>
            <div>
                <h2>Tài khoản liên kết & Số dư ngân hàng</h2>
                <p>Bấm vào thẻ ngân hàng bất kỳ để lọc lịch sử biến động số dư theo thời gian thực</p>
            </div>
        </div>
        <div class="bank-portfolio-actions">
            <?php if ($accounts): ?>
                <div class="linked-total-pill" title="Tổng số dư khả dụng trên <?= count($accounts) ?> tài khoản liên kết">
                    <span class="lt-meta"><i class="fa-solid fa-vault"></i> Tổng số dư (<?= count($accounts) ?> TK)</span>
                    <strong class="lt-amount"><?= money($totBalance) ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($sel): ?>
                <button class="btn btn-ghost btn-sm" data-open="hookModal"><i class="fa-solid fa-bolt"></i> Giả lập biến động</button>
                <button type="button" class="btn btn-danger-soft btn-sm"
                    data-del-acc="<?= (int) $sel['id'] ?>"
                    data-del-bank="<?= e(bankName($sel['bank_code'])) ?>"
                    data-del-code="<?= e($sel['bank_code']) ?>"
                    data-del-no="<?= e($sel['account_no']) ?>"
                    data-del-holder="<?= e($sel['holder_name']) ?>"
                    data-del-bal="<?= e(vnd($sel['balance'])) ?>"
                    title="Xoá / Huỷ liên kết tài khoản ngân hàng đang chọn (<?= e(bankName($sel['bank_code'])) ?>)">
                    <i class="fa-regular fa-trash-can"></i> Xoá ngân hàng hiện tại
                </button>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" data-open="linkModal"><i class="fa-solid fa-plus"></i> Thêm tài khoản</button>
        </div>
    </div>

    <?php if (!$accounts): ?>
        <?= emptyState('fa-credit-card', 'Chưa liên kết tài khoản nào', 'Liên kết tài khoản để nhận biến động số dư tự động.') ?>
    <?php else: ?>
        <?php if ($totBalance > 0 && count($accounts) > 1): ?>
            <!-- Thanh phân bổ tỷ trọng số dư theo từng ngân hàng -->
            <div class="bank-alloc-strip">
                <div class="bank-alloc-bar" role="img" aria-label="Tỷ trọng số dư các ngân hàng">
                    <?php foreach ($accounts as $a):
                        $bal = max(0, (float) $a['balance']);
                        $pct = $totBalance > 0 ? round($bal / $totBalance * 100, 1) : 0;
                        if ($pct <= 0) continue;
                        $clr = bankBrandColor($a['bank_code']);
                    ?>
                        <div class="bank-alloc-seg" data-width="<?= max($pct, 3) ?>" style="width:0%; --seg-clr:<?= $clr ?>" title="<?= e(bankName($a['bank_code'])) ?>: <?= vnd($bal) ?> (<?= $pct ?>%)"></div>
                    <?php endforeach; ?>
                </div>
                <div class="bank-alloc-legend">
                    <?php foreach ($accounts as $a):
                        $active = $sel && (int) $sel['id'] === (int) $a['id'];
                        $bal = (float) $a['balance'];
                        $pct = $totBalance > 0 ? round(max(0, $bal) / $totBalance * 100, 1) : 0;
                        $clr = bankBrandColor($a['bank_code']);
                    ?>
                        <a href="banking.php?acc=<?= (int) $a['id'] ?>" class="alloc-chip <?= $active ? 'is-active' : '' ?>" style="--seg-clr:<?= $clr ?>">
                            <?= bankLogoSvg($a['bank_code'], 20) ?>
                            <span class="alloc-name"><?= e(bankName($a['bank_code'])) ?></span>
                            <span class="alloc-bal"><?= money($bal) ?></span>
                            <span class="alloc-pct"><?= number_format($pct, 1, ',', '.') ?>%</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card-rail">
            <?php foreach ($accounts as $i => $a):
                $active = $sel && (int) $sel['id'] === (int) $a['id'];
                $codeClean = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $a['bank_code']));
                $isWallet = in_array(strtoupper($a['bank_code']), ['MOMO', 'ZALOPAY'], true);
                $themeFallback = in_array($codeClean, $knownBankCodes, true) ? '' : $themes[$i % count($themes)];
                $accClean = trim((string) $a['account_no']);
                $last4 = substr($accClean, -4) ?: '8888';
                $prefix2 = strlen($accClean) >= 6 ? substr($accClean, 0, 2) . '••' : '••••';
                $sharePct = $totBalance > 0 ? round(max(0, (float) $a['balance']) / $totBalance * 100, 1) : 0;
            ?>
                <a href="banking.php?acc=<?= (int) $a['id'] ?>"
                   class="bank-card b-<?= e($codeClean) ?> <?= $themeFallback ?> <?= $active ? 'is-active' : '' ?>"
                   style="--i:<?= (int) $i ?>"
                   data-tilt-card
                   title="<?= e(bankName($a['bank_code'])) ?> · Số TK: <?= e($accClean) ?> · Số dư: <?= e(vnd($a['balance'])) ?>">
                    <?= bankWatermarkSvg($a['bank_code']) ?>
                    <span class="card-guilloche"></span>
                    <span class="card-spotlight"></span>
                    <span class="card-glint"></span>
                    <span class="card-ambient-orb"></span>

                    <!-- Hàng 1: App Icon Ngân hàng chính thức + Tên ngân hàng + Trạng thái -->
                    <div class="card-brand-header">
                        <div class="card-brand-logo-box">
                            <?= bankLogoSvg($a['bank_code'], 48) ?>
                        </div>
                        <div class="card-brand-info">
                            <div class="card-bank-name"><?= e(bankName($a['bank_code'])) ?></div>
                            <div class="card-bank-sub">
                                <i class="fa-solid fa-circle-check"></i>
                                <?= $isWallet ? 'Ví điện tử' : 'TK thanh toán' ?> · <strong>ID #<?= (int) $a['id'] ?></strong>
                            </div>
                        </div>
                        <div class="card-brand-badges">
                            <?php if ($active): ?>
                                <span class="card-badge-active"><span class="active-pulse-dot"></span>Đang chọn · #<?= (int) $a['id'] ?></span>
                            <?php else: ?>
                                <span class="card-badge-code"><?= e($a['bank_code']) ?> · #<?= (int) $a['id'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hàng 2: Số dư khả dụng nổi bật trung tâm + Thanh tỷ trọng động -->
                    <div class="card-hero-balance">
                        <div class="card-cap-label">SỐ DƯ KHẢ DỤNG</div>
                        <div class="card-bal-line">
                            <div class="card-bal-val"><?= money($a['balance']) ?></div>
                            <?php if ($totBalance > 0): ?>
                                <span class="card-share-pill" title="Chiếm <?= number_format($sharePct, 1, ',', '.') ?>% tổng số dư"><?= number_format($sharePct, 1, ',', '.') ?>%</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($totBalance > 0): ?>
                            <div class="card-mini-progress" title="Tỷ trọng <?= number_format($sharePct, 1, ',', '.') ?>% tổng số dư">
                                <span data-width="<?= max($sharePct, 4) ?>"></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Hàng 3: Chủ tài khoản & Số tài khoản kèm nút Sao chép 1 chạm -->
                    <div class="card-bottom-row">
                        <div class="card-holder-col">
                            <div class="card-cap-label">CHỦ TÀI KHOẢN</div>
                            <div class="card-holder-val"><?= e($a['holder_name']) ?></div>
                        </div>
                        <div class="card-acc-col">
                            <div class="card-cap-label">SỐ TÀI KHOẢN</div>
                            <div class="card-acc-val">
                                <span><?= e($prefix2) ?> <?= e($last4) ?></span>
                                <span class="card-copy-acc" data-copy-acc="<?= e($accClean) ?>" title="Sao chép số tài khoản <?= e($accClean) ?>" role="button" tabindex="0">
                                    <i class="fa-regular fa-copy"></i>
                                </span>
                                <span class="card-del-acc"
                                    data-del-acc="<?= (int) $a['id'] ?>"
                                    data-del-bank="<?= e(bankName($a['bank_code'])) ?>"
                                    data-del-code="<?= e($a['bank_code']) ?>"
                                    data-del-no="<?= e($accClean) ?>"
                                    data-del-holder="<?= e($a['holder_name']) ?>"
                                    data-del-bal="<?= e(vnd($a['balance'])) ?>"
                                    title="Huỷ liên kết tài khoản <?= e(bankName($a['bank_code'])) ?> (<?= e($accClean) ?>)"
                                    role="button" tabindex="0">
                                    <i class="fa-regular fa-trash-can"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

            <!-- Ô thêm nhanh tài khoản ngân hàng mới -->
            <button type="button" class="bank-card-add-slot" data-open="linkModal" title="Liên kết thêm tài khoản ngân hàng hoặc ví điện tử mới">
                <span class="add-slot-icon"><i class="fa-solid fa-plus"></i></span>
                <strong class="add-slot-title">Liên kết thêm ngân hàng</strong>
                <span class="add-slot-sub">Hỗ trợ Vietcombank, MB, Techcombank, BIDV, ACB, MoMo, ZaloPay...</span>
            </button>
        </div>
    <?php endif; ?>
</section>

<section class="grid grid-main mb">
    <!-- Biến động -->
    <div class="panel">
        <div class="panel-head" style="flex-wrap:wrap">
            <div class="panel-title">
                <span class="p-icon green"><i class="fa-solid fa-money-bill-transfer"></i></span>
                <div><h2>Biến động số dư</h2><p><?= $sel ? e(bankName($sel['bank_code'])) . ' · ' . e(maskAccount($sel['account_no'])) : 'Chọn một tài khoản' ?></p></div>
            </div>
            <?php if ($sel): ?>
                <div class="tabs">
                    <a href="banking.php?acc=<?= (int) $sel['id'] ?>" class="<?= empty($statusF) ? 'active' : '' ?>">Tất cả</a>
                    <?php foreach (['SYNCED', 'RECEIVED', 'FAILED', 'REJECTED'] as $s): ?>
                        <a href="banking.php?acc=<?= (int) $sel['id'] ?>&status=<?= $s ?>" class="<?= ($statusF ?? '') === $s ? 'active' : '' ?>"><?= TX_STATUS[$s][0] ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$sel): ?>
            <?= emptyState('fa-money-bill-transfer', 'Chưa có tài khoản') ?>
        <?php elseif (!$bankTx): ?>
            <?= emptyState('fa-inbox', 'Chưa có biến động nào', 'Bấm "Giả lập biến động" để gửi thử một webhook từ ngân hàng.') ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nội dung</th><th>Trạng thái</th><th class="t-right">Số tiền</th><th class="t-right">Số dư sau</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($bankTx as $t):
                        $in = $t['direction'] === 'CREDIT';
                        [$sl, $sc, $si] = TX_STATUS[$t['status']] ?? [$t['status'], 'blue', 'fa-circle']; ?>
                        <tr id="btx-<?= (int) $t['id'] ?>">
                            <td><div class="cell-flex">
                                <span class="cat-icon" style="--c:<?= $in ? '#10b981' : '#ef4444' ?>"><i class="fa-solid <?= $in ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i></span>
                                <div><strong class="t-ink"><?= e($t['description'] ?: ($t['category'] ?: ($in ? 'Tiền vào' : 'Tiền ra'))) ?></strong>
                                    <div class="small t-muted"><?= dateVN($t['occurred_at'], true) ?> · <?= e($t['external_ref']) ?><?= $t['transaction_ref'] ? ' · TX#' . (int) $t['transaction_ref'] : '' ?></div></div>
                            </div></td>
                            <td><span class="pill <?= $sc ?>" title="<?= e($t['last_error'] ?? '') ?>"><i class="fa-solid <?= $si ?>"></i> <?= $sl ?></span>
                                <?php if (!empty($t['last_error']) && $t['status'] === 'FAILED'): ?><div class="small t-red" style="max-width:220px"><?= e($t['last_error']) ?></div><?php endif; ?></td>
                            <td class="t-right"><span class="money <?= $in ? 't-green' : 't-red' ?>"><?= money(($in ? 1 : -1) * abs((float) $t['amount']), true) ?></span></td>
                            <td class="t-right money small"><?= money($t['balance_after']) ?></td>
                            <td class="t-right">
                                <?php if ($t['status'] === 'FAILED'): ?>
                                    <button class="btn-icon edit" title="Thử đồng bộ lại" data-retry="<?= (int) $t['id'] ?>"><i class="fa-solid fa-rotate-right"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tỷ giá -->
    <div class="stack">
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="p-icon amber"><i class="fa-solid fa-coins"></i></span>
                    <div><h3>Tỷ giá ngoại tệ</h3><p>Bấm vào ngoại tệ để nạp nhanh vào bộ quy đổi</p></div>
                </div>
                <button class="btn btn-ghost btn-sm" id="syncRates"><i class="fa-solid fa-arrows-rotate"></i> Đồng bộ</button>
            </div>
            <div id="ratesList">
                <?php if (!$rates): ?>
                    <?= emptyState('fa-coins', 'Chưa có dữ liệu tỷ giá', 'Bấm "Đồng bộ" để lấy tỷ giá mới nhất.') ?>
                <?php else: foreach ($rates as $r): ?>
                    <div class="rate-row" data-rate-select="<?= (float) $r['rate_to_vnd'] ?>" title="Bấm để quy đổi <?= e($r['currency_code']) ?> sang VND">
                        <span class="flag"><?= e($r['currency_code']) ?></span>
                        <strong><?= e($r['currency_code']) ?> / VND<small>Cập nhật <?= timeAgo($r['updated_at']) ?></small></strong>
                        <span class="money t-ink"><?= number_format((float) $r['rate_to_vnd'], (float) $r['rate_to_vnd'] < 1000 ? 2 : 0, ',', '.') ?> <small class="t-muted" style="font-weight:600">₫</small></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div id="providers" style="margin-top:.8rem"></div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span class="p-icon violet"><i class="fa-solid fa-right-left"></i></span>
                    <div><h3>Quy đổi tiền tệ</h3><p>Theo tỷ giá hiện tại</p></div>
                </div>
            </div>
            <div class="form-grid">
                <div class="field"><label for="cvAmt">Số tiền</label><input class="input" id="cvAmt" type="number" min="0" value="100"></div>
                <div class="field"><label for="cvCur">Ngoại tệ</label>
                    <select class="select" id="cvCur">
                        <?php foreach ($rates as $r): ?><option value="<?= (float) $r['rate_to_vnd'] ?>"><?= e($r['currency_code']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="calc-result" id="calcResultBox"><span>Tương đương</span><b id="cvOut">—</b><p id="cvRev"></p></div>
        </div>
    </div>
</section>

<!-- Kiểm thử đồng thời -->
<?php if ($sel): ?>
<section class="panel">
    <div class="panel-head">
        <div class="panel-title">
            <span class="p-icon red"><i class="fa-solid fa-gauge-high"></i></span>
            <div><h3>Kiểm thử đồng thời (stress test)</h3><p>POST /api/integrations/simulate · bắn nhiều webhook song song, có request trùng để kiểm tra idempotency & khoá số dư</p></div>
        </div>
    </div>
    <form id="simForm" class="toolbar" data-ajax="1" style="flex-wrap:wrap">
        <div class="field"><label for="sEv">Số sự kiện</label><input class="input" id="sEv" name="events" type="number" min="1" max="2000" value="50"></div>
        <div class="field"><label for="sWk">Luồng song song</label><input class="input" id="sWk" name="workers" type="number" min="1" max="200" value="10"></div>
        <div class="field"><label for="sDup">Tỷ lệ trùng</label><input class="input" id="sDup" name="duplicate_rate" type="number" min="0" max="1" step="0.1" value="0.3"></div>
        <div class="field"><label for="sMode">Chế độ</label>
            <select class="select" id="sMode" name="mode"><option value="safe">An toàn (có khoá)</option><option value="unsafe">Không khoá (minh hoạ lỗi)</option></select></div>
        <div class="field"><label for="sSync">Đồng bộ sang Thu chi</label>
            <select class="select" id="sSync" name="sync"><option value="0">Không</option><option value="1">Có</option></select></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-play"></i> Chạy</button>
    </form>
    <div id="simResult"></div>
</section>
<?php endif; ?>

<!-- Modal liên kết -->
<div class="modal" id="linkModal">
    <div class="modal-dialog" style="max-width:560px">
        <div class="modal-head">
            <span class="p-icon"><i class="fa-solid fa-link"></i></span>
            <div style="flex:1"><h3>Liên kết tài khoản ngân hàng</h3><p>Chọn ngân hàng hoặc ví điện tử để kết nối Open Banking</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="link_account">
            <div class="modal-body">
                <div class="field" style="margin-bottom:1rem">
                    <label>Chọn nhanh ngân hàng / ví điện tử</label>
                    <div class="bank-picker-grid" id="bankPickerGrid">
                        <?php foreach (['VCB', 'TCB', 'MB', 'BIDV', 'ACB', 'VPB', 'TPB', 'VTB', 'AGR', 'STB', 'MOMO', 'ZALOPAY'] as $idx => $b): ?>
                            <button type="button" class="bank-pick-tile <?= $idx === 0 ? 'is-selected' : '' ?>" data-pick-bank="<?= $b ?>">
                                <?= bankLogoSvg($b, 36) ?>
                                <span><?= e(bankName($b)) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="lkBank">Mã ngân hàng</label>
                        <select class="select" id="lkBank" name="bank_code">
                            <?php foreach (['VCB', 'TCB', 'MB', 'BIDV', 'ACB', 'VPB', 'TPB', 'VTB', 'AGR', 'STB', 'MOMO', 'ZALOPAY'] as $b): ?>
                                <option value="<?= $b ?>"><?= e(bankName($b)) ?> (<?= $b ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="field"><label for="lkNo">Số tài khoản</label><input class="input" id="lkNo" name="account_no" pattern="\d{6,30}" required placeholder="6–30 chữ số"></div>
                    <div class="field full"><label for="lkName">Chủ tài khoản</label><input class="input" id="lkName" name="holder_name" required style="text-transform:uppercase"
                        value="<?= e(currentUser()['fullName'] ?? '') ?>"></div>
                    <div class="field full"><label for="lkBal">Số dư ban đầu (VND)</label><input class="input input-money" id="lkBal" name="initial_balance" type="number" min="0" value="0"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Liên kết ngay</button>
            </div>
        </form>
    </div>
</div>

<?php if ($sel): ?>
<!-- Modal webhook -->
<div class="modal" id="hookModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon green"><i class="fa-solid fa-bolt"></i></span>
            <div style="flex:1"><h3>Giả lập biến động số dư</h3><p>Ngân hàng gửi webhook → ghi nhận → đồng bộ sang Transaction Service</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="webhook">
            <input type="hidden" name="acc" value="<?= (int) $sel['id'] ?>">
            <input type="hidden" name="bank_code" value="<?= e($sel['bank_code']) ?>">
            <input type="hidden" name="account_no" value="<?= e($sel['account_no']) ?>">
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom:1rem"><i class="fa-solid fa-building-columns"></i>
                    <span><?= e(bankName($sel['bank_code'])) ?> · <?= e($sel['account_no']) ?> · số dư <?= money($sel['balance']) ?></span></div>
                <div class="field" style="margin-bottom:1rem">
                    <div class="segment">
                        <input type="radio" name="direction" id="dCr" value="CREDIT" checked><label for="dCr" class="seg-in"><i class="fa-solid fa-arrow-down"></i> Tiền vào</label>
                        <input type="radio" name="direction" id="dDb" value="DEBIT"><label for="dDb" class="seg-out"><i class="fa-solid fa-arrow-up"></i> Tiền ra</label>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field full"><label for="hAmt">Số tiền (VND)</label><input class="input input-money" id="hAmt" name="amount" type="number" min="1000" step="1000" required></div>
                    <div class="field"><label for="hCat">Danh mục</label>
                        <select class="select" id="hCat" name="category">
                            <option value="">Tự động</option>
                            <?php foreach ($categories as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label for="hDesc">Nội dung</label><input class="input" id="hDesc" name="description" maxlength="255" placeholder="vd: Lương tháng 10"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Gửi webhook</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal xác nhận Xoá / Huỷ liên kết tài khoản ngân hàng -->
<div class="modal" id="deleteBankModal">
    <div class="modal-dialog" style="max-width:490px">
        <div class="modal-head">
            <span class="p-icon red"><i class="fa-solid fa-link-slash"></i></span>
            <div style="flex:1">
                <h3>Huỷ liên kết ngân hàng</h3>
                <p id="delBankApiPath">DELETE /api/integrations/accounts/<?= (int) ($sel['id'] ?? 0) ?></p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" id="deleteBankForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_account">
            <input type="hidden" name="account_id" id="delAccId" value="<?= (int) ($sel['id'] ?? 0) ?>">
            <div class="modal-body">
                <div class="del-bank-preview-card" id="delBankPreview">
                    <div class="del-bank-preview-top">
                        <div class="del-bank-code-badge" id="delBankCode"><?= e($sel['bank_code'] ?? 'VCB') ?></div>
                        <div class="del-bank-meta">
                            <strong id="delBankName"><?= e($sel ? bankName($sel['bank_code']) : 'Ngân hàng') ?></strong>
                            <span id="delBankNo">STK: <?= e($sel['account_no'] ?? '') ?></span>
                        </div>
                        <div class="del-bank-bal">
                            <small>SỐ DƯ HIỆN TẠI</small>
                            <b id="delBankBal"><?= e($sel ? vnd($sel['balance']) : '0 ₫') ?></b>
                        </div>
                    </div>
                    <div class="del-bank-preview-foot">
                        <span><i class="fa-regular fa-user"></i> Chủ TK: <strong id="delBankHolder"><?= e($sel['holder_name'] ?? '') ?></strong></span>
                        <span class="del-status-tag"><i class="fa-solid fa-triangle-exclamation"></i> Sắp gỡ liên kết</span>
                    </div>
                </div>
                <div class="alert alert-danger" style="margin-top:1rem;margin-bottom:0">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Khi xác nhận xoá, thẻ ngân hàng này và lịch sử biến động số dư (webhook) thuộc tài khoản sẽ bị gỡ khỏi Open Banking Gateway. Các giao dịch thu/chi đã đồng bộ trước đó vẫn được giữ nguyên.</span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Giữ lại tài khoản</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Xác nhận xoá ngân hàng</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Chọn nhanh ngân hàng bằng lưới Icon trong Modal Liên kết
    const bankGrid = document.getElementById('bankPickerGrid');
    const bankSelect = document.getElementById('lkBank');
    if (bankGrid && bankSelect) {
        bankGrid.addEventListener('click', (e) => {
            const tile = e.target.closest('[data-pick-bank]');
            if (!tile) return;
            bankGrid.querySelectorAll('.bank-pick-tile').forEach(t => t.classList.remove('is-selected'));
            tile.classList.add('is-selected');
            bankSelect.value = tile.dataset.pickBank;
        });
        bankSelect.addEventListener('change', () => {
            bankGrid.querySelectorAll('.bank-pick-tile').forEach(t => {
                t.classList.toggle('is-selected', t.dataset.pickBank === bankSelect.value);
            });
        });
    }

    // Copy nhanh số tài khoản & Thử đồng bộ lại
    if (!window.__bankingClickBound) {
        window.__bankingClickBound = true;
        document.addEventListener('click', async (e) => {
            const copyBtn = e.target.closest('[data-copy-acc]');
            if (copyBtn) {
                e.preventDefault();
                e.stopPropagation();
                const accNo = copyBtn.dataset.copyAcc || '';
                if (navigator.clipboard && accNo) {
                    navigator.clipboard.writeText(accNo);
                }
                const icon = copyBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fa-solid fa-check';
                    copyBtn.classList.add('copied');
                    setTimeout(() => {
                        icon.className = 'fa-regular fa-copy';
                        copyBtn.classList.remove('copied');
                    }, 1600);
                }
                CF.toast('Đã sao chép số tài khoản', accNo, 'success', 2800);
                return;
            }
            const delBtn = e.target.closest('[data-del-acc]');
            if (delBtn) {
                e.preventDefault();
                e.stopPropagation();
                const idEl = document.getElementById('delAccId');
                const codeEl = document.getElementById('delBankCode');
                const nameEl = document.getElementById('delBankName');
                const noEl = document.getElementById('delBankNo');
                const holderEl = document.getElementById('delBankHolder');
                const balEl = document.getElementById('delBankBal');
                const apiPathEl = document.getElementById('delBankApiPath');
                const accIdVal = delBtn.dataset.delAcc || '';
                if (idEl) idEl.value = accIdVal;
                if (apiPathEl) apiPathEl.textContent = 'DELETE /api/integrations/accounts/' + accIdVal + ' (ID #' + accIdVal + ')';
                if (codeEl) codeEl.textContent = (delBtn.dataset.delCode || 'BANK') + ' #' + accIdVal;
                if (nameEl) nameEl.textContent = delBtn.dataset.delBank || 'Ngân hàng';
                if (noEl) noEl.textContent = 'STK: ' + (delBtn.dataset.delNo || '');
                if (holderEl) holderEl.textContent = delBtn.dataset.delHolder || '';
                if (balEl) balEl.textContent = delBtn.dataset.delBal || '0 ₫';
                const modal = document.getElementById('deleteBankModal');
                if (modal && window.CF && typeof window.CF.openModal === 'function') {
                    window.CF.openModal(modal);
                } else if (modal) {
                    modal.classList.add('open');
                }
                return;
            }
            const b = e.target.closest('[data-retry]');
            if (b) {
                b.disabled = true;
                try {
                    await CF.api('POST', `/api/integrations/bank-transactions/${b.dataset.retry}/retry`);
                    CF.toast('Đã đưa vào hàng đợi đồng bộ', '', 'success');
                    setTimeout(() => location.reload(), 1500);
                } catch (err) {
                    CF.toast('Không thử lại được', err.message, 'danger');
                    b.disabled = false;
                }
            }
        });
    }

    // Quy đổi ngoại tệ + Click vào dòng tỷ giá để nạp nhanh vào bộ quy đổi
    const amt = document.getElementById('cvAmt'), cur = document.getElementById('cvCur');
    const conv = () => {
        if (!cur || !amt) return;
        const rate = parseFloat(cur.value) || 0, a = parseFloat(amt.value) || 0;
        const outEl = document.getElementById('cvOut');
        const revEl = document.getElementById('cvRev');
        if (outEl) outEl.textContent = rate ? CF.vnd(a * rate) : '—';
        if (revEl) revEl.textContent = rate && cur.selectedOptions[0]
            ? `1.000.000 ₫ ≈ ${(1e6 / rate).toLocaleString('vi-VN', { maximumFractionDigits: 2 })} ${cur.selectedOptions[0].text}`
            : '';
    };
    amt && amt.addEventListener('input', conv);
    cur && cur.addEventListener('change', conv);
    conv();

    document.querySelectorAll('[data-rate-select]').forEach(row => {
        row.addEventListener('click', () => {
            if (!cur) return;
            const code = row.dataset.rateSelect;
            const opt = Array.from(cur.options).find(o => o.dataset.code === code || o.text.trim() === code);
            if (opt) {
                cur.value = opt.value;
                conv();
                document.querySelectorAll('[data-rate-select]').forEach(r => r.classList.remove('is-picked'));
                row.classList.add('is-picked');
                const box = document.getElementById('calcResultBox');
                if (box) {
                    box.classList.remove('flash-pop');
                    void box.offsetWidth;
                    box.classList.add('flash-pop');
                }
            }
        });
    });

    // Đồng bộ tỷ giá
    const syncBtn = document.getElementById('syncRates');
    syncBtn && syncBtn.addEventListener('click', async () => {
        CF.busy(syncBtn, true, 'Đang đồng bộ...');
        try {
            const d = await CF.api('POST', '/api/integrations/sync-rates');
            document.getElementById('providers').innerHTML = (d.providers || []).map(p =>
                `<div class="provider"><span><i class="fa-solid ${p.error ? 'fa-circle-xmark t-red' : 'fa-circle-check t-green'}"></i> ${CF.esc(p.provider)}</span>
                 <span class="small ${p.error ? 't-red' : 't-muted'}">${p.error ? CF.esc(p.error) : p.latency_ms + ' ms'}</span></div>`).join('');
            CF.toast('Đã đồng bộ tỷ giá', `${d.providers_ok}/${(d.providers || []).length} nguồn phản hồi`, 'success');
            setTimeout(() => location.reload(), 1600);
        } catch (e) { CF.toast('Đồng bộ thất bại', e.message, 'danger'); }
        finally { CF.busy(syncBtn, false); }
    });

    // Stress test
    const sf = document.getElementById('simForm');
    sf && sf.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = sf.querySelector('button[type=submit]');
        const body = { account_id: <?= (int) ($sel['id'] ?? 0) ?>, events: +sf.events.value, workers: +sf.workers.value,
            duplicate_rate: +sf.duplicate_rate.value, mode: sf.mode.value, sync: sf.sync.value === '1' };
        CF.busy(btn, true, 'Đang chạy...');
        try {
            const r = await CF.api('POST', '/api/integrations/simulate', body);
            const kv = (l, v, c = '') => `<div><span>${l}</span><b class="${c}">${v}</b></div>`;
            document.getElementById('simResult').innerHTML = `
                <div class="alert ${r.consistent ? 'alert-success' : 'alert-danger'}" style="margin-top:1rem">
                    <i class="fa-solid ${r.consistent ? 'fa-shield-halved' : 'fa-bug'}"></i>
                    <span><strong>${r.consistent ? 'Nhất quán' : 'Không nhất quán'}</strong> · chế độ ${CF.esc(r.mode)} · ${r.elapsed_ms} ms</span></div>
                <div class="kv" style="grid-template-columns:repeat(4,1fr)">
                    ${kv('Request gửi', r.requests_sent)}${kv('Ghi nhận', r.created, 't-green')}${kv('Trùng bị chặn', r.duplicates, 't-amber')}${kv('Từ chối', r.rejected)}
                    ${kv('Số dư trước', CF.vnd(r.balance_before))}${kv('Số dư sau', CF.vnd(r.balance_after))}${kv('Kỳ vọng', CF.vnd(r.expected_balance))}${kv('Chênh lệch', CF.vnd(r.difference), r.difference ? 't-red' : 't-green')}
                </div>`;
            CF.toast(r.consistent ? 'Kiểm thử thành công' : 'Phát hiện sai lệch', '', r.consistent ? 'success' : 'warning');
        } catch (err) { CF.toast('Không chạy được', err.message, 'danger'); }
        finally { CF.busy(btn, false); }
    });
});
</script>

<?php renderFooter(); ?>
