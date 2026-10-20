<?php
/**
 * Thông báo - Notification Service (Node.js)
 *  GET /api/notifications · PUT /{id}/read · PUT /read-all · POST /api/notifications · SSE stream
 */
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$uid = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read_all') {
        $r = api('PUT', "/api/notifications/read-all?user_id=$uid", ['userId' => $uid]);
        $r['ok'] ? flash('success', 'Đã đánh dấu tất cả là đã đọc.') : flash('danger', $r['error']);
    } elseif ($action === 'send') {
        $r = api('POST', '/api/notifications', [
            'userId'  => $uid,
            'title'   => trim((string) ($_POST['title'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'type'    => $_POST['type'] ?? 'SYSTEM',
        ]);
        $r['ok'] ? flash('success', 'Đã gửi thông báo. Kiểm tra toast realtime ở góc màn hình!') : flash('danger', 'Không gửi được: ' . $r['error']);
    }
    redirect('notifications.php' . (!empty($_GET['unread']) ? '?unread=1' : ''));
}

$unreadOnly = !empty($_GET['unread']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$rN = api('GET', '/api/notifications?' . http_build_query([
    'user_id' => $uid, 'limit' => $limit, 'offset' => ($page - 1) * $limit, 'unread_only' => $unreadOnly ? 'true' : null,
]));
$body = $rN['ok'] ? $rN['body'] : [];
$items = $body['items'] ?? [];
$total = (int) ($body['total'] ?? count($items));
$unread = (int) ($body['unreadCount'] ?? 0);
$pages = max(1, (int) ceil($total / $limit));
$errors = collectErrors($rN);

const NOTIF_TYPES = [
    'BUDGET_ALERT'     => ['Ngân sách',    'fa-bullseye',           '#f59e0b', '#fffbeb'],
    'BUDGET_EXCEEDED'  => ['Vượt ngân sách', 'fa-fire',             '#ef4444', '#fef2f2'],
    'BUDGET_WARNING'   => ['Ngân sách',    'fa-bullseye',           '#f59e0b', '#fffbeb'],
    'CASHFLOW_ANOMALY' => ['Bất thường',   'fa-triangle-exclamation', '#ef4444', '#fef2f2'],
    'LOAN_REMINDER'    => ['Khoản vay',    'fa-hand-holding-dollar', '#8b5cf6', '#f5f3ff'],
    'BANK_TRANSACTION' => ['Ngân hàng',    'fa-building-columns',   '#0066cc', '#eff6ff'],
    'SYSTEM'           => ['Hệ thống',     'fa-gear',               '#0066cc', '#eff6ff'],
];
function notifType(string $t): array
{
    return NOTIF_TYPES[strtoupper($t)] ?? [ucwords(strtolower(str_replace('_', ' ', $t))), 'fa-bell', '#0066cc', '#eff6ff'];
}

renderHeader('Thông báo', 'notifications');
renderApiErrors($errors);

$user = currentUser();
$holder = strtoupper($user['fullName'] ?? $user['username'] ?? 'USER');
$cardWidget = renderHeroBankCard(
    'SSE Stream',
    'REALTIME NOTIFICATIONS',
    'SSE-PORT •••• 8086',
    $holder,
    'TỔNG THÔNG BÁO',
    $total . ' tin nhắn',
    'CHƯA ĐỌC',
    $unread . ' tin',
    'VISA',
    'visa'
);

$heroDesc = 'Kênh phát sóng thông báo thời gian thực sử dụng Server-Sent Events (SSE) trên nền tảng Node.js / Express. Tức thì nhận cảnh báo chi tiêu vượt ngưỡng, bất thường dòng tiền và đối soát giao dịch.'
    . '<div class="luxury-hero-actions">'
    . '<form method="post" style="display:inline">' . csrfField() . '<input type="hidden" name="action" value="read_all">'
    . '<button class="btn-hero-pri" type="submit"' . ($unread ? '' : ' disabled') . '><i class="fa-solid fa-check-double"></i> Đánh dấu tất cả đã đọc</button></form>'
    . '<button class="btn-hero-sec" data-open="sendModal"><i class="fa-solid fa-paper-plane"></i> Bắn thông báo thử nghiệm</button>'
    . '</div>';

pageHero('Notification Service · Node.js SSE',
    'Trung Tâm Thông Báo Thời Gian Thực',
    $heroDesc,
    $cardWidget);
?>

<section class="panel">
    <div class="panel-head">
        <div class="panel-title">
            <span class="p-icon"><i class="fa-solid fa-bell"></i></span>
            <div><h2>Hộp thư</h2><p>Trang <?= $page ?>/<?= $pages ?></p></div>
        </div>
        <div class="tabs">
            <a href="notifications.php" class="<?= !$unreadOnly ? 'active' : '' ?>">Tất cả</a>
            <a href="notifications.php?unread=1" class="<?= $unreadOnly ? 'active' : '' ?>">Chưa đọc <?= $unread ? "($unread)" : '' ?></a>
        </div>
    </div>

    <div id="liveList"></div>
    <?php if (!$items): ?>
        <div id="emptyBox"><?= emptyState('fa-bell-slash', $unreadOnly ? 'Không còn thông báo chưa đọc' : 'Chưa có thông báo nào', 'Thông báo mới sẽ xuất hiện tại đây ngay lập tức.') ?></div>
    <?php else: foreach ($items as $n): [$tl, $ti, $tc, $tb] = notifType($n['type'] ?? 'SYSTEM'); ?>
        <article class="notif <?= empty($n['isRead']) ? 'unread' : '' ?>" data-id="<?= (int) $n['id'] ?>">
            <span class="notif-icon" style="color:<?= $tc ?>;background:<?= $tb ?>"><i class="fa-solid <?= $ti ?>"></i></span>
            <div class="notif-body">
                <h4><?= e($n['title']) ?></h4>
                <p><?= e($n['message']) ?></p>
                <div class="notif-meta">
                    <span class="pill" style="background:<?= $tb ?>;color:<?= $tc ?>"><?= e($tl) ?></span>
                    <span><i class="fa-regular fa-clock"></i> <?= timeAgo($n['createdAt'] ?? null) ?></span>
                    <?php if (!empty($n['isRead']) && !empty($n['readAt'])): ?><span><i class="fa-solid fa-check"></i> Đã đọc <?= timeAgo($n['readAt']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if (empty($n['isRead'])): ?>
                <button class="btn btn-ghost btn-sm" data-read="<?= (int) $n['id'] ?>" title="Đánh dấu đã đọc"><i class="fa-solid fa-check"></i></button>
            <?php endif; ?>
        </article>
    <?php endforeach; endif; ?>

    <?php if ($pages > 1): ?>
        <div style="display:flex;justify-content:center;gap:.4rem;margin-top:1.4rem">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>" href="notifications.php?page=<?= $p ?><?= $unreadOnly ? '&unread=1' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Modal gửi thử -->
<div class="modal" id="sendModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon"><i class="fa-solid fa-paper-plane"></i></span>
            <div style="flex:1"><h3>Gửi thông báo thử</h3><p>POST /api/notifications → đẩy realtime qua SSE</p></div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="send">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full"><label for="nType">Loại</label>
                        <select class="select" id="nType" name="type">
                            <?php foreach (['SYSTEM', 'BUDGET_ALERT', 'CASHFLOW_ANOMALY', 'LOAN_REMINDER'] as $t): ?><option value="<?= $t ?>"><?= e(notifType($t)[0]) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field full"><label for="nTitle">Tiêu đề</label><input class="input" id="nTitle" name="title" required maxlength="150" value="Xin chào từ CashFlow 👋"></div>
                    <div class="field full"><label for="nMsg">Nội dung</label><textarea class="input" id="nMsg" name="message" rows="3" required>Đây là thông báo thử nghiệm được đẩy realtime qua SSE.</textarea></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Gửi</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const TYPES = <?= json_encode(NOTIF_TYPES, JSON_UNESCAPED_UNICODE) ?>;
    const badge = document.getElementById('notifBadge');
    const setUnread = n => {
        const big = document.getElementById('unreadBig');
        if (big) big.innerHTML = `${n} <small style="font-size:.9rem;opacity:.7">chưa đọc</small>`;
        if (badge) { badge.textContent = n; badge.hidden = n <= 0; }
    };
    let unread = <?= $unread ?>;

    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-read]');
        if (!b) return;
        b.disabled = true;
        try {
            await CF.api('PUT', `/api/notifications/${b.dataset.read}/read?user_id=<?= $uid ?>`, {});
            const card = b.closest('.notif'); card.classList.remove('unread'); b.remove();
            setUnread(unread = Math.max(0, unread - 1));
        } catch (err) { CF.toast('Lỗi', err.message, 'danger'); b.disabled = false; }
    });

    // Thông báo mới (SSE) chèn lên đầu danh sách
    document.addEventListener('cashflow:notification', ev => {
        const n = ev.detail, t = TYPES[(n.type || '').toUpperCase()] || ['Thông báo', 'fa-bell', '#0066cc', '#eff6ff'];
        document.getElementById('emptyBox')?.remove();
        const el = document.createElement('article');
        el.className = 'notif unread';
        el.innerHTML = `<span class="notif-icon" style="color:${t[2]};background:${t[3]}"><i class="fa-solid ${t[1]}"></i></span>
            <div class="notif-body"><h4>${CF.esc(n.title)}</h4><p>${CF.esc(n.message)}</p>
            <div class="notif-meta"><span class="pill" style="background:${t[3]};color:${t[2]}">${CF.esc(t[0])}</span><span><i class="fa-regular fa-clock"></i> vừa xong</span></div></div>
            ${n.id ? `<button class="btn btn-ghost btn-sm" data-read="${n.id}"><i class="fa-solid fa-check"></i></button>` : ''}`;
        document.getElementById('liveList').prepend(el);
        unread++; setTimeout(() => setUnread(unread), 0);
    });

    // Trạng thái kết nối SSE
    const st = document.getElementById('sseState');
    if (st && window.EventSource && window.CASHFLOW?.sseUrl) {
        const probe = new EventSource(window.CASHFLOW.sseUrl);
        probe.addEventListener('connected', () => { st.textContent = 'Đã kết nối'; probe.close(); });
        probe.onopen = () => { st.textContent = 'Đã kết nối'; };
        probe.onerror = () => { st.textContent = 'Mất kết nối'; st.className = 'down'; probe.close(); };
    }
});
</script>

<?php renderFooter(); ?>
