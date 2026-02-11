<?php
require_once 'config.php';

try {
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    
    echo "ダミーデータ登録を開始します...\n\n";
    
    // サービス名と装置種別の組み合わせ
    $serviceName = 'テストサービス';
    $deviceType = 'ルータ';
    
    // リレーションテーブルに登録（存在しない場合）
    $checkRelationSql = "SELECT COUNT(*) FROM service_device_type_relations WHERE service_name = ? AND device_type = ?";
    $stmt = $database->execute($checkRelationSql, [$serviceName, $deviceType]);
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        $insertRelationSql = "INSERT INTO service_device_type_relations (service_name, device_type, description) VALUES (?, ?, ?)";
        $database->execute($insertRelationSql, [$serviceName, $deviceType, 'テスト用ダミーデータ']);
        echo "リレーションテーブルに登録しました: {$serviceName} - {$deviceType}\n";
    }
    
    // 動的テーブル名を生成
    $tableName = sanitizeTableName($serviceName . '_' . $deviceType);
    
    // 動的テーブルが存在しない場合は作成
    $checkTableSql = "SHOW TABLES LIKE '{$tableName}'";
    $stmt = $database->execute($checkTableSql);
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        $createTableSql = "
            CREATE TABLE `{$tableName}` (
                primary_key VARCHAR(500) NOT NULL PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($createTableSql);
        echo "動的テーブルを作成しました: {$tableName}\n";
    }
    
    // 10件のダミーデータを登録
    $dummyDevices = [
        ['device_name' => 'ルータ-001', 'login_ip' => '192.168.1.1', 'username' => 'admin', 'password' => 'pass1234'],
        ['device_name' => 'ルータ-002', 'login_ip' => '192.168.1.2', 'username' => 'admin', 'password' => 'pass5678'],
        ['device_name' => 'ルータ-003', 'login_ip' => '192.168.1.3', 'username' => 'root', 'password' => 'rootpass'],
        ['device_name' => 'ルータ-004', 'login_ip' => '192.168.1.4', 'username' => 'admin', 'password' => 'admin123'],
        ['device_name' => 'ルータ-005', 'login_ip' => '192.168.1.5', 'username' => 'operator', 'password' => 'oper456'],
        ['device_name' => 'ルータ-006', 'login_ip' => '192.168.1.6', 'username' => 'admin', 'password' => 'secure789'],
        ['device_name' => 'ルータ-007', 'login_ip' => '192.168.1.7', 'username' => 'admin', 'password' => 'test1111'],
        ['device_name' => 'ルータ-008', 'login_ip' => '192.168.1.8', 'username' => 'netadmin', 'password' => 'net2222'],
        ['device_name' => 'ルータ-009', 'login_ip' => '192.168.1.9', 'username' => 'admin', 'password' => 'pass3333'],
        ['device_name' => 'ルータ-010', 'login_ip' => '192.168.1.10', 'username' => 'admin', 'password' => 'pass4444'],
    ];
    
    $insertedCount = 0;
    $errorCount = 0;
    
    foreach ($dummyDevices as $device) {
        // primary_key を生成
        $primaryKey = "{$serviceName}_{$deviceType}_{$device['device_name']}_{$device['username']}";
        
        try {
            // device_info テーブルに挿入
            $insertDeviceSql = "
                INSERT INTO device_info (
                    primary_key, service_name, device_type, device_name, 
                    login_ip, username1, password1, 
                    created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    login_ip = VALUES(login_ip),
                    password1 = VALUES(password1),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ";
            
            $database->execute($insertDeviceSql, [
                $primaryKey,
                $serviceName,
                $deviceType,
                $device['device_name'],
                $device['login_ip'],
                $device['username'],
                $device['password'],
                'システム管理者',
                'システム管理者'
            ]);
            
            // 動的テーブルにも挿入
            $insertDynamicSql = "
                INSERT INTO `{$tableName}` (primary_key) 
                VALUES (?)
                ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
            ";
            $database->execute($insertDynamicSql, [$primaryKey]);
            
            $insertedCount++;
            echo "✓ 登録成功: {$device['device_name']} ({$device['login_ip']})\n";
            
        } catch (Exception $e) {
            $errorCount++;
            echo "✗ 登録失敗: {$device['device_name']} - {$e->getMessage()}\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ダミーデータ登録完了\n";
    echo "成功: {$insertedCount}件\n";
    echo "失敗: {$errorCount}件\n";
    echo str_repeat("=", 50) . "\n";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
