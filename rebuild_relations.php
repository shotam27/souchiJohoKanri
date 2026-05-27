<?php
/**
 * リレーションデータ再構築ツール
 */
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
requireLogin();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リレーション再構築</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .count { font-size: 24px; font-weight: bold; color: #3498db; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <h1>🔄 リレーションデータ再構築</h1>

<?php
try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, 'mysql', defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    
    // POSTリクエストで実行
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rebuild') {
        
        echo "<div class='section'>";
        echo "<h2>実行中...</h2>";
        
        try {
            // リレーションテーブルの存在確認
            if (!$deviceManager->relationTableExists()) {
                echo "<p class='warning'>⚠ リレーションテーブルが存在しないため作成します...</p>";
                $deviceManager->createRelationTable();
                echo "<p class='success'>✓ リレーションテーブルを作成しました</p>";
            }
            
            // 既存データからリレーションを構築
            $result = $deviceManager->buildRelationsFromExistingData();
            
            echo "<h3>実行結果：</h3>";
            echo "<p>登録された組み合わせ数: <span class='count'>{$result['registered']}</span> / {$result['total_combinations']}</p>";
            
            if ($result['registered'] > 0) {
                echo "<p class='success'>✓ リレーションデータの再構築が完了しました</p>";
            } else {
                echo "<p class='warning'>⚠ 登録されたデータがありません</p>";
            }
            
            if (!empty($result['errors'])) {
                echo "<h3>エラー：</h3>";
                echo "<ul>";
                foreach ($result['errors'] as $error) {
                    echo "<li class='error'>" . htmlspecialchars($error) . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ エラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "<p><a href='rebuild_relations.php' class='btn'>再読み込み</a></p>";
        echo "<p><a href='debug_search.php' class='btn'>デバッグツールで確認</a></p>";
        echo "<p><a href='search.php' class='btn'>検索ページへ</a></p>";
        echo "</div>";
        
    } else {
        // GETリクエスト：現在の状態を表示
        
        echo "<div class='section'>";
        echo "<h2>現在の状態</h2>";
        
        // device_infoのデータ確認
        if ($database->tableExists('device_info')) {
            $stmt = $database->execute("SELECT COUNT(*) as count FROM device_info");
            $result = $stmt->fetch();
            $deviceCount = $result['count'];
            
            echo "<p>device_info レコード数: <span class='count'>{$deviceCount}</span> 件</p>";
            
            if ($deviceCount == 0) {
                echo "<p class='warning'>⚠ device_infoにデータがありません。先にCSVをアップロードしてください。</p>";
                echo "<p><a href='index.php' class='btn'>トップページへ</a></p>";
                echo "</div></body></html>";
                exit;
            }
            
            // サービス名・装置種別の組み合わせを表示
            $stmt = $database->execute("
                SELECT 
                    service_name, 
                    device_type, 
                    COUNT(*) as device_count
                FROM device_info 
                GROUP BY service_name, device_type
                ORDER BY service_name, device_type
            ");
            $combinations = $stmt->fetchAll();
            
            echo "<h3>登録されているサービス名・装置種別の組み合わせ：</h3>";
            echo "<table>";
            echo "<tr><th>サービス名</th><th>装置種別</th><th>装置数</th></tr>";
            foreach ($combinations as $combo) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($combo['service_name']) . "</td>";
                echo "<td>" . htmlspecialchars($combo['device_type']) . "</td>";
                echo "<td>" . htmlspecialchars($combo['device_count']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } else {
            echo "<p class='error'>✗ device_infoテーブルが存在しません</p>";
            echo "</div></body></html>";
            exit;
        }
        
        echo "</div>";
        
        // リレーションテーブルの状態
        echo "<div class='section'>";
        echo "<h2>リレーションテーブルの状態</h2>";
        
        if ($deviceManager->relationTableExists()) {
            $stmt = $database->execute("SELECT COUNT(*) as count FROM service_device_type_relations WHERE is_active = 1");
            $result = $stmt->fetch();
            $relationCount = $result['count'];
            
            echo "<p>有効なリレーション数: <span class='count'>{$relationCount}</span> 件</p>";
            
            if ($relationCount == 0) {
                echo "<p class='warning'>⚠ リレーションデータが登録されていません</p>";
            } else {
                // リレーション一覧を表示
                $stmt = $database->execute("
                    SELECT service_name, device_type, description, created_at 
                    FROM service_device_type_relations 
                    WHERE is_active = 1 
                    ORDER BY service_name, device_type
                ");
                $relations = $stmt->fetchAll();
                
                echo "<h3>登録済みリレーション：</h3>";
                echo "<table>";
                echo "<tr><th>サービス名</th><th>装置種別</th><th>説明</th><th>登録日時</th></tr>";
                foreach ($relations as $rel) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($rel['service_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($rel['device_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($rel['description'] ?? '-') . "</td>";
                    echo "<td>" . htmlspecialchars($rel['created_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p class='error'>✗ リレーションテーブルが存在しません</p>";
        }
        
        echo "</div>";
        
        // 実行ボタン
        echo "<div class='section'>";
        echo "<h2>実行</h2>";
        echo "<p>既存の device_info データから service_device_type_relations を自動構築します。</p>";
        echo "<p>既に登録済みのリレーションは更新されます。</p>";
        
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='rebuild'>";
        echo "<button type='submit' class='btn' style='font-size: 18px; padding: 15px 30px;'>🔄 リレーションを再構築する</button>";
        echo "</form>";
        echo "</div>";
    }
    
    $database->close();
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ エラー</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<div class="section">
    <h2>📚 関連リンク</h2>
    <ul>
        <li><a href="debug_search.php">デバッグツール</a></li>
        <li><a href="search.php">検索ページ</a></li>
        <li><a href="manage.php">管理ページ</a></li>
        <li><a href="index.php">トップページ</a></li>
    </ul>
</div>

</body>
</html>
