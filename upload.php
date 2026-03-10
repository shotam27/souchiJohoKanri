<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

// GETリクエストの場合はフォームを表示
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pageTitle = '装置情報管理システム - CSVアップロード';
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
                        <?php include 'svgs/upload.svg'; ?>
                    </div>
                    CSVファイルアップロード
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
        
        <div class="info-box">
            <h3>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                </svg>
                CSVファイル形式について
            </h3>
            <p>以下の形式のCSVファイルをアップロードしてください：</p>
            <div class="csv-format">
サービス名,装置種別,装置名称,ユーザ名1,装置IP,パスワード,その他カラム1,その他カラム2
サービスA,装置種別A,souchimei,admin,198.1.1.1,admin123,値1,値2
サービスA,装置種別A,souchimei2,admin,198.1.1.2,admin123,値3,値4
            </div>
            <ul>
                <li><strong>必須項目:</strong> サービス名、装置種別、装置名称、ユーザ名</li>
                <li><strong>任意項目:</strong> 装置IP、パスワード</li>
                <li><strong>主キー:</strong> [サービス名]_[装置種別]_[装置名称]_[ユーザ名]</li>
                <li><strong>その他のカラム:</strong> 
                    <ul>
                        <li>device_infoテーブルに存在するカラムの場合 → device_infoに登録</li>
                        <li>存在しない場合 → [サービス名]_[装置種別]テーブルに登録</li>
                        <li>カラムが存在しない場合は自動で追加されます</li>
                    </ul>
                </li>
                <li><strong>文字エンコーディング:</strong> UTF-8</li>
                <li><strong>ファイルサイズ制限:</strong> 最大10MB</li>
            </ul>
        </div>
        
        <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(generateCsrfToken()) ?>">
            
            <div class="form-group flex-col">
                <label for="csvFile">CSVファイルを選択:</label>
                
                <!-- ドラッグ&ドロップエリア -->
                <div class="drag-drop-area" id="dragDropArea">
                    <div class="drag-drop-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                    <div class="drag-drop-text">
                        CSVファイルをここにドラッグ&ドロップ
                    </div>
                    <div class="drag-drop-subtext">
                        または<strong>クリック</strong>してファイルを選択
                    </div>
                </div>
                
                <!-- 隠されたファイル入力 -->
                <input type="file" 
                       id="csvFile" 
                       name="csv_file" 
                       accept=".csv,text/csv,application/csv" 
                       class="file-input-hidden">
                
                <!-- 選択されたファイル情報 -->
                <div class="selected-file-info" id="selectedFileInfo">
                    <div class="file-details">
                        <div class="file-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                        </div>
                        <div class="file-info-text">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                    </div>
                </div>
                
                <div class="file-info" style="margin-top: 10px;">
                    ※ CSVファイル（.csv）のみアップロード可能です（最大10MB）
                </div>
            </div>
            
            <div class="upload-progress" id="uploadProgress">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" id="uploadBtn">
                    CSVファイルをアップロード
                </button>
            </div>
        </form>
    </div>

    <script>
        // ドラッグ&ドロップ機能
        const dragDropArea = document.getElementById('dragDropArea');
        const fileInput = document.getElementById('csvFile');
        const selectedFileInfo = document.getElementById('selectedFileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        // ドラッグ&ドロップエリアのクリックでファイル選択ダイアログを開く
        dragDropArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // ドラッグオーバー（ドロップを許可するため必須）
        dragDropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'copy';
            dragDropArea.classList.add('drag-over');
        });
        
        // ドラッグエンター
        dragDropArea.addEventListener('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragDropArea.classList.add('drag-over');
        });
        
        // ドラッグリーブ
        dragDropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // ドロップエリア自体から出た場合のみクラスを削除
            if (e.target === dragDropArea) {
                dragDropArea.classList.remove('drag-over');
            }
        });
        
        // ドロップ
        dragDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dragDropArea.classList.remove('drag-over');
            
            console.log('ファイルドロップイベント発生');
            console.log('e.dataTransfer:', e.dataTransfer);
            
            const files = e.dataTransfer.files;
            console.log('ドロップされたファイル数:', files ? files.length : 'undefined');
            console.log('files オブジェクト:', files);
            
            if (!files || files.length === 0) {
                alert('⚠️ ファイルを検出できませんでした\n\nもう一度お試しください。\n\n通常のファイル選択ボタンをお試しください。');
                console.error('ドロップされたファイルが見つかりません');
                console.error('e.dataTransfer.files:', e.dataTransfer.files);
                console.error('e.dataTransfer.items:', e.dataTransfer.items);
                return;
            }
            
            if (files.length > 1) {
                alert('⚠️ 複数のファイルが選択されています\n\n1つのCSVファイルのみを選択してください。');
                console.warn('複数ファイル検出:', files.length, '個');
                return;
            }
            
            const file = files[0];
            console.log('ファイル詳細:', {
                name: file.name,
                size: file.size + ' bytes',
                type: file.type,
                lastModified: new Date(file.lastModified).toLocaleString('ja-JP')
            });
            
            if (validateFile(file)) {
                // DataTransferオブジェクトを使ってファイル入力に設定
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                displayFileInfo(file);
                console.log('✓ ファイル設定完了');
            } else {
                console.error('ファイル検証失敗:', file.name);
            }
        });
        
        // ファイル選択（通常の方法）
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                displayFileInfo(file);
            }
        });
        
        // ファイル情報を表示
        function displayFileInfo(file) {
            const fileSizeBytes = file.size;
            let fileSizeText;
            
            if (fileSizeBytes === 0) {
                fileSizeText = '0 KB (ファイルが空です)';
                alert('⚠️ エラー: ファイルサイズが0バイトです\n\nファイル名: ' + file.name + '\n\n可能な原因:\n1. ファイルが空です\n2. ファイルが破損しています\n3. ファイルへのアクセス権限がありません\n\n別のファイルを選択するか、ファイルを確認してください。');
            } else if (fileSizeBytes < 1024) {
                fileSizeText = fileSizeBytes + ' B';
            } else if (fileSizeBytes < 1024 * 1024) {
                fileSizeText = (fileSizeBytes / 1024).toFixed(2) + ' KB';
            } else {
                fileSizeText = (fileSizeBytes / 1024 / 1024).toFixed(2) + ' MB';
            }
            
            fileName.textContent = file.name;
            fileSize.textContent = fileSizeText;
            
            selectedFileInfo.classList.add('show');
            dragDropArea.classList.add('has-file');
            
            // ドラッグエリアのテキストを更新
            const dragDropText = dragDropArea.querySelector('.drag-drop-text');
            const dragDropSubtext = dragDropArea.querySelector('.drag-drop-subtext');
            dragDropText.textContent = 'ファイルが選択されました';
            dragDropSubtext.innerHTML = '<strong>クリック</strong>して別のファイルを選択';
        }
        
        // ファイル検証
        function validateFile(file) {
            console.log('ファイル検証開始:', {
                name: file.name,
                size: file.size,
                type: file.type,
                lastModified: new Date(file.lastModified)
            });
            
            // ファイルサイズが0バイトチェック
            if (file.size === 0) {
                alert('❌ エラー: ファイルが空です\n\nファイル名: ' + file.name + '\nファイルサイズ: 0バイト\n\nファイルの内容を確認してください。');
                console.error('ファイルサイズが0バイト:', file.name);
                return false;
            }
            
            // ファイルサイズチェック
            if (file.size > <?= UPLOAD_MAX_SIZE ?>) {
                alert('❌ エラー: ファイルサイズが大きすぎます\n\nファイル名: ' + file.name + '\nファイルサイズ: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB\n\n10MB以下のファイルを選択してください。');
                console.error('ファイルサイズ超過:', file.size, 'bytes');
                return false;
            }
            
            // ファイル形式チェック
            const allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
            const fileName = file.name.toLowerCase();
            const hasValidExtension = fileName.endsWith('.csv');
            const hasValidMimeType = allowedTypes.includes(file.type);
            
            if (!hasValidExtension && !hasValidMimeType) {
                alert('❌ エラー: CSVファイルのみアップロード可能です\n\nファイル名: ' + file.name + '\nファイル形式: ' + (file.type || '不明') + '\n\n.csv形式のファイルを選択してください。');
                console.error('無効なファイル形式:', { type: file.type, name: file.name });
                return false;
            }
            
            console.log('✓ ファイル検証成功');
            return true;
        }
        
        // フォーム送信
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            
            console.log('フォーム送信開始');
            
            if (!fileInput.files.length) {
                alert('❌ CSVファイルを選択してください。');
                console.error('ファイルが選択されていません');
                e.preventDefault();
                return;
            }
            
            const file = fileInput.files[0];
            console.log('送信するファイル:', {
                name: file.name,
                size: file.size + ' bytes',
                type: file.type
            });
            
            if (file.size === 0) {
                alert('❌ エラー: ファイルが空です\n\nファイル名: ' + file.name + '\n\n空のファイルはアップロードできません。');
                console.error('空のファイルが選択されています');
                e.preventDefault();
                return;
            }
            
            if (!validateFile(file)) {
                console.error('ファイル検証失敗');
                e.preventDefault();
                return;
            }
            
            // アップロード開始
            console.log('✓ アップロード開始');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'アップロード中...';
            uploadProgress.style.display = 'block';
            
            // 擬似的なプログレスバー
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += 5;
                document.getElementById('progressBar').style.width = progress + '%';
                if (progress >= 90) {
                    clearInterval(progressInterval);
                }
            }, 100);
        });
    </script>

        </div> <!-- page-container -->
    </div> <!-- main-content -->

    <?php 
    require_once 'includes/footer.php';
    exit;
}

