<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

$pageTitle = 'CSVダウンロード - 装置情報管理システム';

try {
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    
    // サービス名とデバイス種別を取得
    $services = $deviceManager->getServiceNamesFromRelation();
    
    $selectedService = $_GET['service_name'] ?? $_POST['service_name'] ?? '';
    $selectedDeviceType = $_GET['device_type'] ?? $_POST['device_type'] ?? '';
    
    // 選択されたサービスに対応するデバイス種別を取得
    $deviceTypes = [];
    if (!empty($selectedService)) {
        $deviceTypes = $deviceManager->getDeviceTypesFromRelation($selectedService);
    }
    
    // プレビューデータ
    $previewData = [];
    $tableName = '';
    $totalCount = 0;
    $previewColumns = [];
    
    if (!empty($selectedService) && !empty($selectedDeviceType)) {
        // 動的テーブル名を生成
        $tableName = sanitizeTableName($selectedService . '_' . $selectedDeviceType);
        
        if ($deviceManager->dynamicTableExists($tableName)) {
            // 動的テーブルのカラムを取得（primary_key, created_at, updated_at を除く）
            $dynamicColumnsResult = $database->getTableColumns($tableName);
            $dynamicColumns = array_column($dynamicColumnsResult, 'COLUMN_NAME');
            $excludeColumns = ['primary_key', 'created_at', 'updated_at'];
            $extendedColumns = array_diff($dynamicColumns, $excludeColumns);
            
            // プレビュー用のカラムヘッダーを作成
            $previewColumns = [
                'サービス名', '装置種別', '装置名称', '装置IP', 'ユーザー名', 'パスワード'
            ];
            $previewColumns = array_merge($previewColumns, $extendedColumns);
            
            // JOINクエリでデータを取得（最初の10件）
            $dynamicColumnList = [];
            foreach ($extendedColumns as $col) {
                $dynamicColumnList[] = "dt.`{$col}`";
            }
            $dynamicColumnStr = !empty($dynamicColumnList) ? ', ' . implode(', ', $dynamicColumnList) : '';
            
            $sql = "
                SELECT 
                    di.service_name as 'サービス名',
                    di.device_type as '装置種別',
                    di.device_name as '装置名称',
                    di.device_ip as '装置IP',
                    di.username as 'ユーザー名',
                    di.password as 'パスワード'
                    {$dynamicColumnStr}
                FROM device_info di
                LEFT JOIN `{$tableName}` dt ON di.primary_key = dt.primary_key
                WHERE di.service_name = ? AND di.device_type = ?
                ORDER BY di.created_at DESC
                LIMIT 10
            ";
            
            $stmt = $database->execute($sql, [$selectedService, $selectedDeviceType]);
            $previewData = $stmt->fetchAll();
            
            // 総件数を取得
            $countSql = "
                SELECT COUNT(*) as total 
                FROM device_info di
                LEFT JOIN `{$tableName}` dt ON di.primary_key = dt.primary_key
                WHERE di.service_name = ? AND di.device_type = ?
            ";
            $countStmt = $database->execute($countSql, [$selectedService, $selectedDeviceType]);
            $totalCount = $countStmt->fetchColumn();
        }
    }
    
    // CSVダウンロード処理
    if ($_POST['action'] ?? '' === 'download_csv') {
        $service = $_POST['service_name'] ?? '';
        $deviceType = $_POST['device_type'] ?? '';
        
        if (!empty($service) && !empty($deviceType)) {
            $tableName = sanitizeTableName($service . '_' . $deviceType);
            
            if ($deviceManager->dynamicTableExists($tableName)) {
                // 動的テーブルのカラムを取得（primary_key, created_at, updated_at を除く）
                $dynamicColumnsResult = $database->getTableColumns($tableName);
                $dynamicColumns = array_column($dynamicColumnsResult, 'COLUMN_NAME');
                $excludeColumns = ['primary_key', 'created_at', 'updated_at'];
                $extendedColumns = array_diff($dynamicColumns, $excludeColumns);
                
                // JOINクエリでデータを取得
                $dynamicColumnList = [];
                foreach ($extendedColumns as $col) {
                    $dynamicColumnList[] = "dt.`{$col}`";
                }
                $dynamicColumnStr = !empty($dynamicColumnList) ? ', ' . implode(', ', $dynamicColumnList) : '';
                
                $sql = "
                    SELECT 
                        di.service_name as 'サービス名',
                        di.device_type as '装置種別',
                        di.device_name as '装置名称',
                        di.device_ip as '装置IP',
                        di.username as 'ユーザー名',
                        di.password as 'パスワード'
                        {$dynamicColumnStr}
                    FROM device_info di
                    LEFT JOIN `{$tableName}` dt ON di.primary_key = dt.primary_key
                    WHERE di.service_name = ? AND di.device_type = ?
                    ORDER BY di.created_at DESC
                ";
                
                $stmt = $database->execute($sql, [$service, $deviceType]);
                $data = $stmt->fetchAll();
                
                if (!empty($data)) {
                    // 選択されたフィールドを取得
                    $selectedFields = $_POST['selected_fields'] ?? [];
                    
                    // デバッグ: 選択されたフィールドをログ出力
                    error_log("=== CSV Download Debug ===");
                    error_log("POST data: " . print_r($_POST, true));
                    error_log("Selected fields: " . print_r($selectedFields, true));
                    error_log("Available fields: " . print_r(array_keys($data[0] ?? []), true));
                    
                    // CSVファイル名を生成
                    $filename = $service . '_' . $deviceType . '_' . date('Y-m-d_H-i-s') . '.csv';
                    
                    // HTTPヘッダーを設定
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Cache-Control: no-cache, must-revalidate');
                    
                    // BOMを出力（Excelでの文字化け対策）
                    echo "\xEF\xBB\xBF";
                    
                    // CSVデータを出力
                    $output = fopen('php://output', 'w');
                    
                    // ヘッダー行を出力（選択されたフィールドのみ）
                    if (!empty($selectedFields)) {
                        fputcsv($output, $selectedFields);
                        
                        // データ行を出力（選択されたフィールドのみ）
                        foreach ($data as $row) {
                            $filteredRow = [];
                            foreach ($selectedFields as $field) {
                                $filteredRow[] = $row[$field] ?? '';
                            }
                            fputcsv($output, $filteredRow);
                        }
                    } else {
                        // 選択フィールドがない場合は全フィールド出力
                        $headers = array_keys($data[0]);
                        fputcsv($output, $headers);
                        
                        foreach ($data as $row) {
                            fputcsv($output, $row);
                        }
                    }
                    
                    fclose($output);
                    exit;
                } else {
                    setErrorMessage('ダウンロードするデータがありません。');
                }
            } else {
                setErrorMessage('指定されたテーブルが存在しません。');
            }
        } else {
            setErrorMessage('サービス名と装置種別を選択してください。');
        }
    }
    
} catch (Exception $e) {
    setErrorMessage("エラーが発生しました: " . $e->getMessage());
    $services = [];
    $deviceTypes = [];
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
                        <?php include 'svgs/download.svg'; ?>
                    </div>
                    CSVダウンロード
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
        
        <div class="alert alert-info">
            <h4 style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 20px; height: 20px; display: inline-flex;"><?php include 'svgs/info.svg'; ?></span>
                CSVダウンロード機能について
            </h4>
            <p>
                サービス名と装置種別を選択すると、該当する装置データをCSV形式でダウンロードできます。
                ダウンロード前にデータのプレビューを確認できます。
            </p>
        </div>
        
        <!-- 検索・ダウンロードフォーム -->
        <div class="form-section">
            <h3>
                <div class="nav-icon">
                    <?php include 'svgs/search.svg'; ?>
                </div>
                ダウンロード対象の選択   
            </h3>
            
            <form method="post" id="searchForm">
                <div class="form-group">
                    <label for="service_name">サービス名:</label>
                    <select name="service_name" id="service_name" class="form-control" onchange="updateDeviceTypes()">
                        <option value="">-- サービス名を選択 --</option>
                        <?php foreach ($services as $service): ?>
                        <option value="<?= h($service) ?>" <?= $service === $selectedService ? 'selected' : '' ?>>
                            <?= h($service) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="device_type">装置種別:</label>
                    <select name="device_type" id="device_type" class="form-control" onchange="updatePreview()">
                        <option value="">-- 装置種別を選択 --</option>
                        <?php foreach ($deviceTypes as $deviceType): ?>
                        <option value="<?= h($deviceType) ?>" <?= $deviceType === $selectedDeviceType ? 'selected' : '' ?>>
                            <?= h($deviceType) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="updatePreview()">
                        プレビュー表示
                    </button>
                </div>
            </form>
        </div>
        
        <!-- データプレビュー -->
        <?php if (!empty($selectedService) && !empty($selectedDeviceType)): ?>
        <div class="preview-section">
            <?php if ($deviceManager->dynamicTableExists($tableName)): ?>
            <div class="table-info">
                <h4>📊 テーブル情報</h4>
                <p>
                    <strong>テーブル名:</strong> <?= h($tableName) ?><br>
                    <strong>総データ数:</strong> <?= number_format($totalCount) ?>件<br>
                    <strong>プレビュー:</strong> 最初の<?= count($previewData) ?>件を表示
                </p>
            </div>
            
            <?php if (!empty($previewData)): ?>
            <h4 style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 20px; height: 20px; display: inline-flex;"><?php include 'svgs/info.svg'; ?></span>
                データプレビュー
            </h4>
            <div style="overflow-x: auto;">
                <table id="previewTable" class="preview-table transposed">
                    <?php
                    // データを逆転させて表示
                    if (!empty($previewData)) {
                        $transposedData = [];
                        
                        // 各カラムのデータを集める
                        foreach ($previewColumns as $colIndex => $columnName) {
                            $transposedData[$columnName] = [];
                            foreach ($previewData as $row) {
                                $values = array_values($row);
                                $transposedData[$columnName][] = $values[$colIndex] ?? '';
                            }
                        }
                        
                        // 逆転表示
                        echo '<thead><tr>';
                        echo '<th class="checkbox-header"><input type="checkbox" id="select_all" checked onchange="toggleAllCheckboxes(this.checked)"></th>';
                        echo '<th class="row-header">項目</th>';
                        for ($i = 0; $i < count($previewData); $i++) {
                            echo '<th>データ' . ($i + 1) . '</th>';
                        }
                        echo '</tr></thead>';
                        
                        echo '<tbody>';
                        foreach ($transposedData as $fieldName => $fieldValues) {
                            $fieldId = 'field_' . md5($fieldName); // ユニークなID生成
                            echo '<tr>';
                            echo '<td class="checkbox-cell"><input type="checkbox" id="' . $fieldId . '" name="selected_fields[]" value="' . h($fieldName) . '" checked></td>';
                            echo '<th class="row-header">' . h($fieldName) . '</th>';
                            foreach ($fieldValues as $value) {
                                echo '<td title="' . h($value) . '">' . h($value) . '</td>';
                            }
                            echo '</tr>';
                        }
                        echo '</tbody>';
                    }
                    ?>
                </table>
            </div>
            
            <!-- ダウンロードボタン -->
            <div class="download-section">
                <h4>📥 CSVダウンロード</h4>
                <p id="download-info">全<?= number_format($totalCount) ?>件のデータをCSVファイルとしてダウンロードします。</p>
                
                <form method="post" id="downloadForm">
                    <input type="hidden" name="action" value="download_csv">
                    <input type="hidden" name="service_name" value="<?= h($selectedService) ?>">
                    <input type="hidden" name="device_type" value="<?= h($selectedDeviceType) ?>">
                    
                    <!-- 選択されたフィールドをここに動的に追加 -->
                    
                    <button type="submit" class="btn btn-success">
                        💾 CSV ダウンロード
                    </button>
                </form>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <h3>データがありません</h3>
                <p>選択されたサービス・装置種別に対応するデータが見つかりません。</p>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <h3>テーブルが存在しません</h3>
                <p>選択されたサービス・装置種別に対応するテーブルがまだ作成されていません。<br>
                先にCSVファイルをアップロードしてデータを登録してください。</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($selectedService) || empty($selectedDeviceType)): ?>
        <div class="empty-state">
            <h3 style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                <span style="width: 24px; height: 24px; display: inline-flex;"><?php include 'svgs/info.svg'; ?></span>
                使い方
            </h3>
            <ol style="text-align: left; display: inline-block;">
                <li>サービス名を選択してください</li>
                <li>装置種別を選択してください</li>
                <li>「プレビュー表示」ボタンでデータを確認</li>
                <li>「CSVダウンロード」ボタンでファイルを取得</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // 装置種別の更新
        async function updateDeviceTypes() {
            const serviceName = document.getElementById('service_name').value;
            const deviceTypeSelect = document.getElementById('device_type');
            
            // 現在の選択値を保存
            const currentValue = deviceTypeSelect.value;
            
            // 装置種別をリセット
            deviceTypeSelect.innerHTML = '<option value="">-- 装置種別を選択 --</option>';
            
            if (serviceName) {
                try {
                    const response = await fetch(`ajax_api.php?action=get_device_types&service_name=${encodeURIComponent(serviceName)}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        result.data.forEach(deviceType => {
                            const option = document.createElement('option');
                            option.value = deviceType;
                            option.textContent = deviceType;
                            // 以前の選択値と一致する場合は選択状態にする
                            if (deviceType === currentValue) {
                                option.selected = true;
                            }
                            deviceTypeSelect.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('装置種別取得エラー:', error);
                }
            }
        }

        // プレビューの更新
        function updatePreview() {
            const serviceName = document.getElementById('service_name').value;
            const deviceType = document.getElementById('device_type').value;
            
            if (serviceName && deviceType) {
                const params = new URLSearchParams();
                params.append('service_name', serviceName);
                params.append('device_type', deviceType);
                
                window.location.href = '?' + params.toString();
            }
        }
        
        // ページ読み込み時に装置種別を更新（選択状態を保持）
        document.addEventListener('DOMContentLoaded', async function() {
            const serviceName = document.getElementById('service_name').value;
            const deviceTypeSelect = document.getElementById('device_type');
            const selectedDeviceType = '<?= h($selectedDeviceType) ?>';
            
            if (serviceName && selectedDeviceType) {
                // プレビュー表示時は選択状態を保持するため、装置種別リストを更新
                await updateDeviceTypes();
            } else if (serviceName && !selectedDeviceType) {
                // プレビュー前は通常通り更新
                await updateDeviceTypes();
            }
        });
        
        // チェックボックス関連機能
        function toggleAllCheckboxes(checked) {
            const checkboxes = document.querySelectorAll('input[name="selected_fields[]"]');
            checkboxes.forEach(cb => cb.checked = checked);
            updateDownloadButton();
        }
        
        function updateDownloadButton() {
            const selectedFields = getSelectedFields();
            const downloadBtn = document.querySelector('#downloadForm button');
            if (downloadBtn) {
                const baseText = '💾 CSV ダウンロード';
                const countText = selectedFields.length > 0 ? ` (${selectedFields.length}項目)` : ' (項目未選択)';
                downloadBtn.textContent = baseText + countText;
            }
        }
        
        function getSelectedFields() {
            const checkboxes = document.querySelectorAll('input[name="selected_fields[]"]:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        function updateSelectedFields() {
            // チェックボックス変更時にダウンロードボタンを更新
            updateDownloadButton();
        }
        
        // CSVダウンロード時のフィールド選択を考慮
        document.addEventListener('DOMContentLoaded', function() {
            const downloadForm = document.getElementById('downloadForm');
            console.log('downloadForm found:', downloadForm);
            
            if (downloadForm) {
                downloadForm.addEventListener('submit', function(e) {
                    const selectedFields = getSelectedFields();
                    console.log('Submit event - Selected fields:', selectedFields);
                    
                    if (selectedFields.length === 0) {
                        alert('少なくとも1つの項目を選択してください。');
                        e.preventDefault();
                        return;
                    }
                    
                    // 既存のhiddenフィールドをクリア
                    const existingHidden = downloadForm.querySelectorAll('input[type="hidden"][name="selected_fields[]"]');
                    console.log('Existing hidden fields to remove:', existingHidden.length);
                    existingHidden.forEach(input => input.remove());
                    
                    // 選択されたフィールドをフォームに追加
                    selectedFields.forEach(field => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_fields[]';
                        input.value = field;
                        downloadForm.appendChild(input);
                        console.log('Added hidden field:', field);
                    });
                    
                    // 最終的なフォームデータを確認
                    const formData = new FormData(downloadForm);
                    console.log('Final form data:');
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }
                });
            }
            
            // チェックボックスにイベントリスナーを追加
            const checkboxes = document.querySelectorAll('input[name="selected_fields[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedFields);
            });
            
            // 「すべて選択/解除」チェックボックスにもイベントリスナー
            const selectAllCheckbox = document.getElementById('select_all');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    toggleAllCheckboxes(this.checked);
                    updateDownloadButton(); // ボタンも更新
                });
            }
            
            // 初期表示時にダウンロードボタンを更新
            updateDownloadButton();
        });
    </script>

        </div> <!-- page-container -->
    </div> <!-- main-content -->

<?php require_once 'includes/footer.php'; ?>