<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

$pageTitle = '装置情報管理 - 装置情報管理システム';

// 初期データ取得用
try {
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    
} catch (Exception $e) {
    setErrorMessage("データベースエラー: " . $e->getMessage());
}

$errorMessage = getErrorMessage();
$successMessage = getSuccessMessage();

// 共通ヘッダーを読み込み
require_once 'includes/header.php';
?>

    <div class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1 class="page-title">
                    <div class="page-title-icon black-svg">
                        <?php include 'svgs/info.svg'; ?>
                    </div>
                    装置情報管理
                </h1>
            </div>

        <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12,2C17.53,2 22,6.47 22,12C22,17.53 17.53,22 12,22C6.47,22 2,17.53 2,12C2,6.47 6.47,2 12,2M15.59,7L12,10.59L8.41,7L7,8.41L10.59,12L7,15.59L8.41,17L12,13.41L15.59,17L17,15.59L13.41,12L17,8.41L15.59,7Z"/>
            </svg>
            <div>
                <strong>エラー:</strong> <?= h($errorMessage) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M11,16.5L18,9.5L16.59,8.09L11,13.67L7.91,10.59L6.5,12L11,16.5Z"/>
            </svg>
            <div>
                <strong>成功:</strong> <?= h($successMessage) ?>
            </div>
        </div>
        <?php endif; ?>
                
        <!-- 検索フォーム -->   
        <form id="searchForm" class="search-form">
            <h3 class="form-section-title">                    
                検索条件
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="serviceName">サービス名:</label>
                    <select id="serviceName" name="service_name">
                        <option value="">-- すべてのサービス --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="deviceType">装置種別:</label>
                    <select id="deviceType" name="device_type">
                        <option value="">-- すべての装置種別 --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="deviceName">装置名称:</label>
                    <input type="text" id="deviceName" name="device_name" placeholder="部分一致で検索">
                </div>
            </div>
            
            <div class="form-row">
                <button type="submit" class="btn-search">
                    <div class="nav-icon">
                        <?php include 'svgs/search.svg'; ?>
                    </div> 検索</button>
            </div>
        </form>
        
        <!-- 検索結果 -->
        <div id="searchResults" class="search-results">
            <div class="results-header">
                <div class="results-info" id="resultsInfo">検索結果: 0件</div>
            </div>
            
            <div id="loadingIndicator" class="loading" style="display: none;">
                <div class="spinner"></div>
                <div>検索中...</div>
            </div>
            
            <div id="resultsContainer">
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th>サービス名</th>
                            <th>装置種別</th>
                            <th>装置名称</th>
                            <th>ログインIP</th>
                            <th>ユーザー名</th>
                            <th>登録日時</th>
                            <th>更新日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody">
                    </tbody>
                </table>
            </div>
            
            <div class="pagination" id="paginationContainer">
            </div>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>装置情報編集</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editForm">
                <input type="hidden" id="edit_primary_key" name="primary_key">
                <input type="hidden" id="edit_old_service_name" name="old_service_name">
                <input type="hidden" id="edit_old_device_type" name="old_device_type">
                
                <div class="form-group">
                    <label for="edit_service_name">サービス名:<span style="color: red;">*</span></label>
                    <input type="text" id="edit_service_name" name="service_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_device_type">装置種別:<span style="color: red;">*</span></label>
                    <input type="text" id="edit_device_type" name="device_type" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_device_name">装置名称:<span style="color: red;">*</span></label>
                    <input type="text" id="edit_device_name" name="device_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_login_ip">ログインIP:</label>
                    <input type="text" id="edit_login_ip" name="login_ip">
                </div>
                
                <h4 style="margin-top: 20px; margin-bottom: 10px;">認証情報</h4>
                
                <div class="form-group">
                    <label for="edit_username1">ユーザー名1:<span style="color: red;">*</span></label>
                    <input type="text" id="edit_username1" name="username1" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_password1">パスワード1:</label>
                    <input type="password" id="edit_password1" name="password1">
                </div>
                
                <div id="additionalCredentials">
                    <!-- ユーザー名2-10、パスワード2-10 -->
                </div>
                
                <button type="button" onclick="toggleAdditionalCredentials()" style="margin-bottom: 20px; background: #6c757d;">
                    追加の認証情報を表示/非表示
                </button>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // グローバル変数
        let currentPage = 1;
        let currentSearchParams = {};
        let allResults = [];

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadServices();
            searchDevices(); // 初期表示
            
            // 検索フォームのサブミット
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                searchDevices();
            });
            
            // サービス名変更時に装置種別を更新
            document.getElementById('serviceName').addEventListener('change', function() {
                loadDeviceTypes(this.value);
            });
            
            // 編集フォームのサブミット
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitEdit();
            });
            
            // 追加の認証情報フィールドを生成
            generateAdditionalCredentials();
        });

        // サービス名一覧を読み込み
        async function loadServices() {
            try {
                const response = await fetch('ajax_api.php?action=get_services');
                const result = await response.json();
                
                if (result.success) {
                    const select = document.getElementById('serviceName');
                    select.innerHTML = '<option value="">-- すべてのサービス --</option>';
                    
                    result.data.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service;
                        option.textContent = service;
                        select.appendChild(option);
                    });
                } else {
                    showAlert('error', 'サービス名の読み込みに失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Error loading services:', error);
                showAlert('error', 'サービス名の読み込み中にエラーが発生しました');
            }
        }

        // 装置種別一覧を読み込み
        async function loadDeviceTypes(serviceName) {
            const deviceTypeSelect = document.getElementById('deviceType');
            
            if (!serviceName) {
                deviceTypeSelect.innerHTML = '<option value="">-- すべての装置種別 --</option>';
                return;
            }

            try {
                deviceTypeSelect.disabled = true;
                deviceTypeSelect.innerHTML = '<option value="">-- 読み込み中... --</option>';
                
                const response = await fetch(`ajax_api.php?action=get_device_types&service_name=${encodeURIComponent(serviceName)}`);
                const result = await response.json();
                
                if (result.success) {
                    deviceTypeSelect.innerHTML = '<option value="">-- すべての装置種別 --</option>';
                    
                    result.data.forEach(deviceType => {
                        const option = document.createElement('option');
                        option.value = deviceType;
                        option.textContent = deviceType;
                        deviceTypeSelect.appendChild(option);
                    });
                    
                    deviceTypeSelect.disabled = false;
                } else {
                    showAlert('error', '装置種別の読み込みに失敗しました: ' + result.message);
                    deviceTypeSelect.innerHTML = '<option value="">-- エラー --</option>';
                }
            } catch (error) {
                console.error('Error loading device types:', error);
                showAlert('error', '装置種別の読み込み中にエラーが発生しました');
                deviceTypeSelect.innerHTML = '<option value="">-- エラー --</option>';
            }
        }

        // 装置情報を検索
        async function searchDevices(page = 1) {
            const serviceName = document.getElementById('serviceName').value;
            const deviceType = document.getElementById('deviceType').value;
            const deviceName = document.getElementById('deviceName').value;
            
            currentSearchParams = {
                service_name: serviceName,
                device_type: deviceType,
                device_name: deviceName,
                page: page
            };
            
            const loadingIndicator = document.getElementById('loadingIndicator');
            const resultsContainer = document.getElementById('resultsContainer');
            
            loadingIndicator.style.display = 'flex';
            resultsContainer.style.display = 'none';
            
            try {
                const params = new URLSearchParams(currentSearchParams);
                params.append('action', 'search_devices');
                
                const response = await fetch('ajax_api.php?' + params.toString());
                const result = await response.json();
                
                if (result.success) {
                    displayResults(result.data);
                    currentPage = page;
                } else {
                    showAlert('error', '検索に失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Error searching devices:', error);
                showAlert('error', '検索中にエラーが発生しました');
            } finally {
                loadingIndicator.style.display = 'none';
                resultsContainer.style.display = 'block';
            }
        }

        // 検索結果を表示
        function displayResults(data) {
            const tableBody = document.getElementById('resultsTableBody');
            const resultsInfo = document.getElementById('resultsInfo');
            const paginationContainer = document.getElementById('paginationContainer');
            
            resultsInfo.textContent = `検索結果: ${data.pagination.total_count}件（${data.pagination.current_page}/${data.pagination.total_pages}ページ）`;
            
            tableBody.innerHTML = '';
            
            if (data.devices.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #6c757d;">検索条件に一致するデータが見つかりませんでした</td></tr>';
            } else {
                data.devices.forEach(device => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(device.service_name)}</td>
                        <td>${escapeHtml(device.device_type)}</td>
                        <td class="text-truncate" title="${escapeHtml(device.device_name)}">${escapeHtml(device.device_name)}</td>
                        <td>${escapeHtml(device.login_ip || '-')}</td>
                        <td>${escapeHtml(device.username1)}</td>
                        <td>${formatDateTime(device.created_at)}</td>
                        <td>${formatDateTime(device.updated_at)}</td>
                        <td>
                            <button class="btn-edit" onclick="openEditModal('${escapeHtml(device.primary_key)}')">
                                ✏️ 編集
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('${escapeHtml(device.primary_key)}', '${escapeHtml(device.device_name)}', '${escapeHtml(device.service_name)}', '${escapeHtml(device.device_type)}')">
                                🗑️ 削除
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            
            // ページネーション表示
            displayPagination(data.pagination);
        }

        // ページネーション表示
        function displayPagination(pagination) {
            const container = document.getElementById('paginationContainer');
            
            if (pagination.total_pages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '<div class="pagination-buttons">';
            
            // 前へボタン
            if (pagination.current_page > 1) {
                html += `<button class="pagination-btn" onclick="searchDevices(${pagination.current_page - 1})">← 前へ</button>`;
            }
            
            // ページ番号
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active' : '';
                html += `<button class="pagination-btn ${activeClass}" onclick="searchDevices(${i})">${i}</button>`;
            }
            
            // 次へボタン
            if (pagination.current_page < pagination.total_pages) {
                html += `<button class="pagination-btn" onclick="searchDevices(${pagination.current_page + 1})">次へ →</button>`;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }

        // 編集モーダルを開く
        async function openEditModal(primaryKey) {
            try {
                const response = await fetch(`ajax_api.php?action=get_device&primary_key=${encodeURIComponent(primaryKey)}`);
                const result = await response.json();
                
                if (result.success) {
                    const device = result.data;
                    
                    // フォームに値をセット
                    document.getElementById('edit_primary_key').value = device.primary_key;
                    document.getElementById('edit_old_service_name').value = device.service_name;
                    document.getElementById('edit_old_device_type').value = device.device_type;
                    document.getElementById('edit_service_name').value = device.service_name;
                    document.getElementById('edit_device_type').value = device.device_type;
                    document.getElementById('edit_device_name').value = device.device_name;
                    document.getElementById('edit_login_ip').value = device.login_ip || '';
                    document.getElementById('edit_username1').value = device.username1;
                    document.getElementById('edit_password1').value = device.password1 || '';
                    
                    // 追加の認証情報
                    for (let i = 2; i <= 10; i++) {
                        document.getElementById(`edit_username${i}`).value = device[`username${i}`] || '';
                        document.getElementById(`edit_password${i}`).value = device[`password${i}`] || '';
                    }
                    
                    // モーダルを表示
                    document.getElementById('editModal').style.display = 'flex';
                } else {
                    showAlert('error', '装置情報の取得に失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Error loading device:', error);
                showAlert('error', '装置情報の取得中にエラーが発生しました');
            }
        }

        // 編集モーダルを閉じる
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 追加の認証情報フィールドを生成
        function generateAdditionalCredentials() {
            const container = document.getElementById('additionalCredentials');
            let html = '';
            
            for (let i = 2; i <= 10; i++) {
                html += `
                    <div class="form-group" style="display: none;" id="credentials_group_${i}">
                        <h5 style="margin-top: 15px;">認証情報 ${i}</h5>
                        <label for="edit_username${i}">ユーザー名${i}:</label>
                        <input type="text" id="edit_username${i}" name="username${i}">
                        <label for="edit_password${i}">パスワード${i}:</label>
                        <input type="password" id="edit_password${i}" name="password${i}">
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }

        // 追加の認証情報の表示/非表示切り替え
        function toggleAdditionalCredentials() {
            for (let i = 2; i <= 10; i++) {
                const group = document.getElementById(`credentials_group_${i}`);
                if (group.style.display === 'none') {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            }
        }

        // 編集を送信
        async function submitEdit() {
            const formData = new FormData(document.getElementById('editForm'));
            formData.append('action', 'update_device');
            
            try {
                const response = await fetch('ajax_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    closeEditModal();
                    searchDevices(currentPage); // 現在のページを再読み込み
                } else {
                    showAlert('error', '更新に失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Error updating device:', error);
                showAlert('error', '更新中にエラーが発生しました');
            }
        }

        // 削除確認
        function confirmDelete(primaryKey, deviceName, serviceName, deviceType) {
            if (confirm(`本当に「${deviceName}」を削除しますか？\nこの操作は取り消せません。`)) {
                deleteDevice(primaryKey, serviceName, deviceType);
            }
        }

        // 削除実行
        async function deleteDevice(primaryKey, serviceName, deviceType) {
            const formData = new FormData();
            formData.append('action', 'delete_device');
            formData.append('primary_key', primaryKey);
            formData.append('service_name', serviceName);
            formData.append('device_type', deviceType);
            
            try {
                const response = await fetch('ajax_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    searchDevices(currentPage); // 現在のページを再読み込み
                } else {
                    showAlert('error', '削除に失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting device:', error);
                showAlert('error', '削除中にエラーが発生しました');
            }
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 日時フォーマット
        function formatDateTime(datetime) {
            if (!datetime) return '-';
            const date = new Date(datetime);
            return date.toLocaleString('ja-JP', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // アラート表示
        function showAlert(type, message) {
            alert(message);
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: #ddd;
        }
        
        .modal-content form {
            padding: 20px;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .btn-edit {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-edit:hover {
            background-color: #0056b3;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
    </style>

<?php require_once 'includes/footer.php'; ?>
