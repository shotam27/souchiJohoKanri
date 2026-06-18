<?php
require_once __DIR__ . '/../config.php';

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, 'mysql', defined('DB_PORT') ? DB_PORT : null);
    $conn = $database->connect();

    // command_groups テーブル
    $conn->exec("
        CREATE TABLE IF NOT EXISTS command_groups (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            group_name  VARCHAR(200) NOT NULL COMMENT 'コマンド群名',
            device_type VARCHAR(100) NOT NULL COMMENT '対象装置種別',
            description TEXT                  COMMENT '説明',
            protocol    VARCHAR(20)  NOT NULL DEFAULT 'ssh' COMMENT '接続プロトコル(ssh/telnet)',
            port        SMALLINT UNSIGNED NOT NULL DEFAULT 22 COMMENT '接続ポート番号',
            created_by  VARCHAR(100),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_device_type (device_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ command_groups テーブル作成完了\n";

    // 既存テーブルに protocol / port カラムがなければ追加
    $cols = array_column($database->getTableColumns('command_groups'), 'COLUMN_NAME');
    if (!in_array('protocol', $cols, true)) {
        $conn->exec("ALTER TABLE command_groups ADD COLUMN protocol VARCHAR(20) NOT NULL DEFAULT 'ssh' COMMENT '接続プロトコル(ssh/telnet)' AFTER description");
        echo "✓ command_groups.protocol カラム追加\n";
    }
    if (!in_array('port', $cols, true)) {
        $conn->exec("ALTER TABLE command_groups ADD COLUMN port SMALLINT UNSIGNED NOT NULL DEFAULT 22 COMMENT '接続ポート番号' AFTER protocol");
        echo "✓ command_groups.port カラム追加\n";
    }

    // command_group_items テーブル
    $conn->exec("
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
