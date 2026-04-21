<?php
require_once __DIR__ . '/../config.php';

try {
    $dbType  = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $conn = $database->connect();

    // command_groups テーブル
    $conn->executeStatement("
        CREATE TABLE IF NOT EXISTS command_groups (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            group_name  VARCHAR(200) NOT NULL COMMENT 'コマンド群名',
            device_type VARCHAR(100) NOT NULL COMMENT '対象装置種別',
            description TEXT                  COMMENT '説明',
            created_by  VARCHAR(100),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_device_type (device_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ command_groups テーブル作成完了\n";

    // command_group_items テーブル
    $conn->executeStatement("
        CREATE TABLE IF NOT EXISTS command_group_items (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            command_group_id INT NOT NULL,
            sort_order      INT NOT NULL DEFAULT 0,
            prompt          VARCHAR(100) NOT NULL COMMENT 'プロンプト例: # (admin)#',
            command         TEXT         NOT NULL COMMENT 'コマンド',
            FOREIGN KEY (command_group_id) REFERENCES command_groups(id) ON DELETE CASCADE,
            INDEX idx_group_order (command_group_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ command_group_items テーブル作成完了\n";

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n初期化完了！\n";
