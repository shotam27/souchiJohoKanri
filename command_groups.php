<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$pageTitle = 'コマンド群登録 - 装置情報管理システム';

try {
    $dbType   = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset  = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $conn     = $database->connect();

    // テーブルがなければ作成
    if (!$database->tableExists('command_groups')) {
        require_once __DIR__ . '/admin/init_command_groups_table.php';
    }

    // 装置種別一覧（service_device_type_relationsから取得）
    $deviceTypes = [];
    if ($database->tableExists('service_device_type_relations')) {
        $rows = $database->query(
            "SELECT DISTINCT device_type FROM service_device_type_relations WHERE is_active = 1 ORDER BY device_type"
        );
        $deviceTypes = array_column($rows, 'device_type');
    }
    if (empty($deviceTypes) && $database->tableExists('device_info')) {
        $rows = $database->query(
            "SELECT DISTINCT device_type FROM device_info ORDER BY device_type"
        );
        $deviceTypes = array_column($rows, 'device_type');
    }

    // コマンド群一覧を取得
    $groups = $database->query(
        "SELECT cg.id, cg.group_name, cg.device_type, cg.description,
                COUNT(ci.id) AS item_count
         FROM command_groups cg
         LEFT JOIN command_group_items ci ON ci.command_group_id = cg.id
         GROUP BY cg.id, cg.group_name, cg.device_type, cg.description
         ORDER BY cg.device_type, cg.group_name"
    );

} catch (Exception $e) {
    $initError = $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="main-content">
<div class="page-container">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon black-svg">
            <?php include 'svgs/info.svg'; ?>
        </div>
        コマンド群登録
    </h1>
</div>

<?php if (!empty($initError)): ?>
<div class="alert alert-error"><?= htmlspecialchars($initError) ?></div>
<?php endif; ?>

<!-- 登録フォーム -->
<div class="card mb-4">
    <div class="card-header">
        <h2 class="card-title">新規コマンド群の登録</h2>
    </div>
    <div class="card-body">
        <form id="commandGroupForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="form-stack">
                <div class="form-group">
                    <label class="form-label" for="group_name">コマンド群名 <span class="required">*</span></label>
                    <input type="text" id="group_name" name="group_name" class="form-control" required
                           placeholder="例：初期設定コマンド群">
                </div>
                <div class="form-group">
                    <label class="form-label" for="device_type">対象装置種別 <span class="required">*</span></label>
                    <select id="device_type" name="device_type" class="form-control" required>
                        <option value="">-- 選択してください --</option>
                        <?php foreach ($deviceTypes as $dt): ?>
                        <option value="<?= htmlspecialchars($dt) ?>"><?= htmlspecialchars($dt) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">直接入力...</option>
                    </select>
                    <input type="text" id="device_type_custom" name="device_type_custom" class="form-control mt-1"
                           placeholder="装置種別を直接入力" style="display:none;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="description">説明</label>
                    <input type="text" id="description" name="description" class="form-control"
                           placeholder="任意のメモ">
                </div>
            </div>

            <!-- コマンド行 -->
            <div class="section-label">プロンプト＋コマンド</div>
            <div id="commandItems">
                <!-- 行テンプレート（初期1行） -->
                <div class="command-row" data-index="0">
                    <span class="row-num">1</span>
                    <input type="text" name="prompt[]" class="form-control prompt-input"
                           placeholder="プロンプト例: #" value="#">
                    <input type="text" name="command[]" class="form-control command-input"
                           placeholder="コマンド例: show version">
                    <button type="button" class="btn btn-icon btn-danger remove-row" title="削除">✕</button>
                </div>
            </div>

            <div class="command-actions">
                <button type="button" id="addRowBtn" class="btn btn-secondary">
                    ＋ 行を追加
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">登録する</button>
                <button type="reset" class="btn btn-outline" id="resetBtn">リセット</button>
            </div>

            <div id="formMessage" class="alert" style="display:none;"></div>
        </form>
    </div>
</div>

<!-- 登録済み一覧 -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">登録済みコマンド群</h2>
    </div>
    <div class="card-body" id="groupListArea">
        <?php if (empty($groups)): ?>
        <p class="text-muted">まだ登録されていません。</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>コマンド群名</th>
                    <th>対象装置種別</th>
                    <th>説明</th>
                    <th>行数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="groupTableBody">
            <?php foreach ($groups as $g): ?>
            <tr data-id="<?= (int)$g['id'] ?>">
                <td><?= (int)$g['id'] ?></td>
                <td><strong><?= htmlspecialchars($g['group_name']) ?></strong></td>
                <td><?= htmlspecialchars($g['device_type']) ?></td>
                <td><?= htmlspecialchars($g['description'] ?? '') ?></td>
                <td><?= (int)$g['item_count'] ?> 行</td>
                <td>
                    <button class="btn btn-sm btn-secondary view-group" data-id="<?= (int)$g['id'] ?>">詳細</button>
                    <button class="btn btn-sm btn-primary edit-group" data-id="<?= (int)$g['id'] ?>">編集</button>
                    <button class="btn btn-sm btn-danger delete-group" data-id="<?= (int)$g['id'] ?>" data-name="<?= htmlspecialchars($g['group_name']) ?>">削除</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 詳細モーダル -->
<div id="detailModal" class="modal-overlay" style="display:none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">コマンド群詳細</h3>
            <button type="button" class="modal-close" id="closeModal">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading">読み込み中...</div>
        </div>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal-overlay" style="display:none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3>コマンド群編集</h3>
            <button type="button" class="modal-close" id="closeEditModal">✕</button>
        </div>
        <div class="modal-body">
            <form id="editCommandGroupForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" id="edit_id" name="id" value="">
                <div class="form-stack">
                    <div class="form-group">
                        <label class="form-label" for="edit_group_name">コマンド群名 <span class="required">*</span></label>
                        <input type="text" id="edit_group_name" name="group_name" class="form-control" required placeholder="例：初期設定コマンド群">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_device_type">対象装置種別 <span class="required">*</span></label>
                        <input type="text" id="edit_device_type" name="device_type" class="form-control" required placeholder="例：Router">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_description">説明</label>
                        <input type="text" id="edit_description" name="description" class="form-control" placeholder="任意のメモ">
                    </div>
                </div>
                <div class="section-label">プロンプト＋コマンド</div>
                <div id="editCommandItems"></div>
                <div class="command-actions">
                    <button type="button" id="editAddRowBtn" class="btn btn-secondary">＋ 行を追加</button>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存する</button>
                    <button type="button" class="btn btn-outline" id="cancelEditBtn">キャンセル</button>
                </div>
                <div id="editFormMessage" class="alert" style="display:none;"></div>
            </form>
        </div>
    </div>
</div>

</div><!-- page-container -->
</div><!-- main-content -->

<style>
.form-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.form-row .form-group { flex:1; min-width:180px; }
.form-stack { display:flex; flex-direction:column; gap:12px; margin-bottom:16px; }
.form-stack .form-group { width:100%; }
.section-label { font-weight:600; margin-bottom:8px; color:#374151; }
.command-row {
    display:flex; align-items:center; gap:8px; margin-bottom:8px;
    padding:8px; background:#f9fafb; border-radius:6px; border:1px solid #e5e7eb;
}
.row-num { min-width:24px; text-align:right; color:#6b7280; font-size:13px; }
.prompt-input { max-width:140px; font-family:monospace; }
.command-input { flex:1; font-family:monospace; }
.command-actions { margin:12px 0; }
.form-actions { margin-top:20px; display:flex; gap:10px; }
.btn-icon { padding:4px 8px; font-size:13px; }
.btn-outline { background:#fff; border:1px solid #d1d5db; color:#374151; padding:8px 20px; border-radius:6px; cursor:pointer; }
.btn-outline:hover { background:#f3f4f6; }
.required { color:#ef4444; }
.mt-1 { margin-top:6px; }
.text-muted { color:#6b7280; }
.mb-4 { margin-bottom:24px; }
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center; }
.modal-dialog { background:#fff;border-radius:8px;width:90%;max-width:700px;max-height:85vh;display:flex;flex-direction:column; }
.modal-lg { max-width:750px; }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e5e7eb; }
.modal-header h3 { margin:0;font-size:18px; }
.modal-close { background:none;border:none;font-size:20px;cursor:pointer;color:#6b7280; }
.modal-body { padding:20px;overflow-y:auto; }
.detail-table { width:100%;border-collapse:collapse; }
.detail-table th,.detail-table td { padding:8px 12px;border:1px solid #e5e7eb;text-align:left; }
.detail-table th { background:#f3f4f6;font-size:13px;color:#374151; }
.detail-table td.mono { font-family:monospace;font-size:13px; }
.btn-sm { padding:4px 10px;font-size:13px; }
</style>

<script>
const CSRF = document.querySelector('[name=csrf_token]').value;

/* ---- 直接入力トグル ---- */
document.getElementById('device_type').addEventListener('change', function () {
    const custom = document.getElementById('device_type_custom');
    custom.style.display = this.value === '__custom__' ? 'block' : 'none';
    if (this.value !== '__custom__') custom.value = '';
});

/* ---- 行追加 ---- */
let rowIndex = 1;
document.getElementById('addRowBtn').addEventListener('click', function() {
    const prompts = document.querySelectorAll('#commandItems .prompt-input');
    const lastPrompt = prompts.length ? prompts[prompts.length - 1].value : '#';
    addRow(lastPrompt, '');
});

function addRow(promptVal, commandVal) {
    rowIndex++;
    const div = document.createElement('div');
    div.className = 'command-row';
    div.dataset.index = rowIndex;
    div.innerHTML = `
        <span class="row-num">${document.querySelectorAll('.command-row').length + 1}</span>
        <input type="text" name="prompt[]" class="form-control prompt-input"
               placeholder="プロンプト例: #" value="${escHtml(promptVal || '#')}">
        <input type="text" name="command[]" class="form-control command-input"
               placeholder="コマンドを入力" value="${escHtml(commandVal || '')}">
        <button type="button" class="btn btn-icon btn-danger remove-row" title="削除">✕</button>
    `;
    document.getElementById('commandItems').appendChild(div);
    renumberRows();
}

/* ---- 行削除 ---- */
document.getElementById('commandItems').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        const rows = document.querySelectorAll('.command-row');
        if (rows.length <= 1) { alert('最低1行は必要です'); return; }
        e.target.closest('.command-row').remove();
        renumberRows();
    }
});

function renumberRows() {
    document.querySelectorAll('.command-row .row-num').forEach((el, i) => el.textContent = i + 1);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ---- フォーム送信 ---- */
document.getElementById('commandGroupForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msg = document.getElementById('formMessage');

    const deviceTypeEl = document.getElementById('device_type');
    let deviceType = deviceTypeEl.value === '__custom__'
        ? document.getElementById('device_type_custom').value.trim()
        : deviceTypeEl.value;

    if (!deviceType) { showMsg('error', '装置種別を選択または入力してください'); return; }

    const prompts  = [...document.querySelectorAll('[name="prompt[]"]')].map(i => i.value.trim());
    const commands = [...document.querySelectorAll('[name="command[]"]')].map(i => i.value.trim());

    if (commands.some(c => !c)) { showMsg('error', 'コマンドが空の行があります'); return; }

    const body = new URLSearchParams({
        action:      'save_command_group',
        csrf_token:  CSRF,
        group_name:  document.getElementById('group_name').value.trim(),
        device_type: deviceType,
        description: document.getElementById('description').value.trim(),
    });
    prompts.forEach(p  => body.append('prompts[]', p));
    commands.forEach(c => body.append('commands[]', c));

    try {
        const res  = await fetch('ajax_api.php', { method:'POST', body });
        const data = await res.json();
        if (data.success) {
            showMsg('success', data.message || '登録しました');
            document.getElementById('commandGroupForm').reset();
            document.getElementById('commandItems').innerHTML = '';
            rowIndex = 0; addRow('#', '');
            renumberRows();
            reloadGroupList();
        } else {
            showMsg('error', data.error || 'エラーが発生しました');
        }
    } catch (err) {
        showMsg('error', 'サーバーエラー: ' + err.message);
    }
});

function showMsg(type, text) {
    const el = document.getElementById('formMessage');
    el.className = 'alert alert-' + (type === 'error' ? 'error' : 'success');
    el.textContent = text;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 4000);
}

/* ---- 一覧リロード ---- */
async function reloadGroupList() {
    try {
        const res  = await fetch('ajax_api.php', { method:'POST',
            body: new URLSearchParams({ action:'get_command_groups', csrf_token: CSRF }) });
        const data = await res.json();
        if (!data.success) return;
        const tbody = document.getElementById('groupTableBody');
        if (!tbody) { location.reload(); return; }
        tbody.innerHTML = data.groups.map(g => `
            <tr data-id="${g.id}">
                <td>${g.id}</td>
                <td><strong>${escHtml(g.group_name)}</strong></td>
                <td>${escHtml(g.device_type)}</td>
                <td>${escHtml(g.description||'')}</td>
                <td>${g.item_count} 行</td>
                <td>
                    <button class="btn btn-sm btn-secondary view-group" data-id="${g.id}">詳細</button>
                    <button class="btn btn-sm btn-primary edit-group" data-id="${g.id}">編集</button>
                    <button class="btn btn-sm btn-danger delete-group" data-id="${g.id}" data-name="${escHtml(g.group_name)}">削除</button>
                </td>
            </tr>`).join('');
    } catch (e) { /* ignore */ }
}

/* ---- 詳細表示 ---- */
document.addEventListener('click', async function (e) {
    if (e.target.classList.contains('view-group')) {
        const id = e.target.dataset.id;
        document.getElementById('detailModal').style.display = 'flex';
        document.getElementById('modalBody').innerHTML = '<div class="loading">読み込み中...</div>';
        try {
            const res  = await fetch('ajax_api.php', { method:'POST',
                body: new URLSearchParams({ action:'get_command_group_detail', id, csrf_token: CSRF }) });
            const data = await res.json();
            if (!data.success) { document.getElementById('modalBody').textContent = data.error; return; }
            const g = data.group;
            document.getElementById('modalTitle').textContent = g.group_name + '（' + g.device_type + '）';
            document.getElementById('modalBody').innerHTML = `
                <p>${escHtml(g.description||'')}</p>
                <table class="detail-table">
                    <thead><tr><th>#</th><th>プロンプト</th><th>コマンド</th></tr></thead>
                    <tbody>${g.items.map((it,i) => `
                        <tr>
                            <td>${i+1}</td>
                            <td class="mono">${escHtml(it.prompt)}</td>
                            <td class="mono">${escHtml(it.command)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;
        } catch (err) { document.getElementById('modalBody').textContent = 'エラー: ' + err.message; }
    }

    /* ---- 削除 ---- */
    if (e.target.classList.contains('delete-group')) {
        if (!confirm(`「${e.target.dataset.name}」を削除しますか？`)) return;
        const id = e.target.dataset.id;
        const res  = await fetch('ajax_api.php', { method:'POST',
            body: new URLSearchParams({ action:'delete_command_group', id, csrf_token: CSRF }) });
        const data = await res.json();
        if (data.success) { e.target.closest('tr').remove(); }
        else alert(data.error || '削除に失敗しました');
    }

    /* ---- 編集モーダルを開く ---- */
    if (e.target.classList.contains('edit-group')) {
        const id = e.target.dataset.id;
        document.getElementById('editModal').style.display = 'flex';
        document.getElementById('editFormMessage').style.display = 'none';
        document.getElementById('editCommandItems').innerHTML = '<div class="loading">読み込み中...</div>';
        try {
            const res  = await fetch('ajax_api.php', { method:'POST',
                body: new URLSearchParams({ action:'get_command_group_detail', id, csrf_token: CSRF }) });
            const data = await res.json();
            if (!data.success) { alert(data.error || '読み込みに失敗しました'); return; }
            const g = data.group;
            document.getElementById('edit_id').value          = g.id;
            document.getElementById('edit_group_name').value  = g.group_name;
            document.getElementById('edit_device_type').value = g.device_type;
            document.getElementById('edit_description').value = g.description || '';
            document.getElementById('editCommandItems').innerHTML = '';
            editRowIndex = 0;
            (g.items.length ? g.items : [{prompt:'#', command:''}]).forEach(it => addEditRow(it.prompt, it.command));
        } catch (err) { alert('エラー: ' + err.message); }
    }
});

document.getElementById('closeModal').addEventListener('click', () => {
    document.getElementById('detailModal').style.display = 'none';
});
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

/* ---- リセットボタン ---- */
document.getElementById('resetBtn').addEventListener('click', function () {
    setTimeout(() => {
        document.getElementById('commandItems').innerHTML = '';
        rowIndex = 0; addRow('#', '');
    }, 10);
});

/* ---- 編集モーダル: 行管理 ---- */
let editRowIndex = 0;

function addEditRow(promptVal, commandVal) {
    editRowIndex++;
    const div = document.createElement('div');
    div.className = 'command-row';
    div.innerHTML = `
        <span class="row-num">${document.querySelectorAll('#editCommandItems .command-row').length + 1}</span>
        <input type="text" name="edit_prompt[]" class="form-control prompt-input"
               placeholder="プロンプト例: #" value="${escHtml(promptVal || '#')}">
        <input type="text" name="edit_command[]" class="form-control command-input"
               placeholder="コマンドを入力" value="${escHtml(commandVal || '')}">
        <button type="button" class="btn btn-icon btn-danger remove-edit-row" title="削除">✕</button>
    `;
    document.getElementById('editCommandItems').appendChild(div);
    renumberEditRows();
}

function renumberEditRows() {
    document.querySelectorAll('#editCommandItems .command-row .row-num').forEach((el, i) => el.textContent = i + 1);
}

document.getElementById('editAddRowBtn').addEventListener('click', function() {
    const prompts = document.querySelectorAll('#editCommandItems .prompt-input');
    const lastPrompt = prompts.length ? prompts[prompts.length - 1].value : '#';
    addEditRow(lastPrompt, '');
});

document.getElementById('editCommandItems').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-edit-row')) {
        const rows = document.querySelectorAll('#editCommandItems .command-row');
        if (rows.length <= 1) { alert('最低1行は必要です'); return; }
        e.target.closest('.command-row').remove();
        renumberEditRows();
    }
});

/* ---- 編集モーダル: 閉じる ---- */
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
document.getElementById('closeEditModal').addEventListener('click', closeEditModal);
document.getElementById('cancelEditBtn').addEventListener('click', closeEditModal);
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

/* ---- 編集フォーム送信 ---- */
document.getElementById('editCommandGroupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const deviceType = document.getElementById('edit_device_type').value.trim();
    if (!deviceType) { showEditMsg('error', '装置種別を入力してください'); return; }

    const prompts  = [...document.querySelectorAll('[name="edit_prompt[]"]')].map(i => i.value.trim());
    const commands = [...document.querySelectorAll('[name="edit_command[]"]')].map(i => i.value.trim());
    if (commands.some(c => !c)) { showEditMsg('error', 'コマンドが空の行があります'); return; }

    const body = new URLSearchParams({
        action:      'update_command_group',
        csrf_token:  CSRF,
        id:          document.getElementById('edit_id').value,
        group_name:  document.getElementById('edit_group_name').value.trim(),
        device_type: deviceType,
        description: document.getElementById('edit_description').value.trim(),
    });
    prompts.forEach(p  => body.append('prompts[]', p));
    commands.forEach(c => body.append('commands[]', c));

    try {
        const res  = await fetch('ajax_api.php', { method:'POST', body });
        const data = await res.json();
        if (data.success) {
            showEditMsg('success', data.message || '更新しました');
            await reloadGroupList();
            setTimeout(closeEditModal, 1200);
        } else {
            showEditMsg('error', data.message || data.error || 'エラーが発生しました');
        }
    } catch (err) {
        showEditMsg('error', 'サーバーエラー: ' + err.message);
    }
});

function showEditMsg(type, text) {
    const el = document.getElementById('editFormMessage');
    el.className = 'alert alert-' + (type === 'error' ? 'error' : 'success');
    el.textContent = text;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 4000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
