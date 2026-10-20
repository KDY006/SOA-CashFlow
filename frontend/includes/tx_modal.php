<?php
/**
 * Modal thêm/sửa giao dịch - dùng chung cho Tổng quan và Thu chi.
 * Cần biến $categories (từ GET /api/categories). Form gửi về transactions.php.
 */
$categories = $categories ?? [];
$returnTo = $returnTo ?? basename($_SERVER['PHP_SELF']);
?>
<div class="modal" id="txModal">
    <div class="modal-dialog">
        <div class="modal-head">
            <span class="p-icon"><i class="fa-solid fa-receipt"></i></span>
            <div style="flex:1">
                <h3 id="txModalTitle">Ghi chép giao dịch</h3>
                <p>Lưu vào Transaction Service · tự kiểm tra ngân sách</p>
            </div>
            <button type="button" class="modal-close" data-close><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="post" action="transactions.php" id="txForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="tx_id" value="">
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <div class="modal-body">
                <div class="field" style="margin-bottom:1rem">
                    <div class="segment">
                        <input type="radio" name="type" id="tExp" value="EXPENSE" checked>
                        <label for="tExp"><i class="fa-solid fa-arrow-up"></i> Khoản chi</label>
                        <input type="radio" name="type" id="tInc" value="INCOME">
                        <label for="tInc"><i class="fa-solid fa-arrow-down"></i> Khoản thu</label>
                    </div>
                </div>

                <div class="field" style="margin-bottom:1rem">
                    <label for="txAmount">Số tiền</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-coins"></i>
                        <input class="input input-money" id="txAmount" name="amount" type="number" min="1000" step="1000" placeholder="0" required>
                        <span class="suffix">VND</span>
                    </div>
                    <div class="quick-amounts" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.2rem">
                        <?php foreach ([50000, 100000, 200000, 500000, 1000000] as $q): ?>
                            <button type="button" class="pill blue" style="border:0;cursor:pointer" onclick="document.getElementById('txAmount').value=<?= $q ?>"><?= shortVnd($q) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="txCategory">Danh mục</label>
                        <select class="select" id="txCategory" name="category_id" required>
                            <?php foreach (['EXPENSE' => 'Chi tiêu', 'INCOME' => 'Thu nhập'] as $t => $label): ?>
                                <optgroup label="<?= $label ?>" data-type="<?= $t ?>">
                                    <?php foreach ($categories as $c): if (strtoupper($c['type'] ?? '') !== $t) continue; ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="txDate">Thời gian</label>
                        <input class="input" type="datetime-local" id="txDate" name="transaction_date" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="field full">
                        <label for="txDesc">Ghi chú</label>
                        <input class="input" id="txDesc" name="description" maxlength="255" placeholder="vd: Ăn trưa cùng đồng nghiệp">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Huỷ</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu giao dịch</button>
            </div>
        </form>
    </div>
</div>
<script>
    (function () {
        const form = document.getElementById('txForm');
        const sel = document.getElementById('txCategory');
        function syncCategories() {
            const type = form.querySelector('input[name=type]:checked').value;
            let firstVisible = null;
            sel.querySelectorAll('optgroup').forEach(g => {
                const show = g.dataset.type === type;
                g.hidden = !show;
                g.querySelectorAll('option').forEach(o => { o.hidden = !show; o.disabled = !show; if (show && !firstVisible) firstVisible = o; });
            });
            if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled && firstVisible) sel.value = firstVisible.value;
        }
        form.querySelectorAll('input[name=type]').forEach(r => r.addEventListener('change', syncCategories));
        syncCategories();

        // Mở modal ở chế độ sửa: <button data-edit-tx='{json}'>
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-edit-tx]');
            const add = e.target.closest('[data-open="txModal"]');
            if (add && !btn) {
                form.reset();
                form.elements.tx_id.value = '';
                document.getElementById('txModalTitle').textContent = 'Ghi chép giao dịch';
                document.getElementById('txDate').value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                syncCategories();
            }
            if (!btn) return;
            const t = JSON.parse(btn.dataset.editTx);
            form.elements.tx_id.value = t.id;
            form.querySelector(`input[name=type][value="${t.type}"]`).checked = true;
            syncCategories();
            form.amount.value = Math.round(t.amount);
            if (t.category_id) sel.value = t.category_id;
            form.description.value = t.description || '';
            form.transaction_date.value = (t.transaction_date || '').replace(' ', 'T').slice(0, 16);
            document.getElementById('txModalTitle').textContent = 'Sửa giao dịch #' + t.id;
            CF.openModal('txModal');
        });
    })();
</script>
