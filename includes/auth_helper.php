<?php
/**
 * 認証が必要なページ用のヘルパー関数
 * ログインしていない場合はログインページにリダイレクト
 */
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $currentUrl = $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($currentUrl));
        exit;
    }
}

/**
 * ログイン中のユーザー名を取得
 * @return string|null
 */
function getLoggedInUsername() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['username'] ?? null;
}

/**
 * ログイン中のユーザーIDを取得
 * @return int|null
 */
function getLoggedInUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * ログイン状態を確認
 * @return bool
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
