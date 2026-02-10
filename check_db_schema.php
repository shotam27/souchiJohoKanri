<?php
/**
 * データベーススキーマ確認ツール
 */
require_once 'config.php';

try {
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    
    echo "<h2>device_info テーブルのカラム確認</h2>\n";
    
    if (!$database->tableExists('device_info')) {
        echo "<p style='color: orange;'>device_info テーブルが存在しません。</p>\n";
        echo "<p>CSVアップロードを実行すると自動的に作成されます。</p>\n";
    } else {
        $columns = $database->getTableColumns('device_info');
        
        echo "<h3>現在のカラム一覧：</h3>\n";
        echo "<ul>\n";
        foreach ($columns as $col) {
            echo "<li>" . htmlspecialchars($col['COLUMN_NAME']) . " - " . htmlspecialchars($col['DATA_TYPE']) . "</li>\n";
        }
        echo "</ul>\n";
        
        // 必須カラムのチェック
        $requiredColumns = ['primary_key', 'service_name', 'device_type', 'device_name', 'login_ip', 'username1', 'password1'];
        $existingColumnNames = array_column($columns, 'COLUMN_NAME');
        
        echo "<h3>必須カラムのチェック：</h3>\n";
        $allOk = true;
        foreach ($requiredColumns as $required) {
            $exists = in_array($required, $existingColumnNames);
            $color = $exists ? 'green' : 'red';
            $status = $exists ? '✓ 存在' : '✗ 不足';
            echo "<p style='color: {$color};'>{$required}: {$status}</p>\n";
            if (!$exists) {
                $allOk = false;
            }
        }
        
        // 旧カラム（削除すべき）のチェック
        $oldColumns = ['device_ip', 'username', 'password'];
        echo "<h3>旧スキーマカラムのチェック：</h3>\n";
        $hasOldColumns = false;
        foreach ($oldColumns as $old) {
            if (in_array($old, $existingColumnNames)) {
                echo "<p style='color: orange;'>⚠ 旧カラムが残っています: {$old}</p>\n";
                $hasOldColumns = true;
            }
        }
        
        if (!$hasOldColumns) {
            echo "<p style='color: green;'>✓ 旧カラムは存在しません</p>\n";
        }
        
        // 総合判定
        echo "<hr>\n";
        if ($allOk && !$hasOldColumns) {
            echo "<h2 style='color: green;'>✓ スキーマは正常です</h2>\n";
            
            // データ件数確認
            $stmt = $database->execute("SELECT COUNT(*) as count FROM device_info");
            $result = $stmt->fetch();
            echo "<p>登録データ件数: " . $result['count'] . " 件</p>\n";
            
        } elseif ($hasOldColumns) {
            echo "<h2 style='color: orange;'>⚠ 旧スキーマのテーブルが存在します</h2>\n";
            echo "<p><strong>対応方法：</strong></p>\n";
            echo "<ol>\n";
            echo "<li>既存データが不要な場合: テーブルを削除して再作成</li>\n";
            echo "<li>既存データを保持する場合: マイグレーションスクリプトを実行</li>\n";
            echo "</ol>\n";
            echo "<p><a href='migrate_device_info.php'>→ 自動マイグレーションを実行</a></p>\n";
        } else {
            echo "<h2 style='color: red;'>✗ 必須カラムが不足しています</h2>\n";
            echo "<p>テーブルを削除して再作成してください。</p>\n";
        }
    }
    
    $database->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>
