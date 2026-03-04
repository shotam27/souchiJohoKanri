<?php
/**
 * audit_logs テーブル作成マイグレーション
 * 実行: docker compose exec web php admin/create_audit_logs.php
 */
require_once __DIR__ . '/../config.php';

echo "=== audit_logs テーブル マイグレーション ===\n\n";

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
    $conn = $database->connect();

    $sql = "
        CREATE TABLE IF NOT EXISTS audit_logs (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
            username    VARCHAR(100)  NOT NULL                      COMMENT '操作ユーザー名',
            action      VARCHAR(50)   NOT NULL                      COMMENT 'アクション種別',
            detail      TEXT                                        COMMENT '補足情報',
            ip_address  VARCHAR(45)                                 COMMENT 'クライアントIP',
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP     COMMENT '操作日時',
            INDEX idx_audit_username (username),
            INDEX idx_audit_action   (action),
            INDEX idx_audit_created  (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='ユーザー操作ログ'
    ";

    $conn->exec($sql);
    echo "✓ audit_logs テーブルを作成しました（既存の場合はスキップ）\n";

    // 確認
    $check = $conn->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
    echo "✓ 現在のログ件数: {$check} 件\n";

    echo "\n=== マイグレーション完了 ===\n";

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
