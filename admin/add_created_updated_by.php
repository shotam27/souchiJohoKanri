<?php
/**
 * device_infoテーブルにcreated_by、updated_byカラムを追加するマイグレーション
 */

require_once __DIR__ . '/../config.php';

try {
    // データベース接続
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    
    echo "データベースに接続しました。\n\n";
    
    // 既存のカラムを確認
    if ($dbType === 'pgsql') {
        $checkSql = "
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'device_info' 
            AND column_name IN ('created_by', 'updated_by')
        ";
    } else {
        $checkSql = "
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'device_info' 
            AND TABLE_SCHEMA = '" . DB_NAME . "'
            AND COLUMN_NAME IN ('created_by', 'updated_by')
        ";
    }
    
    $existingColumns = $database->query($checkSql);
    $existingColumnNames = array_column($existingColumns, $dbType === 'pgsql' ? 'column_name' : 'COLUMN_NAME');
    
    echo "既存のカラム: " . implode(', ', $existingColumnNames) . "\n\n";
    
    // created_byカラムを追加
    if (!in_array('created_by', $existingColumnNames)) {
        echo "created_byカラムを追加中...\n";
        if ($dbType === 'pgsql') {
            $sql = "ALTER TABLE device_info ADD COLUMN created_by VARCHAR(100)";
        } else {
            $sql = "ALTER TABLE device_info ADD COLUMN created_by VARCHAR(100) COMMENT '作成者' AFTER password10";
        }
        $database->execute($sql);
        echo "✓ created_byカラムを追加しました。\n\n";
    } else {
        echo "created_byカラムは既に存在します。\n\n";
    }
    
    // updated_byカラムを追加
    if (!in_array('updated_by', $existingColumnNames)) {
        echo "updated_byカラムを追加中...\n";
        if ($dbType === 'pgsql') {
            $sql = "ALTER TABLE device_info ADD COLUMN updated_by VARCHAR(100)";
        } else {
            $sql = "ALTER TABLE device_info ADD COLUMN updated_by VARCHAR(100) COMMENT '更新者' AFTER created_by";
        }
        $database->execute($sql);
        echo "✓ updated_byカラムを追加しました。\n\n";
    } else {
        echo "updated_byカラムは既に存在します。\n\n";
    }
    
    echo "マイグレーション完了！\n";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
