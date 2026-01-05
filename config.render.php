<?php
/**
 * アプリケーション設定ファイル（Render本番環境用）
 */

// データベース設定（環境変数から取得）
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'device_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// アップロード設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['text/csv', 'application/csv', 'text/plain']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// エラーレポート設定（本番環境用）
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

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
    return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
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
