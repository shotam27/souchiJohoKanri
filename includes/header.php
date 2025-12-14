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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
        }
        
        /* メインナビゲーションバー */
        .main-navbar {
            background: #004eb1;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: 70px;
        }
        
        .navbar-brand {
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand:hover {
            color: #e8f2ff;
        }
        
        .brand-icon {
            width: 32px;
            height: 32px;
        }
        
        .navbar-brand svg {
            width: 32px;
            height: 32px;
            fill: currentColor;
        }
        
        .navbar-nav {
            display: flex;
            list-style: none;
            gap: 5px;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            margin: 0 2px;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            color: #e8f2ff;
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.25);
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
            color: #ffffff;
        }
        
        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-icon svg {
            width: 100%;
            height: 100%;
            fill: currentColor;
        }
        
        /* モバイル対応 */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        @media (max-width: 768px) {
            .navbar-container {
                flex-wrap: wrap;
                height: auto;
                padding: 15px 20px;
            }
            
            .navbar-nav {
                display: none;
                width: 100%;
                flex-direction: column;
                margin-top: 15px;
                gap: 10px;
            }
            
            .navbar-nav.show {
                display: flex;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .nav-link {
                width: 100%;
                justify-content: flex-start;
                padding: 15px 20px;
                font-size: 16px;
            }
            
            .navbar-brand {
                font-size: 20px;
            }
        }
        
        /* メインコンテンツエリア */
        .main-content {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        .page-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .page-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-title-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-title-icon svg {
            width: 100%;
            height: 100%;
            fill: #667eea;
        }
        
        /* アラートスタイル */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #dc2626;
            border-left-color: #dc2626;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            color: #16a34a;
            border-left-color: #16a34a;
        }
        
        .alert-info {
            background-color: #f0f9ff;
            color: #0284c7;
            border-left-color: #0284c7;
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
            flex-shrink: 0;
            margin-top: 2px;
        }
    </style>
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