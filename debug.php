<?php
/**
 * Render デバッグページ
 * 環境変数とデータベース接続を確認
 */

// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Render デバッグ情報</h1>";

// 環境変数の確認
echo "<h2>環境変数</h2>";
echo "<pre>";
echo "DB_HOST: " . (getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'NOT SET') . "\n";
echo "DB_PASS: " . ((getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?? null) ? '***SET***' : 'NOT SET') . "\n";
echo "DB_TYPE: " . (getenv('DB_TYPE') ?: $_ENV['DB_TYPE'] ?? 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 'NOT SET') . "\n";
echo "</pre>";

// config.phpの存在確認
echo "<h2>ファイル確認</h2>";
echo "<pre>";
echo "config.php exists: " . (file_exists('config.php') ? 'YES' : 'NO') . "\n";
echo "config.render.php exists: " . (file_exists('config.render.php') ? 'YES' : 'NO') . "\n";
echo "</pre>";

// PHP拡張機能の確認
echo "<h2>PHP拡張機能</h2>";
echo "<pre>";
echo "PDO: " . (extension_loaded('pdo') ? 'YES' : 'NO') . "\n";
echo "PDO_MySQL: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
echo "PDO_PostgreSQL: " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "\n";
echo "MySQLi: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "</pre>";

// データベース接続テスト
echo "<h2>データベース接続テスト</h2>";
echo "<pre>";

if (file_exists('config.php')) {
    require_once 'config.php';
    
    try {
        $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
        
        echo "接続情報:\n";
        echo "  DB_TYPE: mysql\n";
        echo "  DB_HOST: " . DB_HOST . "\n";
        echo "  DB_PORT: {$dbPort}\n";
        echo "  DB_NAME: " . DB_NAME . "\n";
        echo "  DB_USER: " . DB_USER . "\n\n";
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        echo "✅ データベース接続成功!\n";
        
        // テーブル一覧を取得
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "\nテーブル数: " . count($tables) . "\n";
        if (count($tables) > 0) {
            echo "テーブル一覧:\n";
            foreach ($tables as $table) {
                echo "  - $table\n";
            }
        } else {
            echo "⚠️ テーブルが存在しません。データベース初期化が必要です。\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ データベース接続エラー:\n";
        echo $e->getMessage() . "\n";
    }
} else {
    echo "❌ config.php が見つかりません\n";
}

echo "</pre>";

// ディレクトリの権限確認
echo "<h2>ディレクトリ権限</h2>";
echo "<pre>";
$dirs = ['uploads', 'logs', 'classes', 'includes'];
foreach ($dirs as $dir) {
    if (file_exists($dir)) {
        echo "$dir: " . (is_writable($dir) ? '✅ 書き込み可' : '❌ 書き込み不可') . "\n";
    } else {
        echo "$dir: ❌ 存在しません\n";
    }
}
echo "</pre>";

echo "<hr>";
echo "<p><a href='/'>トップページに戻る</a></p>";
?>
