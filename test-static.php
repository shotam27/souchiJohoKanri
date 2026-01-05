<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>静的ファイルテスト</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .test-item { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>静的ファイル読み込みテスト</h1>
    
    <div class="test-item">
        <h2>1. CSS読み込みテスト</h2>
        <link rel="stylesheet" href="css/styles.css">
        <div id="cssTest">CSSが読み込まれていません</div>
        <script>
            const styles = window.getComputedStyle(document.body);
            document.getElementById('cssTest').innerHTML = 
                styles.backgroundColor ? 
                '<span class="success">✅ CSSが読み込まれています</span>' : 
                '<span class="error">❌ CSSが読み込まれていません</span>';
        </script>
    </div>
    
    <div class="test-item">
        <h2>2. SVGファイルテスト</h2>
        <p>SVGファイルの読み込み:</p>
        <?php
        $svgPath = __DIR__ . '/svgs/upload.svg';
        if (file_exists($svgPath)) {
            echo '<span class="success">✅ SVGファイルが存在します</span><br>';
            echo '<div style="width:50px;height:50px;">';
            include $svgPath;
            echo '</div>';
        } else {
            echo '<span class="error">❌ SVGファイルが見つかりません</span>';
        }
        ?>
    </div>
    
    <div class="test-item">
        <h2>3. ファイルパーミッション</h2>
        <?php
        $dirs = ['css', 'svgs', 'uploads', 'logs', 'classes', 'includes'];
        echo '<ul>';
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                echo "<li><strong>$dir/</strong>: 存在 (権限: $perms)</li>";
            } else {
                echo "<li><strong>$dir/</strong>: <span class='error'>存在しません</span></li>";
            }
        }
        
        // CSSファイルの確認
        $cssFile = __DIR__ . '/css/styles.css';
        if (file_exists($cssFile)) {
            $perms = substr(sprintf('%o', fileperms($cssFile)), -4);
            $readable = is_readable($cssFile) ? '読み取り可能' : '読み取り不可';
            echo "<li><strong>css/styles.css</strong>: 存在 (権限: $perms, $readable)</li>";
        } else {
            echo "<li><strong>css/styles.css</strong>: <span class='error'>存在しません</span></li>";
        }
        echo '</ul>';
        ?>
    </div>
    
    <div class="test-item">
        <h2>4. Apache設定確認</h2>
        <?php
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            echo '<p>mod_rewrite: ' . (in_array('mod_rewrite', $modules) ? 
                '<span class="success">有効</span>' : 
                '<span class="error">無効</span>') . '</p>';
        } else {
            echo '<p>Apache関数が利用できません</p>';
        }
        ?>
        <p>.htaccess: <?= file_exists(__DIR__ . '/.htaccess') ? 
            '<span class="success">存在します</span>' : 
            '<span class="error">存在しません</span>' ?></p>
    </div>
    
    <div class="test-item">
        <h2>5. データベース接続テスト</h2>
        <?php
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                echo '<span class="success">✅ データベース接続成功</span>';
                echo '<br>ホスト: ' . DB_HOST;
            } catch (PDOException $e) {
                echo '<span class="error">❌ データベース接続エラー: ' . htmlspecialchars($e->getMessage()) . '</span>';
                echo '<br>ホスト: ' . DB_HOST;
            }
        } else {
            echo '<span class="error">❌ config.phpが見つかりません</span>';
        }
        ?>
    </div>
    
    <hr>
    <p><a href="/">トップページに戻る</a> | <a href="debug.php">詳細デバッグページ</a></p>
</body>
</html>
