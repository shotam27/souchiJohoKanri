<?php
/**
 * Docker環境用アプリケーション設定ファイル
 */

// データベース設定（Docker環境用）
define('DB_HOST', $_ENV['DB_HOST'] ?? 'mysql');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'device_management');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'rootpassword');
define('DB_CHARSET', 'utf8mb4');

// アップロード設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['text/csv', 'application/csv', 'text/plain']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// エラーレポート設定（Docker環境では有効）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッション設定
session_start();

// オートロード設定
function autoload($className) {
    $classFile = __DIR__ . '/classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
}
spl_autoload_register('autoload');

// 共通関数

/**
 * HTMLエスケープ
 * @param string $str
 * @return string
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRFトークン生成
 * @return string
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 * @param string $token
 * @return bool
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ファイル名をサニタイズ
 * @param string $filename
 * @return string
 */
function sanitizeFilename($filename) {
    // 危険な文字を除去
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
    // 連続するドットやアンダースコアを単一に
    $filename = preg_replace('/[_\.]{2,}/', '_', $filename);
    return $filename;
}

/**
 * データベースのテーブル名として使用可能な文字列に変換
 * @param string $str
 * @return string
 */
function sanitizeTableName($str) {
    // 日本語文字は残し、特殊文字のみ除去
    $str = preg_replace('/[^\p{L}\p{N}_]/u', '_', $str);
    // 連続するアンダースコアを単一に
    $str = preg_replace('/_+/', '_', $str);
    // 先頭末尾のアンダースコアを除去
    $str = trim($str, '_');
    return $str;
}

/**
 * エラーメッセージをセッションに設定
 * @param string $message
 */
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * 成功メッセージをセッションに設定
 * @param string $message
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * エラーメッセージを取得して削除
 * @return string|null
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

/**
 * 成功メッセージを取得して削除
 * @return string|null
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

/**
 * Docker環境の健全性チェック
 * @return array
 */
function checkDockerEnvironment() {
    $checks = [
        'database_connection' => false,
        'upload_directory' => false,
        'required_extensions' => false
    ];
    
    // データベース接続チェック
    try {
        $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
        $database->connect();
        $checks['database_connection'] = true;
    } catch (Exception $e) {
        // 接続失敗
    }
    
    // アップロードディレクトリチェック
    if (is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)) {
        $checks['upload_directory'] = true;
    }
    
    // 必要な拡張機能チェック
    if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
        $checks['required_extensions'] = true;
    }
    
    return $checks;
}
?>