<?php
/**
 * Ajax API 直接テスト - ブラウザで開いて動作確認
 */
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajax API テスト</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .btn { padding: 10px 20px; margin: 5px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .result { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #3498db; }
        .error { border-left-color: #e74c3c; background: #fadbd8; }
        .success { border-left-color: #27ae60; background: #d5f4e6; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        .loading { color: #3498db; font-style: italic; }
    </style>
</head>
<body>
    <h1>🧪 Ajax API 直接テスト</h1>

    <div class="test-section">
        <h2>1️⃣ サービス名取得テスト</h2>
        <button class="btn" onclick="testGetServices()">サービス名取得</button>
        <div id="result1" class="result" style="display:none;"></div>
    </div>

    <div class="test-section">
        <h2>2️⃣ 装置種別取得テスト</h2>
        <p>まずサービス名を取得してから実行してください</p>
        <input type="text" id="serviceNameInput" placeholder="サービス名を入力" style="padding: 8px; width: 300px;">
        <button class="btn" onclick="testGetDeviceTypes()">装置種別取得</button>
        <div id="result2" class="result" style="display:none;"></div>
    </div>

    <div class="test-section">
        <h2>3️⃣ 検索API テスト（全件）</h2>
        <button class="btn" onclick="testSearchAll()">全件検索</button>
        <div id="result3" class="result" style="display:none;"></div>
    </div>

    <div class="test-section">
        <h2>4️⃣ 検索API テスト（条件指定）</h2>
        <p>サービス名: <input type="text" id="searchServiceName" placeholder="サービス名" style="padding: 8px; width: 200px;"></p>
        <p>装置種別: <input type="text" id="searchDeviceType" placeholder="装置種別" style="padding: 8px; width: 200px;"></p>
        <p>装置名称: <input type="text" id="searchDeviceName" placeholder="装置名称（部分一致）" style="padding: 8px; width: 200px;"></p>
        <button class="btn" onclick="testSearchWithConditions()">条件検索</button>
        <div id="result4" class="result" style="display:none;"></div>
    </div>

    <div class="test-section">
        <h2>📊 統合テスト</h2>
        <button class="btn" onclick="runAllTests()">全テスト実行</button>
        <div id="result5" class="result" style="display:none;"></div>
    </div>

    <div class="test-section">
        <h2>📚 関連リンク</h2>
        <ul>
            <li><a href="search.php" target="_blank">検索ページ（本番）</a></li>
            <li><a href="manage.php" target="_blank">管理ページ（本番）</a></li>
            <li><a href="debug_search.php">デバッグツール</a></li>
            <li><a href="index.php">トップページ</a></li>
        </ul>
    </div>

    <script>
        // ユーティリティ関数
        function showResult(elementId, content, type = 'info') {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.className = 'result ' + type;
            element.innerHTML = content;
        }

        function showLoading(elementId) {
            showResult(elementId, '<span class="loading">⏳ 処理中...</span>', 'info');
        }

        // 1. サービス名取得テスト
        async function testGetServices() {
            showLoading('result1');
            
            try {
                console.log('🔍 Testing: ajax_api.php?action=get_services');
                
                const response = await fetch('ajax_api.php?action=get_services');
                const text = await response.text();
                
                console.log('Response status:', response.status);
                console.log('Response text:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON parse error: ' + e.message + '\n\nResponse: ' + text);
                }
                
                let html = '<h3>レスポンス:</h3>';
                html += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
                
                if (result.success) {
                    html += '<h3>取得されたサービス名:</h3>';
                    html += '<ul>';
                    if (result.data && result.data.length > 0) {
                        result.data.forEach(service => {
                            html += '<li>' + escapeHtml(service) + '</li>';
                        });
                        
                        // 最初のサービス名を自動入力
                        document.getElementById('serviceNameInput').value = result.data[0];
                        document.getElementById('searchServiceName').value = result.data[0];
                    } else {
                        html += '<li style="color: orange;">データがありません</li>';
                    }
                    html += '</ul>';
                    
                    showResult('result1', html, 'success');
                } else {
                    html += '<p style="color: red;">エラー: ' + escapeHtml(result.message) + '</p>';
                    showResult('result1', html, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                const html = '<h3>エラー発生:</h3><pre>' + escapeHtml(error.message) + '</pre>';
                showResult('result1', html, 'error');
            }
        }

        // 2. 装置種別取得テスト
        async function testGetDeviceTypes() {
            const serviceName = document.getElementById('serviceNameInput').value;
            
            if (!serviceName) {
                alert('サービス名を入力してください');
                return;
            }
            
            showLoading('result2');
            
            try {
                const url = 'ajax_api.php?action=get_device_types&service_name=' + encodeURIComponent(serviceName);
                console.log('🔍 Testing:', url);
                
                const response = await fetch(url);
                const text = await response.text();
                
                console.log('Response status:', response.status);
                console.log('Response text:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON parse error: ' + e.message + '\n\nResponse: ' + text);
                }
                
                let html = '<h3>レスポンス:</h3>';
                html += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
                
                if (result.success) {
                    html += '<h3>取得された装置種別:</h3>';
                    html += '<ul>';
                    if (result.data && result.data.length > 0) {
                        result.data.forEach(type => {
                            html += '<li>' + escapeHtml(type) + '</li>';
                        });
                        
                        // 最初の装置種別を自動入力
                        document.getElementById('searchDeviceType').value = result.data[0];
                    } else {
                        html += '<li style="color: orange;">データがありません</li>';
                    }
                    html += '</ul>';
                    
                    showResult('result2', html, 'success');
                } else {
                    html += '<p style="color: red;">エラー: ' + escapeHtml(result.message) + '</p>';
                    showResult('result2', html, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                const html = '<h3>エラー発生:</h3><pre>' + escapeHtml(error.message) + '</pre>';
                showResult('result2', html, 'error');
            }
        }

        // 3. 全件検索テスト
        async function testSearchAll() {
            showLoading('result3');
            
            try {
                const url = 'ajax_api.php?action=search_devices&page=1';
                console.log('🔍 Testing:', url);
                
                const response = await fetch(url);
                const text = await response.text();
                
                console.log('Response status:', response.status);
                console.log('Response text:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON parse error: ' + e.message + '\n\nResponse: ' + text);
                }
                
                let html = '<h3>レスポンス:</h3>';
                html += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
                
                if (result.success && result.data) {
                    const count = result.data.pagination.total_count;
                    const devices = result.data.devices;
                    
                    html += '<h3>検索結果: ' + count + ' 件</h3>';
                    
                    if (devices && devices.length > 0) {
                        html += '<table border="1" cellpadding="5" style="margin-top: 10px;">';
                        html += '<tr><th>サービス名</th><th>装置種別</th><th>装置名称</th><th>ログインIP</th><th>ユーザ名1</th></tr>';
                        devices.forEach(device => {
                            html += '<tr>';
                            html += '<td>' + escapeHtml(device.service_name) + '</td>';
                            html += '<td>' + escapeHtml(device.device_type) + '</td>';
                            html += '<td>' + escapeHtml(device.device_name) + '</td>';
                            html += '<td>' + escapeHtml(device.login_ip || '-') + '</td>';
                            html += '<td>' + escapeHtml(device.username1) + '</td>';
                            html += '</tr>';
                        });
                        html += '</table>';
                    }
                    
                    showResult('result3', html, 'success');
                } else {
                    html += '<p style="color: red;">エラー: ' + escapeHtml(result.message || '不明なエラー') + '</p>';
                    showResult('result3', html, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                const html = '<h3>エラー発生:</h3><pre>' + escapeHtml(error.message) + '</pre>';
                showResult('result3', html, 'error');
            }
        }

        // 4. 条件検索テスト
        async function testSearchWithConditions() {
            showLoading('result4');
            
            try {
                const serviceName = document.getElementById('searchServiceName').value;
                const deviceType = document.getElementById('searchDeviceType').value;
                const deviceName = document.getElementById('searchDeviceName').value;
                
                const params = new URLSearchParams();
                params.append('action', 'search_devices');
                params.append('page', '1');
                if (serviceName) params.append('service_name', serviceName);
                if (deviceType) params.append('device_type', deviceType);
                if (deviceName) params.append('device_name', deviceName);
                
                const url = 'ajax_api.php?' + params.toString();
                console.log('🔍 Testing:', url);
                
                const response = await fetch(url);
                const text = await response.text();
                
                console.log('Response status:', response.status);
                console.log('Response text:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON parse error: ' + e.message + '\n\nResponse: ' + text);
                }
                
                let html = '<h3>検索条件:</h3>';
                html += '<ul>';
                html += '<li>サービス名: ' + (serviceName || '（指定なし）') + '</li>';
                html += '<li>装置種別: ' + (deviceType || '（指定なし）') + '</li>';
                html += '<li>装置名称: ' + (deviceName || '（指定なし）') + '</li>';
                html += '</ul>';
                
                html += '<h3>レスポンス:</h3>';
                html += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
                
                if (result.success && result.data) {
                    const count = result.data.pagination.total_count;
                    html += '<h3>検索結果: ' + count + ' 件</h3>';
                    showResult('result4', html, 'success');
                } else {
                    html += '<p style="color: red;">エラー: ' + escapeHtml(result.message || '不明なエラー') + '</p>';
                    showResult('result4', html, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                const html = '<h3>エラー発生:</h3><pre>' + escapeHtml(error.message) + '</pre>';
                showResult('result4', html, 'error');
            }
        }

        // 全テスト実行
        async function runAllTests() {
            showResult('result5', '<h3>全テストを順次実行中...</h3>', 'info');
            
            let allResults = '<h3>統合テスト結果</h3>';
            let allSuccess = true;
            
            // テスト1
            try {
                const response = await fetch('ajax_api.php?action=get_services');
                const result = await response.json();
                if (result.success && result.data && result.data.length > 0) {
                    allResults += '<p>✓ サービス名取得: <span style="color: green;">成功</span> (' + result.data.length + '件)</p>';
                } else {
                    allResults += '<p>✗ サービス名取得: <span style="color: red;">失敗</span></p>';
                    allSuccess = false;
                }
            } catch (e) {
                allResults += '<p>✗ サービス名取得: <span style="color: red;">エラー</span> - ' + e.message + '</p>';
                allSuccess = false;
            }
            
            // テスト2
            try {
                const response = await fetch('ajax_api.php?action=search_devices&page=1');
                const result = await response.json();
                if (result.success && result.data) {
                    allResults += '<p>✓ 全件検索: <span style="color: green;">成功</span> (' + result.data.pagination.total_count + '件)</p>';
                } else {
                    allResults += '<p>✗ 全件検索: <span style="color: red;">失敗</span></p>';
                    allSuccess = false;
                }
            } catch (e) {
                allResults += '<p>✗ 全件検索: <span style="color: red;">エラー</span> - ' + e.message + '</p>';
                allSuccess = false;
            }
            
            if (allSuccess) {
                allResults += '<h2 style="color: green;">✓ すべてのテストが成功しました！</h2>';
                allResults += '<p><strong>検索機能は正常に動作しています。</strong></p>';
                allResults += '<p>それでも検索ページで動作しない場合、以下を確認してください：</p>';
                allResults += '<ul>';
                allResults += '<li>ブラウザのキャッシュをクリア（Ctrl+Shift+Delete）</li>';
                allResults += '<li>ブラウザの開発者ツール（F12）でJavaScriptエラーを確認</li>';
                allResults += '<li>検索ページ（search.php）のJavaScriptが正しく読み込まれているか確認</li>';
                allResults += '</ul>';
                showResult('result5', allResults, 'success');
            } else {
                allResults += '<h2 style="color: red;">✗ 一部のテストが失敗しました</h2>';
                showResult('result5', allResults, 'error');
            }
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
