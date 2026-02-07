<?php
/**
 * データベース初期化スクリプト（ユーザーテーブル追加）
 */

require_once __DIR__ . '/../config.php';

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
    $conn = $database->connect();
    
    echo "データベース接続成功\n";
    
    // ユーザーテーブル作成
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ユーザーID',
        username VARCHAR(100) NOT NULL UNIQUE COMMENT 'ユーザー名',
        password_hash VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ（bcrypt）',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
        last_login TIMESTAMP NULL COMMENT '最終ログイン日時',
        is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ(1:有効, 0:無効)',
        INDEX idx_username (username),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザー認証テーブル'";
    
    $conn->exec($sql);
    echo "✓ usersテーブルを作成しました\n";
    
    echo "\n初期化完了！\n";
    echo "登録ページにアクセスしてユーザーを作成してください: register.php\n";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
