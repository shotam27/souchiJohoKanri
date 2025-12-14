<?php
/**
 * 共通ヘッダー - ナビゲーションメニュー付き
 */

// 現在のページを判定
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '装置情報管理システム' ?></title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- メインナビゲーションバー -->
    <nav class="main-navbar">
        <div class="navbar-container">
            <!-- ブランドロゴ -->
            <a href="index.php" class="navbar-brand">
                <?php include 'svgs/brand.svg'; ?>
                装置情報管理システム
            </a>
            
            <!-- モバイルメニュートグル -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                </svg>
            </button>
            
            <!-- ナビゲーションメニュー -->
            <ul class="navbar-nav" id="navbarNav">
                <li class="nav-item">
                    <a href="search.php" class="nav-link <?= $currentPage === 'search' ? 'active' : '' ?>">
                        <div class="nav-icon">
                            <?php include 'svgs/search.svg'; ?>
                        </div>
                        装置検索
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                        <div class="nav-icon">
                            <?php include 'svgs/upload.svg'; ?>
                        </div>
                        CSVアップロード
                    </a>
                </li>
                <li class="nav-item">
                    <a href="download.php" class="nav-link <?= $currentPage === 'download' ? 'active' : '' ?>">
                        <div class="nav-icon">
                            <?php include 'svgs/download.svg'; ?>
                        </div>
                        CSVダウンロード
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <script>
        // モバイルメニュートグル
        function toggleMobileMenu() {
            const nav = document.getElementById('navbarNav');
            nav.classList.toggle('show');
        }
        
        // ウィンドウサイズ変更時にモバイルメニューを閉じる
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const nav = document.getElementById('navbarNav');
                nav.classList.remove('show');
            }
        });
        
        // 外部クリック時にモバイルメニューを閉じる
        document.addEventListener('click', function(event) {
            const nav = document.getElementById('navbarNav');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                nav.classList.remove('show');
            }
        });
    </script>