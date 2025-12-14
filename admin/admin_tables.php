<?php
require_once '../config.php';

// 管理者用：テーブル管理ページ
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';
$error = '';

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
    $deviceManager = new DeviceManager($database);
    
    // テーブル削除処理
    if ($_POST['action'] ?? '' === 'delete_dynamic_tables') {
        $tablesDeleted = 0;
        
        // 全テーブルを取得
        $sql = "SHOW TABLES";
        $stmt = $database->execute($sql);
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);
        
        foreach ($tables as $table) {
            $tableName = $table[0];
            // システムテーブル以外を削除
            if (!in_array($tableName, ['device_info', 'service_device_type_relations'])) {
                try {
                    $deleteSql = "DROP TABLE `{$tableName}`";
                    $database->execute($deleteSql);
                    $tablesDeleted++;
                } catch (Exception $e) {
                    error_log("テーブル削除エラー ({$tableName}): " . $e->getMessage());
                }
            }
        }
        
        $message = "{$tablesDeleted}個の動的テーブルを削除しました。";
    }
    
    // 全テーブルを取得して表示
    $sql = "SHOW TABLES";
    $stmt = $database->execute($sql);
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    
} catch (Exception $e) {
    $error = "エラー: " . $e->getMessage();
    $tables = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>テーブル管理 - 装置情報管理システム</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #333;
            margin: 0;
        }
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        .nav-buttons a {
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .nav-buttons a:hover {
            background-color: #5a6268;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table tr:hover {
            background-color: #f8f9fa;
        }
        .system-table {
            color: #28a745;
            font-weight: bold;
        }
        .dynamic-table {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ テーブル管理</h1>
            <div class="nav-buttons">
                <a href="index.php">📤 CSVアップロード</a>
                <a href="search.php">🔍 装置検索</a>
                <a href="relations.php">🔗 リレーション管理</a>
            </div>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>エラー:</strong> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <strong>成功:</strong> <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="alert alert-warning">
            <h4>⚠️ 注意</h4>
            <p>
                このページは開発・テスト用です。動的テーブルを削除すると、CSVでアップロードしたデータが失われます。
                本番環境では使用しないでください。
            </p>
        </div>
        
        <h3>現在のテーブル一覧</h3>
        
        <table class="table">
            <thead>
                <tr>
                    <th>テーブル名</th>
                    <th>タイプ</th>
                    <th>説明</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table): ?>
                <?php 
                    $tableName = $table[0];
                    $isSystemTable = in_array($tableName, ['device_info', 'service_device_type_relations']);
                ?>
                <tr>
                    <td class="<?= $isSystemTable ? 'system-table' : 'dynamic-table' ?>">
                        <?= htmlspecialchars($tableName) ?>
                    </td>
                    <td>
                        <?= $isSystemTable ? 'システムテーブル' : '動的テーブル' ?>
                    </td>
                    <td>
                        <?php if ($tableName === 'device_info'): ?>
                            装置情報の基本データ
                        <?php elseif ($tableName === 'service_device_type_relations'): ?>
                            サービス・装置種別リレーション
                        <?php else: ?>
                            CSVアップロードで作成された拡張データテーブル
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3>管理操作</h3>
        
        <form method="post" style="margin-top: 20px;" onsubmit="return confirm('全ての動的テーブルを削除しますか？この操作は取り消せません。');">
            <input type="hidden" name="action" value="delete_dynamic_tables">
            <button type="submit" class="btn btn-danger">
                🗑️ 全ての動的テーブルを削除
            </button>
        </form>
        
        <p style="margin-top: 20px; color: #6c757d; font-size: 14px;">
            動的テーブルを削除すると、次回のCSVアップロード時に新しい構造でテーブルが再作成されます。
        </p>
    </div>
</body>
</html>