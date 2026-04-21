<?php
/**
 * 共通ヘッダー - ナビゲーションメニュー付き
 */

// 認証ヘルパー関数を読み込み
require_once __DIR__ . '/auth_helper.php';

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
            
            <div class="navbar-menu">
                <!-- ナビゲーションメニュー -->
                <ul class="navbar-nav" id="navbarNav">
                    <li class="nav-item">                        <a href="manage.php" class="nav-link <?= $currentPage === 'manage' ? 'active' : '' ?>" title="装置情報管理">
                            <div class="nav-icon">
                                <?php include 'svgs/info.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">装置情報管理</span>
                        </a>
                    </li>
                    <li class="nav-item">                        <a href="index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" title="CSVアップロード">
                            <div class="nav-icon">
                                <?php include 'svgs/upload.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">CSVアップロード</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="download.php" class="nav-link <?= $currentPage === 'download' ? 'active' : '' ?>" title="CSVダウンロード">
                            <div class="nav-icon">
                                <?php include 'svgs/download.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">CSVダウンロード</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="command_groups.php" class="nav-link <?= $currentPage === 'command_groups' ? 'active' : '' ?>" title="コマンド群登録">
                            <div class="nav-icon">
                                <?php include 'svgs/info.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">コマンド群登録</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="macro_output.php" class="nav-link <?= $currentPage === 'macro_output' ? 'active' : '' ?>" title="マクロ出力">
                            <div class="nav-icon">
                                <?php include 'svgs/rotate.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">マクロ出力</span>
                        </a>
                    </li>
                </ul>
                
                <!-- ユーザー情報 -->
                <div class="navbar-user">
                    <?php if (isLoggedIn()): ?>
                        <span class="nav-link user-name-display">
                            <div class="nav-icon">
                                <span class="user-icon">
                                    <?php include 'svgs/user.svg'; ?>
                                </span>
                            </div>
                            <span class="nav-text"><?= htmlspecialchars(getLoggedInUsername()) ?></span>
                        </span>
                        <a href="logout.php" class="nav-link logout-link" title="ログアウト">
                            <div class="nav-icon">
                                <?php include 'svgs/logout.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">ログアウト</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link login-link" title="ログイン">
                            <div class="nav-icon">
                                <?php include 'svgs/login.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">ログイン</span>
                        </a>
                        <a href="register.php" class="nav-link register-link" title="新規登録">
                            <div class="nav-icon">
                                <?php include 'svgs/register.svg'; ?>
                            </div>
                            <span class="nav-text nav-text-hidden">新規登録</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <script>

        
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
            
            if (nav && (!nav.contains(event.target) && (!toggle || !toggle.contains(event.target)))) {
                nav.classList.remove('show');
            }
        });
    </script>