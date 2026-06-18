<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

$pageTitle = 'コマンド群マクロ出力 - 装置情報管理システム';

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, 'mysql', defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    $services = $deviceManager->getServiceNamesFromRelation();
} catch (Exception $e) {
    $initError = $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="main-content">
<div class="page-container">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon black-svg"><?php include 'svgs/download.svg'; ?></div>
        コマンド群マクロ出力
    </h1>
</div>

<?php if (!empty($initError)): ?>
<div class="alert alert-error"><?= htmlspecialchars($initError) ?></div>
<?php endif; ?>

<div class="wizard-layout">

    <!-- Step 1: サービス名 -->
    <div class="wizard-step" id="step1">
        <div class="step-header"><span class="step-num">1</span> サービス名を選択</div>
        <div class="step-body">
            <select id="sel_service" class="form-control">
                <option value="">-- サービス名を選択 --</option>
                <?php foreach ($services ?? [] as $svc): ?>
                <option value="<?= htmlspecialchars($svc) ?>"><?= htmlspecialchars($svc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Step 2: 装置種別 -->
    <div class="wizard-step step-disabled" id="step2">
        <div class="step-header"><span class="step-num">2</span> 装置種別を選択</div>
        <div class="step-body">
            <select id="sel_device_type" class="form-control" disabled>
                <option value="">-- 先にサービス名を選択 --</option>
            </select>
        </div>
    </div>

    <!-- Step 3: 対象装置 -->
    <div class="wizard-step step-disabled" id="step3">
        <div class="step-header">
            <span class="step-num">3</span> 対象装置を選択
            <label class="check-all-label">
                <input type="checkbox" id="checkAll" checked> すべて選択/解除
            </label>
        </div>
        <div class="step-body">
            <div id="deviceList" class="device-list">
                <p class="text-muted">装置種別を選択してください</p>
            </div>
        </div>
    </div>

    <!-- Step 4: コマンド群 -->
    <div class="wizard-step step-disabled" id="step4">
        <div class="step-header"><span class="step-num">4</span> コマンド群を選択</div>
        <div class="step-body">
            <select id="sel_command_group" class="form-control" disabled>
                <option value="">-- コマンド群を選択 --</option>
            </select>
            <div id="commandPreview" class="command-preview" style="display:none;"></div>
        </div>
    </div>

    <!-- Step 5: 出力 -->
    <div class="wizard-step step-disabled" id="step5">
        <div class="step-header"><span class="step-num">5</span> マクロ出力</div>
        <div class="step-body">
            <div id="outputSummary" class="output-summary"></div>
            <form id="macroForm" method="POST" action="generate_command_macro.php" target="_blank">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="service_name" id="f_service">
                <input type="hidden" name="device_type" id="f_device_type">
                <input type="hidden" name="command_group_id" id="f_group_id">
                <input type="hidden" name="macro_mode" id="f_macro_mode" value="normal">
                <div id="f_devices_container"></div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary btn-lg" id="downloadBtn" disabled
                            onclick="document.getElementById('f_macro_mode').value='normal'">
                        ▼ Teraterm マクロをダウンロード (.ttl)
                    </button>
                    <button type="submit" class="btn btn-secondary btn-lg" id="downloadBtnSelect" disabled
                            onclick="document.getElementById('f_macro_mode').value='device_select'">
                        ▼ 装置選択ありで作成 (.ttl)
                    </button>
                </div>
            </form>
        </div>
    </div>

</div><!-- wizard-layout -->
</div><!-- page-container -->
</div><!-- main-content -->

<style>
.wizard-layout { display:flex; flex-direction:column; gap:16px; }
.wizard-step { border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; transition: opacity .2s; }
.step-disabled { opacity:.45; pointer-events:none; }
.step-header {
    display:flex; align-items:center; gap:10px;
    background:#f3f4f6; padding:10px 16px;
    font-weight:600; font-size:15px; color:#374151;
}
.step-num {
    display:inline-flex; align-items:center; justify-content:center;
    width:26px; height:26px; border-radius:50%;
    background:#4f46e5; color:#fff; font-size:13px; font-weight:700; flex-shrink:0;
}
.step-body { padding:16px; }
.check-all-label { margin-left:auto; font-weight:400; font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer; }
.device-list { display:flex; flex-wrap:wrap; gap:8px; max-height:260px; overflow-y:auto; }
.device-item {
    display:flex; align-items:center; gap:6px;
    padding:6px 10px; border:1px solid #e5e7eb; border-radius:6px;
    font-size:13px; cursor:pointer; user-select:none;
    background:#fff; transition: border-color .15s, background .15s;
}
.device-item:hover { border-color:#4f46e5; }
.device-item.checked { background:#eef2ff; border-color:#4f46e5; }
.device-ip { color:#6b7280; font-size:12px; }
.command-preview {
    margin-top:12px; background:#1e1e2e; color:#cdd6f4;
    border-radius:6px; padding:14px 16px; font-family:monospace; font-size:12px;
    max-height:220px; overflow-y:auto; white-space:pre-wrap;
}
.output-summary { margin-bottom:14px; color:#374151; font-size:14px; }
.output-summary strong { color:#4f46e5; }
.btn-lg { padding:12px 28px; font-size:16px; }
.text-muted { color:#6b7280; font-size:13px; }
</style>

<script>
const CSRF = document.querySelector('[name=csrf_token]').value;

async function apiPost(action, params = {}) {
    const body = new URLSearchParams({ action, csrf_token: CSRF, ...params });
    const res = await fetch('ajax_api.php', { method: 'POST', body });
    return res.json();
}

function enable(stepId)  { document.getElementById(stepId).classList.remove('step-disabled'); }
function disable(stepId) { document.getElementById(stepId).classList.add('step-disabled'); }

/* ---- Step 1: サービス選択 ---- */
document.getElementById('sel_service').addEventListener('change', async function () {
    const svc = this.value;
    ['step2','step3','step4','step5'].forEach(disable);
    resetStep3(); resetStep4(); resetStep5();

    if (!svc) return;

    // 装置種別を取得
    const data = await apiPost('get_device_types', { service_name: svc });
    const sel = document.getElementById('sel_device_type');
    sel.innerHTML = '<option value="">-- 装置種別を選択 --</option>';
    (Array.isArray(data.data) ? data.data : []).forEach(dt => {
        sel.innerHTML += `<option value="${esc(dt)}">${esc(dt)}</option>`;
    });
    sel.disabled = false;
    enable('step2');
});

/* ---- Step 2: 装置種別選択 ---- */
document.getElementById('sel_device_type').addEventListener('change', async function () {
    const svc = document.getElementById('sel_service').value;
    const dt  = this.value;
    ['step3','step4','step5'].forEach(disable);
    resetStep3(); resetStep4(); resetStep5();

    if (!dt) return;

    // 装置一覧
    const dData = await apiPost('get_devices_for_macro', { service_name: svc, device_type: dt });
    renderDeviceList(dData.devices || []);
    enable('step3');

    // コマンド群
    const gData = await apiPost('get_command_groups_by_device_type', { device_type: dt });
    const sel = document.getElementById('sel_command_group');
    sel.innerHTML = '<option value="">-- コマンド群を選択 --</option>';
    (gData.groups || []).forEach(g => {
        sel.innerHTML += `<option value="${g.id}">${esc(g.group_name)}</option>`;
    });
    sel.disabled = false;
    enable('step4');
});

/* ---- 装置リスト描画 ---- */
function renderDeviceList(devices) {
    const container = document.getElementById('deviceList');
    if (!devices.length) {
        container.innerHTML = '<p class="text-muted">この組み合わせの装置情報がありません</p>';
        return;
    }
    container.innerHTML = devices.map(d => `
        <label class="device-item checked">
            <input type="checkbox" class="device-cb" value="${esc(d.primary_key)}"
                   data-name="${esc(d.device_name)}" data-ip="${esc(d.login_ip||'')}"
                   data-user="${esc(d.username1||'')}" data-pass="${esc(d.password1||'')}" checked>
            <span>${esc(d.device_name)}</span>
            <span class="device-ip">${esc(d.login_ip||'')}</span>
        </label>`).join('');

    document.querySelectorAll('.device-cb').forEach(cb => {
        cb.addEventListener('change', function () {
            this.closest('.device-item').classList.toggle('checked', this.checked);
            updateStep5();
        });
    });
    updateStep5();
}

/* ---- すべて選択/解除 ---- */
document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.device-cb').forEach(cb => {
        cb.checked = this.checked;
        cb.closest('.device-item').classList.toggle('checked', this.checked);
    });
    updateStep5();
});

/* ---- Step 4: コマンド群選択 ---- */
document.getElementById('sel_command_group').addEventListener('change', async function () {
    const id = this.value;
    document.getElementById('commandPreview').style.display = 'none';
    resetStep5();

    if (!id) return;

    const data = await apiPost('get_command_group_detail', { id });
    if (data.success) {
        const g = data.group;
        const lines = g.items.map((it, i) =>
            `${String(i+1).padStart(2,' ')}. [${it.prompt}] ${it.command}`).join('\n');
        document.getElementById('commandPreview').textContent = `【${g.group_name}】\n${lines}`;
        document.getElementById('commandPreview').style.display = 'block';
        document.getElementById('f_group_id').value = id;
        updateStep5();
    }
});

/* ---- Step 5 更新 ---- */
function updateStep5() {
    const checkedCbs = [...document.querySelectorAll('.device-cb:checked')];
    const groupId = document.getElementById('f_group_id').value;
    const svc  = document.getElementById('sel_service').value;
    const dt   = document.getElementById('sel_device_type').value;
    const groupName = document.getElementById('sel_command_group').selectedOptions[0]?.text || '';

    if (!checkedCbs.length || !groupId) { disable('step5'); return; }

    enable('step5');
    document.getElementById('f_service').value     = svc;
    document.getElementById('f_device_type').value = dt;

    // 選択装置を hidden input に反映
    const cont = document.getElementById('f_devices_container');
    cont.innerHTML = checkedCbs.map(cb =>
        `<input type="hidden" name="primary_keys[]" value="${esc(cb.value)}">
         <input type="hidden" name="ips[]" value="${esc(cb.dataset.ip)}">
         <input type="hidden" name="usernames[]" value="${esc(cb.dataset.user)}">
         <input type="hidden" name="passwords[]" value="${esc(cb.dataset.pass)}">
         <input type="hidden" name="device_names[]" value="${esc(cb.dataset.name)}">`
    ).join('');

    document.getElementById('outputSummary').innerHTML =
        `サービス: <strong>${esc(svc)}</strong> ／ 装置種別: <strong>${esc(dt)}</strong><br>
         対象装置: <strong>${checkedCbs.length} 台</strong> ／ コマンド群: <strong>${esc(groupName)}</strong>`;

    document.getElementById('downloadBtn').disabled = false;
    document.getElementById('downloadBtnSelect').disabled = false;
}

function resetStep3() {
    document.getElementById('deviceList').innerHTML = '<p class="text-muted">装置種別を選択してください</p>';
}
function resetStep4() {
    document.getElementById('sel_command_group').innerHTML = '<option value="">-- コマンド群を選択 --</option>';
    document.getElementById('sel_command_group').disabled = true;
    document.getElementById('commandPreview').style.display = 'none';
    document.getElementById('f_group_id').value = '';
}
function resetStep5() {
    document.getElementById('outputSummary').innerHTML = '';
    document.getElementById('downloadBtn').disabled = true;
    document.getElementById('downloadBtnSelect').disabled = true;
    disable('step5');
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>