// POSTリクエストの処理

// CSRFトークンの検証
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    setErrorMessage('CSRFトークンが無効です');
    header('Location: upload.php');
    exit;
}

try {
    // ファイルアップロードの検証
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズが大きすぎます',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズが大きすぎます',
            UPLOAD_ERR_PARTIAL => 'ファイルのアップロードが完了していません',
            UPLOAD_ERR_NO_FILE => 'ファイルが選択されていません',
            UPLOAD_ERR_NO_TMP_DIR => 'テンポラリディレクトリが見つかりません',
            UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'ファイルのアップロードが停止されました'
        ];
        
        $error_code = $_FILES['csv_file']['error'];
        $error_message = isset($error_messages[$error_code]) ? 
                        $error_messages[$error_code] : 
                        '不明なアップロードエラーが発生しました';
        
        throw new Exception($error_message);
    }
    
    $uploaded_file = $_FILES['csv_file'];
    
    // ファイルサイズの検証
    if ($uploaded_file['size'] > UPLOAD_MAX_SIZE) {
        throw new Exception('ファイルサイズが制限を超えています（最大: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB）');
    }
    
    // ファイル形式の検証
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $uploaded_file['tmp_name']);
    finfo_close($file_info);
    
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mime_type, UPLOAD_ALLOWED_TYPES) && $file_extension !== 'csv') {
        throw new Exception('CSVファイル以外はアップロードできません');
    }
    
    // アップロードディレクトリの作成
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception('アップロードディレクトリの作成に失敗しました');
        }
    }
    
    // ファイル名のサニタイズ
    $original_filename = $uploaded_file['name'];
    $sanitized_filename = date('Y-m-d_H-i-s') . '_' . sanitizeFilename($original_filename);
    $upload_path = UPLOAD_DIR . $sanitized_filename;
    
    // ファイルを移動
    if (!move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
        throw new Exception('ファイルの保存に失敗しました');
    }
    
    // UTF-8 BOM が先頭にある場合は除去する（CSV処理前）
    $file_contents = @file_get_contents($upload_path);
    if ($file_contents !== false && substr($file_contents, 0, 3) === "\xEF\xBB\xBF") {
        if (file_put_contents($upload_path, substr($file_contents, 3)) === false) {
            throw new Exception('ファイルの保存後のBOM除去に失敗しました');
        }
        error_log("Removed UTF-8 BOM from uploaded file: " . $upload_path);
    }
    
    // CSVファイルの処理
    $csv_processor = new CsvProcessor();
    if (!$csv_processor->loadFile($upload_path)) {
        $errors = $csv_processor->getErrors();
        throw new Exception('CSVファイルの読み込みに失敗しました: ' . implode(', ', $errors));
    }
    
    // CSVデータの検証
    if (!$csv_processor->validate()) {
        $errors = $csv_processor->getErrors();
        throw new Exception('CSVデータの検証に失敗しました: ' . implode(', ', $errors));
    }
    
    // データベース接続
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    
    // データベース初期化（必須テーブルの存在確認と作成）
    $db_initializer = new DatabaseInitializer($database);
    error_log("Initializing required database tables");
    $init_results = $db_initializer->initializeAllTables();
    
    if (!empty($init_results['tables_created'])) {
        error_log("Created tables: " . implode(', ', $init_results['tables_created']));
    }
    
    if (!empty($init_results['errors'])) {
        throw new Exception('データベース初期化エラー: ' . implode(', ', $init_results['errors']));
    }
    
    $device_manager = new DeviceManager($database);
    $activity_logger = new ActivityLogger($database); // ログ記録用

    // CSVデータをデータベースに登録
    $results = $device_manager->processCsvData($csv_processor);
    
    // 処理結果の確認
    if (!$results['success']) {
        throw new Exception('データベースへの登録に失敗しました: ' . implode(', ', $results['errors']));
    }
    
    // CSVデータからサービス-装置種別のリレーションを自動登録（別トランザクション）
    $relationCount = 0;
    try {
        $csvData = $csv_processor->getData();
        $processedRelations = [];
        
        error_log("Starting relation registration for " . count($csvData) . " records");
        
        foreach ($csvData as $row) {
            $serviceName = $row['サービス名'];
            $deviceType = $row['装置種別'];
            
            // 重複チェック（同一処理内での重複を避ける）
            $relationKey = $serviceName . '|' . $deviceType;
            if (!in_array($relationKey, $processedRelations)) {
                try {
                    $description = "CSV自動登録: " . date('Y-m-d H:i:s') . " - " . basename($original_filename);
                    error_log("Registering relation: {$serviceName} -> {$deviceType}");
                    $device_manager->registerServiceDeviceTypeRelation($serviceName, $deviceType, $description);
                    $relationCount++;
                    $processedRelations[] = $relationKey;
                    error_log("Successfully registered relation: {$relationKey}");
                } catch (Exception $e) {
                    // リレーション登録のエラーは警告として扱う（処理は継続）
                    error_log("Relation registration warning for {$relationKey}: " . $e->getMessage());
                }
            } else {
                error_log("Skipping duplicate relation: {$relationKey}");
            }
        }
        
        error_log("Completed relation registration. Total: {$relationCount} relations");
    } catch (Exception $e) {
        // リレーション登録エラーは警告として扱う
        error_log("Relation registration process error: " . $e->getMessage());
        $relationCount = 0;
    }
    
    // 統計情報の取得
    $statistics = $csv_processor->getStatistics();
    
    // 成功メッセージの設定
    $success_message = "CSVファイルの処理が完了しました。\n";
    $success_message .= "- 処理レコード数: " . $results['device_info_count'] . "件\n";
    $success_message .= "- 作成された動的テーブル: " . count($results['dynamic_tables_created']) . "個\n";
    
    if (!empty($results['dynamic_tables_created'])) {
        $success_message .= "- テーブル名: " . implode(', ', $results['dynamic_tables_created']) . "\n";
    }
    
    $success_message .= "- サービス数: " . count($statistics['services']) . "種類\n";
    $success_message .= "- 装置種別数: " . count($statistics['device_types']) . "種類\n";
    $success_message .= "- 自動登録されたリレーション: " . $relationCount . "件";
    
    setSuccessMessage($success_message);

    // 操作ログを記録
    $logDetail = sprintf(
        'ファイル: %s, レコード数: %d, サービス数: %d, 装置種別数: %d',
        $original_filename,
        $results['device_info_count'],
        count($statistics['services']),
        count($statistics['device_types'])
    );
    $activity_logger->log(
        getLoggedInUsername() ?? 'unknown',
        ActivityLogger::ACTION_UPLOAD,
        $logDetail
    );

    // アップロードされたファイルを削除（オプション）
    // unlink($upload_path);
    
} catch (Exception $e) {
    // 詳細なエラー情報をログに記録
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("Upload error details: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    
    // デバッグ用：ファイルにも書き出す
    @file_put_contents('/tmp/fusion_upload_debug.log', 
        date('Y-m-d H:i:s') . "\n" . 
        json_encode($errorDetails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n", 
        FILE_APPEND
    );
    
    setErrorMessage("アップロード処理中にエラーが発生しました: " . $e->getMessage());
    
    // データベースのトランザクション状態を確認してロールバック
    if (isset($database)) {
        try {
            if ($database->inTransaction()) {
                $database->rollBack();
                error_log("Transaction rolled back due to error");
            }
        } catch (Exception $rollbackError) {
            error_log("Rollback error: " . $rollbackError->getMessage());
        }
    }
    
    // エラー時はアップロードされたファイルを削除
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
} finally {
    // データベース接続を閉じる
    if (isset($database)) {
        try {
            $database->close();
        } catch (Exception $closeError) {
            error_log("Database close error: " . $closeError->getMessage());
        }
    }
}

// 結果ページにリダイレクト
header('Location: upload.php');
exit;
?>