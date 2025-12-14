<?php
/**
 * データベース初期化スクリプト
 * アプリケーションの初回セットアップ時に実行
 */

require_once '../config.php';

try {
    echo "=== 装置情報管理システム - データベース初期化 ===\n\n";
    
    // データベース接続テスト
    echo "1. データベース接続テスト...\n";
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
    $pdo = $database->connect();
    echo "   ✓ 接続成功\n\n";
    
    // 装置情報テーブルの作成
    echo "2. 装置情報テーブル作成...\n";
    $deviceManager = new DeviceManager($database);
    
    if (!$deviceManager->deviceInfoTableExists()) {
        $deviceManager->createDeviceInfoTable();
        echo "   ✓ device_info テーブルを作成しました\n";
    } else {
        echo "   - device_info テーブルは既に存在します\n";
    }
    
    // リレーションテーブルの作成
    if (!$deviceManager->relationTableExists()) {
        $deviceManager->createRelationTable();
        echo "   ✓ service_device_type_relations テーブルを作成しました\n";
    } else {
        echo "   - service_device_type_relations テーブルは既に存在します\n";
    }
    
    // テーブル情報の表示
    echo "\n3. テーブル構造確認...\n";
    $columns = $database->getTableColumns('device_info');
    echo "   device_info テーブルのカラム:\n";
    foreach ($columns as $column) {
        echo "   - {$column['COLUMN_NAME']} ({$column['DATA_TYPE']})\n";
    }
    
    // アップロードディレクトリの確認
    echo "\n4. アップロードディレクトリ確認...\n";
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
        echo "   ✓ アップロードディレクトリを作成しました: " . UPLOAD_DIR . "\n";
    } else {
        echo "   - アップロードディレクトリは既に存在します: " . UPLOAD_DIR . "\n";
    }
    
    // 権限確認
    if (is_writable(UPLOAD_DIR)) {
        echo "   ✓ アップロードディレクトリは書き込み可能です\n";
    } else {
        echo "   ⚠ アップロードディレクトリに書き込み権限がありません\n";
    }
    
    // サンプルCSVファイルの存在確認
    echo "\n5. サンプルファイル確認...\n";
    $sampleCsvPath = __DIR__ . '/sample.csv';
    if (file_exists($sampleCsvPath)) {
        echo "   ✓ サンプルCSVファイルが存在します: sample.csv\n";
        
        // サンプルファイルの内容確認
        $csvProcessor = new CsvProcessor();
        if ($csvProcessor->loadFile($sampleCsvPath)) {
            $stats = $csvProcessor->getStatistics();
            echo "   - データ行数: {$stats['total_rows']}\n";
            echo "   - サービス: " . implode(', ', $stats['services']) . "\n";
            echo "   - 装置種別: " . implode(', ', $stats['device_types']) . "\n";
        }
    } else {
        echo "   ⚠ sample.csv が見つかりません\n";
    }
    
    // 設定値の確認
    echo "\n6. アプリケーション設定確認...\n";
    echo "   - データベースホスト: " . DB_HOST . "\n";
    echo "   - データベース名: " . DB_NAME . "\n";
    echo "   - 最大アップロードサイズ: " . (UPLOAD_MAX_SIZE / 1024 / 1024) . "MB\n";
    echo "   - 許可ファイル形式: " . implode(', ', UPLOAD_ALLOWED_TYPES) . "\n";
    
    echo "\n=== 初期化完了 ===\n";
    echo "ブラウザで index.php にアクセスしてアプリケーションを開始してください。\n";
    
} catch (Exception $e) {
    echo "❌ エラーが発生しました: " . $e->getMessage() . "\n";
    echo "\n設定を確認してください:\n";
    echo "1. MySQLサーバーが起動していることを確認\n";
    echo "2. config.php のデータベース接続情報を確認\n";
    echo "3. データベース '{DB_NAME}' が存在することを確認\n";
    echo "4. ユーザー '{DB_USER}' に適切な権限があることを確認\n";
}
?>