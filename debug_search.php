<?php
/**
 * 検索機能デバッグツール
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
    <title>検索デバッグツール</title>
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
        .code { background: #f4f4f4; padding: 10px; border-left: 3px solid #3498db; margin: 10px 0; overflow-x: auto; }
        pre { margin: 0; }
        .count { font-size: 24px; font-weight: bold; color: #3498db; }
    </style>
</head>
<body>
    <h1>🔍 検索機能デバッグツール</h1>

<?php
try {
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    
    // ========== 1. データベース接続確認 ==========
    echo "<div class='section'>";
    echo "<h2>1️⃣ データベース接続</h2>";
    echo "<p class='success'>✓ 接続成功</p>";
    echo "<p>データベースタイプ: <strong>{$dbType}</strong></p>";
    echo "</div>";
    
    // ========== 2. テーブル存在確認 ==========
    echo "<div class='section'>";
    echo "<h2>2️⃣ テーブル存在確認</h2>";
    
    $tables = ['device_info', 'service_device_type_relations'];
    foreach ($tables as $table) {
        $exists = $database->tableExists($table);
        $status = $exists ? "<span class='success'>✓ 存在</span>" : "<span class='error'>✗ 不存在</span>";
        echo "<p>{$table}: {$status}</p>";
    }
    echo "</div>";
    
    // ========== 3. データ件数確認 ==========
    echo "<div class='section'>";
    echo "<h2>3️⃣ データ件数確認</h2>";
    
    if ($database->tableExists('device_info')) {
        $stmt = $database->execute("SELECT COUNT(*) as count FROM device_info");
        $result = $stmt->fetch();
        $count = $result['count'];
        
        echo "<p>device_info テーブルのレコード数: <span class='count'>{$count}</span> 件</p>";
        
        if ($count == 0) {
            echo "<p class='warning'>⚠ データが登録されていません。CSVをアップロードしてください。</p>";
        }
    } else {
        echo "<p class='error'>✗ device_info テーブルが存在しません</p>";
    }
    
    if ($database->tableExists('service_device_type_relations')) {
        $stmt = $database->execute("SELECT COUNT(*) as count FROM service_device_type_relations WHERE is_active = 1");
        $result = $stmt->fetch();
        $relationCount = $result['count'];
        
        echo "<p>service_device_type_relations の有効なレコード数: <span class='count'>{$relationCount}</span> 件</p>";
        
        if ($relationCount == 0) {
            echo "<p class='warning'>⚠ リレーションデータが登録されていません。</p>";
        }
    }
    echo "</div>";
    
    // ========== 4. サンプルデータ表示 ==========
    if (isset($count) && $count > 0) {
        echo "<div class='section'>";
        echo "<h2>4️⃣ サンプルデータ（最新5件）</h2>";
        
        $stmt = $database->execute("SELECT * FROM device_info ORDER BY created_at DESC LIMIT 5");
        $samples = $stmt->fetchAll();
        
        if (!empty($samples)) {
            echo "<table>";
            echo "<tr>";
            echo "<th>サービス名</th>";
            echo "<th>装置種別</th>";
            echo "<th>装置名称</th>";
            echo "<th>ログインIP</th>";
            echo "<th>ユーザ名1</th>";
            echo "<th>登録日時</th>";
            echo "</tr>";
            
            foreach ($samples as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['login_ip'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($row['username1']) . "</td>";
                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        echo "</div>";
    }
    
    // ========== 5. サービス名一覧 ==========
    echo "<div class='section'>";
    echo "<h2>5️⃣ サービス名一覧（リレーション）</h2>";
    
    try {
        $services = $deviceManager->getServiceNamesFromRelation();
        
        echo "<p>取得されたサービス名数: <span class='count'>" . count($services) . "</span> 件</p>";
        
        if (!empty($services)) {
            echo "<ul>";
            foreach ($services as $service) {
                echo "<li>" . htmlspecialchars($service) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='warning'>⚠ サービス名が取得できません</p>";
            
            // device_infoから直接取得を試す
            if ($database->tableExists('device_info')) {
                $stmt = $database->execute("SELECT DISTINCT service_name FROM device_info ORDER BY service_name");
                $directServices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($directServices)) {
                    echo "<p class='warning'>⚠ device_infoには以下のサービス名があります：</p>";
                    echo "<ul>";
                    foreach ($directServices as $service) {
                        echo "<li>" . htmlspecialchars($service) . "</li>";
                    }
                    echo "</ul>";
                    echo "<p><strong>原因:</strong> service_device_type_relations テーブルにデータが登録されていません。</p>";
                    echo "<p><a href='rebuild_relations.php' style='background:#3498db;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>リレーションを再構築</a></p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ========== 6. 装置種別一覧 ==========
    if (!empty($services)) {
        echo "<div class='section'>";
        echo "<h2>6️⃣ 装置種別一覧（最初のサービス）</h2>";
        
        try {
            $firstService = $services[0];
            $deviceTypes = $deviceManager->getDeviceTypesFromRelation($firstService);
            
            echo "<p>サービス名: <strong>" . htmlspecialchars($firstService) . "</strong></p>";
            echo "<p>装置種別数: <span class='count'>" . count($deviceTypes) . "</span> 件</p>";
            
            if (!empty($deviceTypes)) {
                echo "<ul>";
                foreach ($deviceTypes as $type) {
                    echo "<li>" . htmlspecialchars($type) . "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='warning'>⚠ 装置種別が取得できません</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        echo "</div>";
    }
    
    // ========== 7. 検索APIテスト ==========
    echo "<div class='section'>";
    echo "<h2>7️⃣ 検索APIテスト</h2>";
    
    // テスト1: 全件検索
    try {
        echo "<h3>テスト1: 全件検索（条件なし）</h3>";
        $devices = $deviceManager->searchDevicesAdvanced(null, null, null, 10, 0);
        $total = $deviceManager->countDevicesAdvanced(null, null, null);
        
        echo "<p>検索結果: <span class='count'>{$total}</span> 件（表示: " . count($devices) . " 件）</p>";
        
        if ($total > 0 && count($devices) > 0) {
            echo "<p class='success'>✓ 全件検索は正常に動作しています</p>";
        } elseif ($total > 0 && count($devices) == 0) {
            echo "<p class='error'>✗ データは存在するが取得できません（クエリに問題がある可能性）</p>";
        } else {
            echo "<p class='warning'>⚠ データが登録されていません</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<div class='code'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
    }
    
    // テスト2: サービス名で絞り込み
    if (!empty($services)) {
        try {
            echo "<h3>テスト2: サービス名で絞り込み（" . htmlspecialchars($services[0]) . "）</h3>";
            $devices = $deviceManager->searchDevicesAdvanced($services[0], null, null, 10, 0);
            $total = $deviceManager->countDevicesAdvanced($services[0], null, null);
            
            echo "<p>検索結果: <span class='count'>{$total}</span> 件</p>";
            
            if ($total > 0) {
                echo "<p class='success'>✓ サービス名での絞り込みは正常に動作しています</p>";
            } else {
                echo "<p class='warning'>⚠ 該当データなし</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "</div>";
    
    // ========== 8. Ajax APIテスト ==========
    echo "<div class='section'>";
    echo "<h2>8️⃣ Ajax APIテスト</h2>";
    
    echo "<p>以下のリンクで直接APIをテストできます：</p>";
    echo "<ul>";
    echo "<li><a href='ajax_api.php?action=get_services' target='_blank'>サービス名取得API</a></li>";
    if (!empty($services)) {
        echo "<li><a href='ajax_api.php?action=get_device_types&service_name=" . urlencode($services[0]) . "' target='_blank'>装置種別取得API（" . htmlspecialchars($services[0]) . "）</a></li>";
        echo "<li><a href='ajax_api.php?action=search_devices&service_name=" . urlencode($services[0]) . "&page=1' target='_blank'>検索API（" . htmlspecialchars($services[0]) . "）</a></li>";
    }
    echo "<li><a href='ajax_api.php?action=search_devices&page=1' target='_blank'>検索API（全件）</a></li>";
    echo "</ul>";
    echo "</div>";
    
    // ========== 診断結果 ==========
    echo "<div class='section' style='background: #e8f4f8;'>";
    echo "<h2>🎯 診断結果</h2>";
    
    $issues = [];
    
    if (!isset($count) || $count == 0) {
        $issues[] = "device_infoテーブルにデータが登録されていません → CSVをアップロードしてください";
    }
    
    if (!isset($relationCount) || $relationCount == 0) {
        $issues[] = "リレーションデータが登録されていません → <a href='rebuild_relations.php'>リレーション再構築</a>を実行してください";
    }
    
    if (empty($services) && isset($count) && $count > 0) {
        $issues[] = "データは存在するがリレーションが未登録です → <a href='rebuild_relations.php'>リレーション再構築</a>を実行してください";
    }
    
    if (empty($issues)) {
        echo "<p class='success' style='font-size: 18px;'>✓ 問題は検出されませんでした。</p>";
        echo "<p>それでも検索できない場合は、ブラウザの開発者ツール（F12）でコンソールエラーを確認してください。</p>";
    } else {
        echo "<p style='font-size: 18px; color: #e74c3c;'>⚠ 以下の問題が見つかりました：</p>";
        echo "<ol>";
        foreach ($issues as $issue) {
            echo "<li>{$issue}</li>";
        }
        echo "</ol>";
    }
    
    echo "</div>";
    
    $database->close();
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ エラー</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<div class='code'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
    echo "</div>";
}
?>

<div class="section">
    <h2>📚 関連リンク</h2>
    <ul>
        <li><a href="index.php">トップページ</a></li>
        <li><a href="search.php">検索ページ</a></li>
        <li><a href="manage.php">管理ページ</a></li>
        <li><a href="check_db_schema.php">スキーマ確認</a></li>
        <li><a href="rebuild_relations.php">リレーション再構築</a></li>
    </ul>
</div>

</body>
</html>
