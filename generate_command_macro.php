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
$macroMode      = trim($_POST['macro_mode']        ?? 'normal');
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
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, 'mysql', defined('DB_PORT') ? DB_PORT : null);

    // コマンド群情報を取得
    $groups = $database->query(
        "SELECT id, group_name, device_type, protocol, port FROM command_groups WHERE id = ?",
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
$protocol  = $group['protocol'] ?? 'ssh';
$port      = (int)($group['port'] ?? 22);
$totalDev  = count($primaryKeys);

// ヘッダーコメント（共通）
$ttl  = "; ============================================================\n";
$ttl .= "; コマンド群実行マクロ\n";
$ttl .= "; サービス   : {$serviceName}\n";
$ttl .= "; 装置種別   : {$deviceType}\n";
$ttl .= "; コマンド群 : {$groupName}\n";
$ttl .= "; 対象装置数 : {$totalDev} 台\n";
$ttl .= "; 生成日時   : {$now}\n";
if ($macroMode === 'device_select') {
    $ttl .= "; モード     : 装置選択あり（旧 Teraterm 対応）\n";
}
$ttl .= "; 接続     : " . strtoupper($protocol) . " ポート {$port}\n";
$ttl .= "; ============================================================\n\n";

if ($macroMode === 'device_select') {
    /* ----------------------------------------------------------
       装置選択モード（参考.ttl スタイル）
       1. 全装置の接続変数を定義
       2. inputbox で装置番号を選択
       3. if/endif で HOSTADDR/USERNAME/PASSWORD を設定
       4. 接続文字列構築 → connect
       5. コマンド送信（1 回のみ）
       ---------------------------------------------------------- */

    // 有効な装置のみ抽出
    $validDevices = [];
    foreach ($primaryKeys as $idx => $pk) {
        $ip      = $ips[$idx]         ?? '';
        $user    = $usernames[$idx]   ?? '';
        $pass    = $passwords[$idx]   ?? '';
        $devName = $deviceNames[$idx] ?? "device_$idx";
        if ($ip === '' || $user === '') {
            $ttl .= "; !! " . ($idx + 1) . ": {$devName} — IPまたはユーザー名が未設定のためスキップ\n";
            continue;
        }
        $validDevices[] = compact('ip', 'user', 'pass', 'devName');
    }

    if (empty($validDevices)) {
        $ttl .= "; エラー: 有効な装置が存在しません\n";
        $ttl .= "end\n";
    } else {
        // Step 1: 接続変数を定義
        foreach ($validDevices as $n => $d) {
            $num = $n + 1;
            $ttl .= "HOSTADDR{$num} = '{$d['ip']}'\n";
            $ttl .= "USERNAME{$num} = '{$d['user']}'\n";
            $ttl .= "PASSWORD{$num} = '{$d['pass']}'\n";
            $ttl .= "\n";
        }

        // Step 2: inputbox メッセージ（装置一覧）
        $ttl .= "MESSAGE = \"接続先選択：\"\n";
        foreach ($validDevices as $n => $d) {
            $num = $n + 1;
            $ttl .= "strconcat MESSAGE \"\\n {$num}:{$d['devName']}({$d['user']})\"\n";
        }
        $ttl .= "inputbox MESSAGE \"装置選択\" 1\n";
        $ttl .= "str2int NODESELECT inputstr\n";
        $ttl .= "\n";

        // Step 3: if/endif で変数設定
        foreach ($validDevices as $n => $d) {
            $num = $n + 1;
            $ttl .= "if NODESELECT={$num} then\n";
            $ttl .= "\tHOSTADDR = HOSTADDR{$num}\n";
            $ttl .= "\tUSERNAME = USERNAME{$num}\n";
            $ttl .= "\tPASSWORD = PASSWORD{$num}\n";
            $ttl .= "endif\n";
            $ttl .= "\n";
        }

        // Step 4: 接続文字列構築と接続
        if ($protocol === 'ssh') {
            $ttl .= "line1 = HOSTADDR\n";
            $ttl .= "strconcat line1 ':{$port} /ssh /2 /auth=password /user='\n";
            $ttl .= "strconcat line1 USERNAME\n";
            $ttl .= "strconcat line1 ' /passwd='\n";
            $ttl .= "strconcat line1 PASSWORD\n";
            $ttl .= "\n";
            $ttl .= "connect line1\n";
        } else {
            // Telnet: 認証情報はコマンド項目の wait/sendln で対応
            $ttl .= "line1 = HOSTADDR\n";
            $ttl .= "strconcat line1 ':{$port} /telnet'\n";
            $ttl .= "\n";
            $ttl .= "connect line1\n";
        }
        $ttl .= "\n";

        // Step 5: コマンド送信（選択後の 1 装置に対して）
        foreach ($items as $item) {
            $prompt  = addslashes($item['prompt']);
            $command = addslashes($item['command']);
            $ttl .= "wait '{$prompt}'\n";
            $ttl .= "sendln '{$command}'\n";
        }

        $ttl .= "\n";
        $ttl .= "; ============================================================\n";
        $ttl .= "; マクロ終了\n";
        $ttl .= "; ============================================================\n";
        $ttl .= "end\n";
    }

} else {
    /* ----------------------------------------------------------
       通常モード: 全装置に順番に接続してコマンド実行
       ---------------------------------------------------------- */
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
        if ($protocol === 'ssh') {
            $ttl .= "COMMAND = HOSTADDR\n";
            $ttl .= "strconcat COMMAND ':{$port} /ssh /2 /auth=password /user='\n";
            $ttl .= "strconcat COMMAND USERNAME\n";
            $ttl .= "strconcat COMMAND ' /passwd='\n";
            $ttl .= "strconcat COMMAND PASSWORD\n";
            $ttl .= "\n";
            $ttl .= "connect COMMAND\n";
        } else {
            $ttl .= "COMMAND = HOSTADDR\n";
            $ttl .= "strconcat COMMAND ':{$port} /telnet'\n";
            $ttl .= "\n";
            $ttl .= "connect COMMAND\n";
        }
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
}

// ファイル名生成
$safeService = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $serviceName);
$safeType    = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $deviceType);
$safeGroup   = preg_replace('/[^a-zA-Z0-9._\-]/u', '_', $groupName);
$modeSuffix  = ($macroMode === 'device_select') ? '_select' : '';
$filename    = "{$safeService}_{$safeType}_{$safeGroup}{$modeSuffix}_" . date('Ymd_His') . ".ttl";

// 操作ログ
try {
    $actLogger = new ActivityLogger($database);
    $actLogger->log(
        getLoggedInUsername() ?? 'unknown',
        ActivityLogger::ACTION_TERATERM,
        "コマンド群マクロ: {$groupName} / {$serviceName}/{$deviceType} / {$totalDev}台" .
            ($macroMode === 'device_select' ? ' [装置選択あり]' : '')
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
