<?php
require_once 'config.php';

$pageTitle = '装置情報検索 - 装置情報管理システム';

// 初期データ取得用
try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
    $deviceManager = new DeviceManager($database);
    
    // 統計情報を取得
    $statistics = $deviceManager->getDeviceStatistics();
    
} catch (Exception $e) {
    $statistics = null;
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
                        <?php include 'svgs/search.svg'; ?>
                    </div>
                    装置情報検索
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
                    <select id="deviceType" name="device_type" disabled>
                        <option value="">-- まずサービス名を選択してください --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="deviceName">装置名:</label>
                    <input type="text" id="deviceName" name="device_name" placeholder="装置名で検索（部分一致）">
                </div>
            </div>
            
            <div class="search-buttons">
                <button type="button" id="clearBtn" class="btn btn-secondary">
                    <div class="btn-icon">
                        <?php include 'svgs/rotate.svg'; ?>
                    </div> クリア
                </button>
                <button type="submit" id="searchBtn" class="btn btn-primary">                    <div class="btn-icon">
                        <?php include 'svgs/search.svg'; ?>
                    </div> 検索</button>
            </div>
        </form>
        
        <!-- 検索結果 -->
        <div id="searchResults" class="search-results">
            <div class="results-header">
                <div class="results-info" id="resultsInfo">検索結果: 0件</div>
                <div class="export-buttons">
                    <button class="btn-export" onclick="exportCSV()">📄 CSV出力</button>
                    <button class="btn-export" onclick="exportExcel()">📊 Excel出力</button>
                </div>
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
                            <th>装置IP</th>
                            <th>ユーザー名</th>
                            <th>パスワード</th>
                            <th>登録日時</th>
                            <th>更新日時</th>
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

    <script>
        // グローバル変数
        let currentPage = 1;
        let currentSearchParams = {};
        let allResults = [];

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadServices();
            setupEventListeners();
        });

        // イベントリスナーの設定
        function setupEventListeners() {
            // サービス名変更時
            document.getElementById('serviceName').addEventListener('change', function() {
                loadDeviceTypes(this.value);
            });

            // 検索フォーム送信
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                performSearch(1);
            });

            // クリアボタン
            document.getElementById('clearBtn').addEventListener('click', function() {
                clearForm();
            });

            // エンターキーでの検索
            document.getElementById('deviceName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch(1);
                }
            });
        }

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

        // 装置種別一覧を読み込み（サービス名でフィルタ）
        async function loadDeviceTypes(serviceName) {
            const deviceTypeSelect = document.getElementById('deviceType');
            
            if (!serviceName) {
                deviceTypeSelect.innerHTML = '<option value="">-- まずサービス名を選択してください --</option>';
                deviceTypeSelect.disabled = true;
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

        // 検索実行
        async function performSearch(page = 1) {
            const formData = new FormData(document.getElementById('searchForm'));
            const params = new URLSearchParams(formData);
            params.append('action', 'search_devices');
            params.append('page', page);
            
            // 現在の検索パラメータを保存
            currentSearchParams = Object.fromEntries(formData);
            currentPage = page;

            try {
                showLoading(true);
                
                const response = await fetch('ajax_api.php', {
                    method: 'POST',
                    body: params
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayResults(result.data);
                    allResults = result.data.devices; // CSV出力用
                } else {
                    showAlert('error', '検索に失敗しました: ' + result.message);
                }
            } catch (error) {
                console.error('Search error:', error);
                showAlert('error', '検索中にエラーが発生しました');
            } finally {
                showLoading(false);
            }
        }

        // 検索結果表示
        function displayResults(data) {
            const resultsDiv = document.getElementById('searchResults');
            const tableBody = document.getElementById('resultsTableBody');
            const resultsInfo = document.getElementById('resultsInfo');
            const paginationContainer = document.getElementById('paginationContainer');
            
            // 結果情報の更新
            resultsInfo.textContent = `検索結果: ${data.pagination.total_count}件（${data.pagination.current_page}/${data.pagination.total_pages}ページ）`;
            
            // テーブル内容をクリア
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
                        <td>${escapeHtml(device.device_ip || '-')}</td>
                        <td>${escapeHtml(device.username)}</td>
                        <td class="text-truncate" title="${escapeHtml(device.password || '-')}">${device.password ? '●●●●●●' : '-'}</td>
                        <td>${formatDateTime(device.created_at)}</td>
                        <td>${formatDateTime(device.updated_at)}</td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            
            // ページネーション表示
            displayPagination(data.pagination);
            
            // 結果エリアを表示
            resultsDiv.style.display = 'block';
        }

        // ページネーション表示
        function displayPagination(pagination) {
            const container = document.getElementById('paginationContainer');
            container.innerHTML = '';
            
            if (pagination.total_pages <= 1) return;
            
            // 前へボタン
            const prevBtn = document.createElement('button');
            prevBtn.textContent = '« 前へ';
            prevBtn.disabled = pagination.current_page === 1;
            prevBtn.onclick = () => performSearch(pagination.current_page - 1);
            container.appendChild(prevBtn);
            
            // ページ番号ボタン
            const start = Math.max(1, pagination.current_page - 2);
            const end = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (let i = start; i <= end; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.onclick = () => performSearch(i);
                
                if (i === pagination.current_page) {
                    pageBtn.className = 'current';
                }
                
                container.appendChild(pageBtn);
            }
            
            // 次へボタン
            const nextBtn = document.createElement('button');
            nextBtn.textContent = '次へ »';
            nextBtn.disabled = pagination.current_page === pagination.total_pages;
            nextBtn.onclick = () => performSearch(pagination.current_page + 1);
            container.appendChild(nextBtn);
        }

        // フォームクリア
        function clearForm() {
            document.getElementById('searchForm').reset();
            document.getElementById('deviceType').innerHTML = '<option value="">-- まずサービス名を選択してください --</option>';
            document.getElementById('deviceType').disabled = true;
            document.getElementById('searchResults').style.display = 'none';
        }

        // ローディング表示切り替え
        function showLoading(show) {
            const loading = document.getElementById('loadingIndicator');
            const results = document.getElementById('resultsContainer');
            
            if (show) {
                loading.style.display = 'block';
                results.style.display = 'none';
            } else {
                loading.style.display = 'none';
                results.style.display = 'block';
            }
        }

        // アラート表示
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<strong>${type === 'error' ? 'エラー' : '情報'}:</strong> ${message}`;
            
            const container = document.querySelector('.container');
            const header = container.querySelector('.header');
            container.insertBefore(alertDiv, header.nextSibling);
            
            // 5秒後に自動削除
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        // CSV出力
        function exportCSV() {
            if (allResults.length === 0) {
                showAlert('error', '出力するデータがありません');
                return;
            }

            const headers = ['サービス名', '装置種別', '装置名称', '装置IP', 'ユーザー名', 'パスワード', '登録日時', '更新日時'];
            let csv = headers.join(',') + '\n';
            
            allResults.forEach(device => {
                const row = [
                    `"${device.service_name}"`,
                    `"${device.device_type}"`,
                    `"${device.device_name}"`,
                    `"${device.device_ip || ''}"`,
                    `"${device.username}"`,
                    `"${device.password || ''}"`,
                    `"${device.created_at}"`,
                    `"${device.updated_at}"`
                ];
                csv += row.join(',') + '\n';
            });
            
            downloadFile(csv, 'device_search_results.csv', 'text/csv;charset=utf-8;');
        }

        // Excel出力（簡易版）
        function exportExcel() {
            showAlert('info', 'Excel出力機能は開発中です。CSV出力をご利用ください。');
        }

        // ファイルダウンロード
        function downloadFile(content, filename, mimeType) {
            const blob = new Blob(['\uFEFF' + content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 日時フォーマット
        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP');
        }
    </script>

        </div> <!-- page-container -->
    </div> <!-- main-content -->

<?php require_once 'includes/footer.php'; ?>