<?php
/**
 * CSV一括登録 CLI スクリプト
 *
 * 使い方:
 *   php import_csv.php <CSVファイルパス> [ユーザー名]
 *
 * 例:
 *   php import_csv.php /path/to/devices.csv
 *   php import_csv.php /path/to/devices.csv admin
 */

// CLI 専用チェック
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("このスクリプトはCLIからのみ実行できます。\n");
}

// 引数チェック
if ($argc < 2) {
    fwrite(STDERR, "使い方: php import_csv.php <CSVファイルパス> [ユーザー名]\n");
    fwrite(STDERR, "例:     php import_csv.php ./uploads/devices.csv admin\n");
    exit(1);
}

$csvFilePath = $argv[1];
$cliUser     = $argv[2] ?? 'cli';

// ファイル存在確認
if (!file_exists($csvFilePath)) {
    fwrite(STDERR, "エラー: ファイルが見つかりません: {$csvFilePath}\n");
    exit(1);
}
if (!is_readable($csvFilePath)) {
    fwrite(STDERR, "エラー: ファイルを読み込めません: {$csvFilePath}\n");
    exit(1);
}

// config.php の session_start() を CLI でも安全に通すため
// セッション処理を無効化してから読み込む
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');

require_once __DIR__ . '/config.php';

// ---- メイン処理 ----
echo "=== CSV インポート開始 ===\n";
echo "ファイル : {$csvFilePath}\n";
echo "実行ユーザー: {$cliUser}\n";
echo str_repeat('-', 40) . "\n";

try {
    // UTF-8 BOM 除去
    $contents = file_get_contents($csvFilePath);
    if ($contents === false) {
        throw new Exception("ファイルの読み込みに失敗しました");
    }
    if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
        $contents = substr($contents, 3);
        file_put_contents($csvFilePath, $contents);
        echo "[INFO] UTF-8 BOM を除去しました\n";
    }

    // アップロードディレクトリへコピー（履歴保持）
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $savedName = date('Y-m-d_H-i-s') . '_' . sanitizeFilename(basename($csvFilePath));
    $savedPath = UPLOAD_DIR . $savedName;
    copy($csvFilePath, $savedPath);
    echo "[INFO] ファイルを保存しました: uploads/{$savedName}\n";

    // CSV 読み込み・検証
    $csv = new CsvProcessor();
    if (!$csv->loadFile($csvFilePath)) {
        $errs = $csv->getErrors();
        throw new Exception("CSV読み込みエラー: " . implode(', ', $errs));
    }
    if (!$csv->validate()) {
        $errs = $csv->getErrors();
        throw new Exception("CSVバリデーションエラー: " . implode(', ', $errs));
    }

    $stats = $csv->getStatistics();
    echo "[INFO] 読み込み行数  : " . ($stats['total_rows'] ?? count($csv->getData())) . " 件\n";

    // DB 接続
    $database = new Database(
        DB_HOST, DB_NAME, DB_USER, DB_PASS,
        DB_CHARSET, 'mysql',
        defined('DB_PORT') ? DB_PORT : null
    );

    // テーブル初期化
    $initializer = new DatabaseInitializer($database);
    $initResult  = $initializer->initializeAllTables();
    if (!empty($initResult['tables_created'])) {
        echo "[INFO] テーブルを作成しました: " . implode(', ', $initResult['tables_created']) . "\n";
    }
    if (!empty($initResult['errors'])) {
        throw new Exception("DB初期化エラー: " . implode(', ', $initResult['errors']));
    }

    // CSV データを DB へ登録
    $manager = new DeviceManager($database);
    $results = $manager->processCsvData($csv);

    if (!$results['success']) {
        throw new Exception("DB登録エラー: " . implode(', ', $results['errors']));
    }

    // サービス-装置種別 リレーション自動登録
    $relationCount      = 0;
    $processedRelations = [];
    foreach ($csv->getData() as $row) {
        $key = $row['サービス名'] . '|' . $row['装置種別'];
        if (!in_array($key, $processedRelations)) {
            try {
                $desc = "CLI自動登録: " . date('Y-m-d H:i:s') . " - " . basename($csvFilePath);
                $manager->registerServiceDeviceTypeRelation($row['サービス名'], $row['装置種別'], $desc);
                $relationCount++;
            } catch (Exception $e) {
                echo "[WARN] リレーション登録: {$key} -> " . $e->getMessage() . "\n";
            }
            $processedRelations[] = $key;
        }
    }

    // 操作ログ記録
    try {
        $logger    = new ActivityLogger($database);
        $logDetail = sprintf(
            'ファイル: %s, レコード数: %d, サービス数: %d, 装置種別数: %d',
            basename($csvFilePath),
            $results['device_info_count'],
            count($stats['services']),
            count($stats['device_types'])
        );
        $logger->log($cliUser, ActivityLogger::ACTION_UPLOAD, $logDetail, 'cli');
    } catch (Exception $e) {
        echo "[WARN] ログ記録に失敗しました: " . $e->getMessage() . "\n";
    }

    // 結果出力
    echo str_repeat('-', 40) . "\n";
    echo "=== 完了 ===\n";
    echo "登録レコード数          : " . $results['device_info_count'] . " 件\n";
    echo "作成された動的テーブル  : " . count($results['dynamic_tables_created']) . " 個\n";
    if (!empty($results['dynamic_tables_created'])) {
        echo "  -> " . implode(', ', $results['dynamic_tables_created']) . "\n";
    }
    echo "サービス数              : " . count($stats['services']) . " 種類\n";
    echo "装置種別数              : " . count($stats['device_types']) . " 種類\n";
    echo "自動登録リレーション    : " . $relationCount . " 件\n";

    exit(0);

} catch (Exception $e) {
    fwrite(STDERR, "\n[ERROR] " . $e->getMessage() . "\n");
    error_log("import_csv.php error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
