<?php
/**
 * device_infoテーブルのスキーマ変更マイグレーション
 * 旧: username, password, device_ip
 * 新: username1-10, password1-10, login_ip
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    echo "データベース接続中...\n";
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
    $database->connect();
    
    echo "既存のdevice_infoテーブルを確認中...\n";
    $tableExists = $database->tableExists('device_info');
    
    if (!$tableExists) {
        echo "device_infoテーブルが存在しません。新規作成します。\n";
        require_once __DIR__ . '/../classes/DeviceManager.php';
        $deviceManager = new DeviceManager($database);
        $deviceManager->createDeviceInfoTable();
        echo "✓ device_infoテーブルを作成しました。\n";
        exit(0);
    }
    
    echo "既存データを確認中...\n";
    $stmt = $database->execute("SELECT COUNT(*) as cnt FROM device_info");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = $result[0]['cnt'];
    echo "既存レコード数: {$count}\n";
    
    if ($count > 0) {
        echo "\n警告: 既存データが存在します。\n";
        echo "このマイグレーションでは、既存データを保持したままスキーマを変更します。\n";
        echo "- username → username1\n";
        echo "- password → password1\n";
        echo "- device_ip → login_ip\n";
        echo "- username2-10, password2-10 を追加（NULL）\n\n";
        
        // バックアップテーブル作成
        echo "バックアップテーブル作成中...\n";
        $timestamp = date('Ymd_His');
        $backupTable = "device_info_backup_{$timestamp}";
        $database->execute("CREATE TABLE {$backupTable} AS SELECT * FROM device_info");
        echo "✓ バックアップテーブル作成完了: {$backupTable}\n";
    }
    
    echo "\nスキーマ変更開始...\n";
    
    // 1. 新しいカラムを追加
    echo "1. 新しいカラムを追加中...\n";
    $alterQueries = [
        "ALTER TABLE device_info ADD COLUMN login_ip VARCHAR(45) AFTER device_name",
        "ALTER TABLE device_info ADD COLUMN username1 VARCHAR(100) AFTER login_ip",
        "ALTER TABLE device_info ADD COLUMN password1 VARCHAR(255) AFTER username1"
    ];
    
    // username2-10, password2-10 を追加
    for ($i = 2; $i <= 10; $i++) {
        $alterQueries[] = "ALTER TABLE device_info ADD COLUMN username{$i} VARCHAR(100) AFTER password" . ($i - 1);
        $alterQueries[] = "ALTER TABLE device_info ADD COLUMN password{$i} VARCHAR(255) AFTER username{$i}";
    }
    
    foreach ($alterQueries as $query) {
        try {
            $database->execute($query);
        } catch (Exception $e) {
            // カラムが既に存在する場合はスキップ
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
        }
    }
    echo "✓ 新しいカラムを追加しました。\n";
    
    if ($count > 0) {
        // 2. データを移行
        echo "2. 既存データを新カラムに移行中...\n";
        $database->execute("UPDATE device_info SET username1 = username WHERE username1 IS NULL");
        $database->execute("UPDATE device_info SET password1 = password WHERE password1 IS NULL");
        $database->execute("UPDATE device_info SET login_ip = device_ip WHERE login_ip IS NULL");
        echo "✓ データ移行完了。\n";
    }
    
    // 3. 古いカラムを削除
    echo "3. 古いカラムを削除中...\n";
    try {
        $database->execute("ALTER TABLE device_info DROP COLUMN username");
    } catch (Exception $e) {
        echo "  - username カラムは既に削除されています。\n";
    }
    try {
        $database->execute("ALTER TABLE device_info DROP COLUMN password");
    } catch (Exception $e) {
        echo "  - password カラムは既に削除されています。\n";
    }
    try {
        $database->execute("ALTER TABLE device_info DROP COLUMN device_ip");
    } catch (Exception $e) {
        echo "  - device_ip カラムは既に削除されています。\n";
    }
    echo "✓ 古いカラムを削除しました。\n";
    
    // 4. username1 を NOT NULL に変更
    if ($count > 0) {
        echo "4. username1 を NOT NULL に変更中...\n";
        $database->execute("ALTER TABLE device_info MODIFY username1 VARCHAR(100) NOT NULL COMMENT 'ユーザー名1'");
        echo "✓ username1 を NOT NULL に変更しました。\n";
    }
    
    // 5. インデックスを更新
    echo "5. インデックスを更新中...\n";
    try {
        $database->execute("DROP INDEX idx_device_info ON device_info");
    } catch (Exception $e) {
        echo "  - idx_device_info は既に削除されています。\n";
    }
    $database->execute("CREATE INDEX idx_device_info ON device_info (service_name, device_type, device_name, username1)");
    echo "✓ インデックスを更新しました。\n";
    
    echo "\n✅ マイグレーション完了！\n";
    
    if ($count > 0) {
        echo "\nバックアップテーブル: {$backupTable}\n";
        echo "問題がなければ、以下のコマンドでバックアップを削除できます：\n";
        echo "DROP TABLE {$backupTable};\n";
    }
    
    // 更新後のスキーマを表示
    echo "\n現在のdevice_infoテーブル構造:\n";
    $stmt = $database->execute("SHOW COLUMNS FROM device_info");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>
