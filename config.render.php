<?php
/**
 * アプリケーション設定ファイル（Render本番環境用）
 */

// データベース設定（環境変数から取得）
// Render PostgreSQL用設定
define('DB_TYPE', getenv('DB_TYPE') ?: 'pgsql'); // 'pgsql' or 'mysql'
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432'); // PostgreSQL: 5432, MySQL: 3306
define('DB_NAME', getenv('DB_NAME') ?: 'device_management');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4'); // MySQLのみ使用

// アップロード設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['text/csv', 'application/csv', 'text/plain']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// エラーレポート設定（デバッグ用 - 問題解決後は無効化）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// エラーログ設定
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_error.log');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッション設定
session_start();

// クラスファイルの自動読み込み
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * テーブル名のサニタイズ関数
 */
function sanitizeTableName($name) {
    // 日本語（マルチバイト文字）を許可し、英数字、アンダースコア、マルチバイト文字以外を_に変換
    return preg_replace('/[^a-zA-Z0-9_\x80-\xFF]/', '_', $name);
}

/**
 * HTMLエスケープ関数
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
 * CSVヘッダーからカラム定義を生成
 */
function generateColumnsFromHeader($header) {
    $columns = [];
    foreach ($header as $columnName) {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $columnName);
        $columns[] = "`{$sanitized}` TEXT";
    }
    return implode(', ', $columns);
}

/**
 * CSVカラム名をサニタイズ
 */
function sanitizeColumnName($name) {
    return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
}

/**
 * 安全なJSON出力
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンス
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * 成功レスポンス
 */
function successResponse($data, $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
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
