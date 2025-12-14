<?php
require_once 'config.php';

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setErrorMessage('不正なリクエストです');
    header('Location: index.php');
    exit;
}

// CSRFトークンの検証
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    setErrorMessage('CSRFトークンが無効です');
    header('Location: index.php');
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
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
    $device_manager = new DeviceManager($database);
    
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
header('Location: index.php');
exit;
?>