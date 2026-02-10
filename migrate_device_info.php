<?php
/**
 * device_info テーブルを旧スキーマから新スキーマへ移行
 */
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

// 管理者権限チェック（必要に応じて）
// if (!isAdmin()) {
//     die('管理者権限が必要です');
// }

$dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
$charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>device_info マイグレーション</title>
    <style>
        body { font-family: sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .log { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 3px solid #333; }
    </style>
</head>
<body>
    <h1>device_info テーブルマイグレーション</h1>
    
<?php
try {
    if (!$database->tableExists('device_info')) {
        echo "<p class='warning'>device_info テーブルが存在しません。マイグレーション不要です。</p>";
        exit;
    }
    
    echo "<div class='log'>";
    echo "<h3>ステップ1: 現在のスキーマ確認</h3>";
    
    $columns = $database->getTableColumns('device_info');
    $existingColumnNames = array_column($columns, 'COLUMN_NAME');
    
    echo "<p>既存カラム数: " . count($existingColumnNames) . "</p>";
    
    // 旧カラムの存在確認
    $hasOldColumns = in_array('device_ip', $existingColumnNames) || in_array('username', $existingColumnNames);
    $hasNewColumns = in_array('login_ip', $existingColumnNames) && in_array('username1', $existingColumnNames);
    
    if (!$hasOldColumns && $hasNewColumns) {
        echo "<p class='success'>✓ 既に新スキーマです。マイグレーション不要です。</p>";
        echo "</div>";
        exit;
    }
    
    if (!$hasOldColumns) {
        echo "<p class='error'>✗ 旧スキーマではありません。手動でスキーマを確認してください。</p>";
        echo "</div>";
        exit;
    }
    
    echo "<p class='warning'>⚠ 旧スキーマを検出しました。マイグレーションを実行します...</p>";
    echo "</div>";
    
    // データ件数確認
    $stmt = $database->execute("SELECT COUNT(*) as count FROM device_info");
    $result = $stmt->fetch();
    $dataCount = $result['count'];
    
    echo "<div class='log'>";
    echo "<h3>ステップ2: 既存データ確認</h3>";
    echo "<p>既存データ件数: {$dataCount} 件</p>";
    echo "</div>";
    
    // バックアップテーブル作成
    echo "<div class='log'>";
    echo "<h3>ステップ3: バックアップテーブル作成</h3>";
    
    $backupTableName = 'device_info_backup_' . date('Ymd_His');
    
    if ($dbType === 'pgsql') {
        $database->execute("CREATE TABLE \"{$backupTableName}\" AS SELECT * FROM device_info");
    } else {
        $database->execute("CREATE TABLE `{$backupTableName}` LIKE device_info");
        $database->execute("INSERT INTO `{$backupTableName}` SELECT * FROM device_info");
    }
    
    echo "<p class='success'>✓ バックアップテーブル作成: {$backupTableName}</p>";
    echo "</div>";
    
    // トランザクション開始
    $database->beginTransaction();
    
    try {
        echo "<div class='log'>";
        echo "<h3>ステップ4: カラム名変更</h3>";
        
        // device_ip → login_ip
        if (in_array('device_ip', $existingColumnNames)) {
            if ($dbType === 'pgsql') {
                $database->execute("ALTER TABLE device_info RENAME COLUMN device_ip TO login_ip");
            } else {
                $database->execute("ALTER TABLE device_info CHANGE device_ip login_ip VARCHAR(45)");
            }
            echo "<p class='success'>✓ device_ip → login_ip</p>";
        }
        
        // username → username1
        if (in_array('username', $existingColumnNames)) {
            if ($dbType === 'pgsql') {
                $database->execute("ALTER TABLE device_info RENAME COLUMN username TO username1");
            } else {
                $database->execute("ALTER TABLE device_info CHANGE username username1 VARCHAR(100) NOT NULL");
            }
            echo "<p class='success'>✓ username → username1</p>";
        }
        
        // password → password1
        if (in_array('password', $existingColumnNames)) {
            if ($dbType === 'pgsql') {
                $database->execute("ALTER TABLE device_info RENAME COLUMN password TO password1");
            } else {
                $database->execute("ALTER TABLE device_info CHANGE password password1 VARCHAR(255)");
            }
            echo "<p class='success'>✓ password → password1</p>";
        }
        
        echo "</div>";
        
        // 追加カラムの作成
        echo "<div class='log'>";
        echo "<h3>ステップ5: 追加カラム作成</h3>";
        
        $additionalColumns = [];
        for ($i = 2; $i <= 10; $i++) {
            if (!in_array("username{$i}", $existingColumnNames)) {
                $additionalColumns[] = "username{$i}";
            }
            if (!in_array("password{$i}", $existingColumnNames)) {
                $additionalColumns[] = "password{$i}";
            }
        }
        
        foreach ($additionalColumns as $col) {
            if ($dbType === 'pgsql') {
                $database->execute("ALTER TABLE device_info ADD COLUMN \"{$col}\" VARCHAR(255)");
            } else {
                $database->execute("ALTER TABLE device_info ADD COLUMN `{$col}` VARCHAR(255)");
            }
            echo "<p class='success'>✓ カラム追加: {$col}</p>";
        }
        
        if (empty($additionalColumns)) {
            echo "<p>追加カラムなし</p>";
        }
        
        echo "</div>";
        
        // インデックスの再作成
        echo "<div class='log'>";
        echo "<h3>ステップ6: インデックス再作成</h3>";
        
        try {
            if ($dbType === 'pgsql') {
                $database->execute("DROP INDEX IF EXISTS idx_device_info");
                $database->execute("CREATE INDEX idx_device_info ON device_info (service_name, device_type, device_name, username1)");
            } else {
                $database->execute("DROP INDEX idx_device_info ON device_info");
                $database->execute("CREATE INDEX idx_device_info ON device_info (service_name, device_type, device_name, username1)");
            }
            echo "<p class='success'>✓ インデックス再作成完了</p>";
        } catch (Exception $e) {
            echo "<p class='warning'>⚠ インデックス再作成: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
        
        // コミット
        $database->commit();
        
        echo "<div class='log'>";
        echo "<h2 class='success'>✓ マイグレーション完了</h2>";
        echo "<p>全ての変更が正常に完了しました。</p>";
        echo "<p>バックアップ: {$backupTableName}</p>";
        echo "<p><a href='check_db_schema.php'>→ スキーマ確認</a></p>";
        echo "<p><a href='index.php'>→ トップページへ戻る</a></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        $database->rollBack();
        echo "<p class='error'>✗ エラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>変更はロールバックされました。</p>";
        echo "<p>バックアップテーブル {$backupTableName} から手動で復元できます。</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ 致命的エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    $database->close();
}
?>

</body>
</html>
