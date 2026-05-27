<?php
/**
 * 既存データのcreated_by、updated_byフィールドにデフォルト値を設定するマイグレーション
 */

require_once __DIR__ . '/../config.php';

try {
    // データベース接続
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, 'mysql', defined('DB_PORT') ? DB_PORT : null);
    
    echo "データベースに接続しました。\n\n";
    
    // created_byがNULLのレコードを確認
    $checkSql = "SELECT COUNT(*) as count FROM device_info WHERE created_by IS NULL OR updated_by IS NULL";
    $stmt = $database->execute($checkSql);
    $result = $stmt->fetch();
    $nullCount = $result['count'];
    
    echo "created_byまたはupdated_byがNULLのレコード数: {$nullCount}\n\n";
    
    if ($nullCount > 0) {
        echo "既存データにデフォルト値を設定中...\n";
        
        // created_byがNULLの場合は'system'を設定
        $updateCreatedBySql = "UPDATE device_info SET created_by = 'system' WHERE created_by IS NULL";
        $database->execute($updateCreatedBySql);
        echo "✓ created_byにデフォルト値を設定しました。\n";
        
        // updated_byがNULLの場合は'system'を設定
        $updateUpdatedBySql = "UPDATE device_info SET updated_by = 'system' WHERE updated_by IS NULL";
        $database->execute($updateUpdatedBySql);
        echo "✓ updated_byにデフォルト値を設定しました。\n\n";
        
        echo "マイグレーション完了！\n";
    } else {
        echo "すべてのレコードに値が設定されています。処理不要です。\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
