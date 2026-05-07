<?php
/**
 * コマンド群実行 Teraterm マクロ生成・ダウンロード
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_helper.php';

requireLogin();

// CSRF チェック
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('不正なリクエストです');
}

$serviceName    = trim($_POST['service_name']      ?? '');
$deviceType     = trim($_POST['device_type']       ?? '');
$commandGroupId = (int)($_POST['command_group_id'] ?? 0);
$primaryKeys    = $_POST['primary_keys']   ?? [];
$ips            = $_POST['ips']            ?? [];
$usernames      = $_POST['usernames']      ?? [];
$passwords      = $_POST['passwords']      ?? [];
$deviceNames    = $_POST['device_names']   ?? [];

if (!$primaryKeys || !$commandGroupId) {
    http_response_code(400);
    exit('装置またはコマンド群が選択されていません');
}

try {
    $dbType   = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset  = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);

    // コマンド群情報を取得
    $groups = $database->query(
        "SELECT id, group_name, device_type FROM command_groups WHERE id = ?",
        [$commandGroupId]
    );
    if (empty($groups)) {
        http_response_code(404);
        exit('コマンド群が見つかりません');
    }
    $group = $groups[0];

    $items = $database->query(
        "SELECT prompt, command FROM command_group_items
         WHERE command_group_id = ? ORDER BY sort_order",
        [$commandGroupId]
    );

} catch (Exception $e) {
    http_response_code(500);
    exit('DBエラー: ' . htmlspecialchars($e->getMessage()));
}

/* ============================================================
   TTL マクロ生成
   ============================================================ */
$now       = date('Y-m-d H:i:s');
$groupName = $group['group_name'];
$totalDev  = count($primaryKeys);

$ttl  = "; ============================================================\n";
$ttl .= "; コマンド群実行マクロ\n";
$ttl .= "; サービス   : {$serviceName}\n";
$ttl .= "; 装置種別   : {$deviceType}\n";
$ttl .= "; コマンド群 : {$groupName}\n";
$ttl .= "; 対象装置数 : {$totalDev} 台\n";
$ttl .= "; 生成日時   : {$now}\n";
$ttl .= "; ============================================================\n\n";

foreach ($primaryKeys as $idx => $pk) {
    $ip       = $ips[$idx]        ?? '';
    $user     = $usernames[$idx]  ?? '';
    $pass     = $passwords[$idx]  ?? '';
    $devName  = $deviceNames[$idx] ?? "device_$idx";
    $num      = $idx + 1;

    // 接続情報が不完全な場合はスキップしコメントを残す
    if ($ip === '' || $user === '') {
        $ttl .= "; !! [{$num}/{$totalDev}] {$devName} — IPまたはユーザー名が未設定のためスキップ\n\n";
        continue;
    }

    $safeDev = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $devName);

    $ttl .= "; ------------------------------------------------------------\n";
    $ttl .= "; 装置 [{$num}/{$totalDev}]: {$devName} ({$ip})\n";
    $ttl .= "; ------------------------------------------------------------\n";
    $ttl .= "HOSTADDR = '{$ip}'\n";
    $ttl .= "USERNAME = '{$user}'\n";
    $ttl .= "PASSWORD = '{$pass}'\n";
    $ttl .= "\n";
    $ttl .= "COMMAND = HOSTADDR\n";
    $ttl .= "strconcat COMMAND ':22 /ssh /2 /auth=password /user='\n";
    $ttl .= "strconcat COMMAND USERNAME\n";
    $ttl .= "strconcat COMMAND ' /passwd='\n";
    $ttl .= "strconcat COMMAND PASSWORD\n";
    $ttl .= "\n";
    $ttl .= "connect COMMAND\n";
    $ttl .= "\n";

    // コマンド群の各行: プロンプト待ち → コマンド送信
    foreach ($items as $item) {
        $prompt  = addslashes($item['prompt']);
        $command = addslashes($item['command']);
        $ttl .= "wait '{$prompt}'\n";
        $ttl .= "sendln '{$command}'\n";
    }

}

$ttl .= "; ============================================================\n";
$ttl .= "; マクロ終了\n";
$ttl .= "; ============================================================\n";
$ttl .= "end\n";

// ファイル名生成
$safeService = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $serviceName);
$safeType    = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $deviceType);
$safeGroup   = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $groupName);
$filename    = "{$safeService}_{$safeType}_{$safeGroup}_" . date('Ymd_His') . ".ttl";

// 操作ログ
try {
    $actLogger = new ActivityLogger($database);
    $actLogger->log(
        getLoggedInUsername() ?? 'unknown',
        ActivityLogger::ACTION_TERATERM,
        "コマンド群マクロ: {$groupName} / {$serviceName}/{$deviceType} / {$totalDev}台"
    );
} catch (Exception $e) {
    error_log('Macro log error: ' . $e->getMessage());
}

// ダウンロード出力
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($ttl));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $ttl;
exit;
