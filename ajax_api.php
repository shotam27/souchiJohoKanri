<?php
/**
 * Ajax API エンドポイント
 * フロントエンドからの非同期リクエストを処理
 */

require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログイン必須
if (!isLoggedIn()) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

// JSON形式で出力
header('Content-Type: application/json; charset=UTF-8');

// CORS対応（必要に応じて）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // リクエストメソッドの確認
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET' && $method !== 'POST') {
        throw new Exception('許可されていないリクエストメソッドです');
    }
    
    // アクションパラメータの取得
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('アクションが指定されていません');
    }
    
    // データベース接続
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
    $charset = ($dbType === 'pgsql') ? 'utf8' : DB_CHARSET;
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, $charset, $dbType, defined('DB_PORT') ? DB_PORT : null);
    $deviceManager = new DeviceManager($database);
    
    $response = ['success' => false, 'data' => null, 'message' => ''];
    
    switch ($action) {
        case 'get_services':
            // サービス名一覧をリレーションテーブルから取得
            $services = $deviceManager->getServiceNamesFromRelation();
            $response = [
                'success' => true,
                'data' => $services,
                'message' => 'サービス名一覧を取得しました'
            ];
            break;
            
        case 'get_device_types':
            // 装置種別一覧をリレーションテーブルから取得（サービス名でフィルタ）
            $serviceName = $_GET['service_name'] ?? $_POST['service_name'] ?? null;
            $deviceTypes = $deviceManager->getDeviceTypesFromRelation($serviceName);
            $response = [
                'success' => true,
                'data' => $deviceTypes,
                'message' => '装置種別一覧を取得しました'
            ];
            break;
            
        case 'get_all_relations':
            // 全リレーション取得
            $relations = $deviceManager->getAllRelations();
            $response = [
                'success' => true,
                'data' => $relations,
                'message' => 'リレーション一覧を取得しました'
            ];
            break;
            
        case 'build_relations':
            // 既存データからリレーション構築
            $result = $deviceManager->buildRelationsFromExistingData();
            $response = [
                'success' => $result['success'],
                'data' => null,
                'message' => $result['message']
            ];
            break;
            
        case 'search_devices':
            // 装置情報検索
            $serviceName = $_GET['service_name'] ?? $_POST['service_name'] ?? null;
            $deviceType = $_GET['device_type'] ?? $_POST['device_type'] ?? null;
            $deviceName = $_GET['device_name'] ?? $_POST['device_name'] ?? null;
            $page = (int)($_GET['page'] ?? $_POST['page'] ?? 1);
            $limit = 20; // 1ページあたりの件数
            $offset = ($page - 1) * $limit;
            
            // 空文字列をnullに変換
            $serviceName = $serviceName === '' ? null : $serviceName;
            $deviceType = $deviceType === '' ? null : $deviceType;
            $deviceName = $deviceName === '' ? null : $deviceName;
            
            $devices = $deviceManager->searchDevicesAdvanced(
                $serviceName, 
                $deviceType, 
                $deviceName, 
                $limit, 
                $offset
            );
            
            $total = $deviceManager->countDevicesAdvanced(
                $serviceName, 
                $deviceType, 
                $deviceName
            );
            
            $totalPages = ceil($total / $limit);
            
            $response = [
                'success' => true,
                'data' => [
                    'devices' => $devices,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'total_count' => $total,
                        'per_page' => $limit
                    ]
                ],
                'message' => "検索結果: {$total}件見つかりました"
            ];
            break;
            
        case 'get_statistics':
            // 装置統計情報を取得
            $stats = $deviceManager->getDeviceStatistics();
            $response = [
                'success' => true,
                'data' => $stats,
                'message' => '統計情報を取得しました'
            ];
            break;
            
        case 'build_relations':
            // 既存データからリレーションを自動構築
            $buildResult = $deviceManager->buildRelationsFromExistingData();
            $response = [
                'success' => true,
                'data' => $buildResult,
                'message' => "リレーション構築完了: {$buildResult['registered']}件登録"
            ];
            break;
            
        case 'get_all_relations':
            // 全リレーション一覧を取得（管理用）
            $relations = $deviceManager->getAllRelations();
            $response = [
                'success' => true,
                'data' => $relations,
                'message' => 'リレーション一覧を取得しました'
            ];
            break;
            
        case 'generate_teraterm_macro':
            // Teratermマクロ生成
            $deviceIp = $_GET['device_ip'] ?? '';
            $username = $_GET['username'] ?? '';
            $password = $_GET['password'] ?? '';
            $deviceName = $_GET['device_name'] ?? 'device';
            
            if (empty($deviceIp) || empty($username) || empty($password)) {
                throw new Exception('IPアドレス、ユーザー名、パスワードが必要です');
            }
            
            require_once 'classes/TeratermMacroGenerator.php';
            
            $generator = new TeratermMacroGenerator($deviceIp, $username, $password);
            
            // ファイル名を生成（安全な文字に変換）
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $deviceName);
            $filename = "{$safeName}_{$deviceIp}.ttl";
            
            // JSONレスポンスではなく、ファイルとしてダウンロード
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $generator->generate();
            exit; // JSON出力をスキップ
            
        case 'get_device':
            // 装置情報を取得
            $primaryKey = $_GET['primary_key'] ?? $_POST['primary_key'] ?? '';
            
            if (empty($primaryKey)) {
                throw new Exception('Primary keyが指定されていません');
            }
            
            $device = $deviceManager->getDeviceByPrimaryKey($primaryKey);
            
            if (!$device) {
                throw new Exception('指定された装置情報が見つかりません');
            }
            
            // 動的テーブルのデータも取得
            $tableName = sanitizeTableName($device['service_name'] . '_' . $device['device_type']);
            $extendedData = [];
            $extendedColumns = [];
            
            if ($deviceManager->dynamicTableExists($tableName)) {
                $dynamicData = $deviceManager->getDynamicTableData($tableName, $primaryKey);
                if ($dynamicData) {
                    $extendedColumns = $deviceManager->getDynamicTableExtendedColumns($tableName);
                    // 拡張列のデータのみを抽出
                    foreach ($extendedColumns as $col) {
                        $extendedData[$col] = $dynamicData[$col] ?? null;
                    }
                }
            }
            
            $response = [
                'success' => true,
                'data' => [
                    'device' => $device,
                    'extended_data' => $extendedData,
                    'extended_columns' => $extendedColumns
                ],
                'message' => '装置情報を取得しました'
            ];
            break;
            
        case 'update_device':
            // 装置情報を更新
            $primaryKey = $_POST['primary_key'] ?? '';
            $oldServiceName = $_POST['old_service_name'] ?? '';
            $oldDeviceType = $_POST['old_device_type'] ?? '';
            
            if (empty($primaryKey)) {
                throw new Exception('Primary keyが指定されていません');
            }
            
            // 更新データを取得
            $updateData = [
                'service_name' => $_POST['service_name'] ?? '',
                'device_type' => $_POST['device_type'] ?? '',
                'device_name' => $_POST['device_name'] ?? '',
                'login_ip' => $_POST['login_ip'] ?? null,
                'username1' => $_POST['username1'] ?? ''
            ];
            
            // パスワード1-10、ユーザー名2-10を追加
            $updateData['password1'] = $_POST['password1'] ?? null;
            for ($i = 2; $i <= 10; $i++) {
                $updateData["username{$i}"] = $_POST["username{$i}"] ?? null;
                $updateData["password{$i}"] = $_POST["password{$i}"] ?? null;
            }
            
            // 必須項目チェック
            if (empty($updateData['service_name']) || empty($updateData['device_type']) || 
                empty($updateData['device_name']) || empty($updateData['username1'])) {
                throw new Exception('サービス名、装置種別、装置名称、ユーザー名1は必須です');
            }
            
            // device_infoテーブルを更新
            $deviceManager->updateDeviceInfo($primaryKey, $updateData);
            
            // 動的テーブルも更新
            $oldTableName = sanitizeTableName($oldServiceName . '_' . $oldDeviceType);
            $newTableName = sanitizeTableName($updateData['service_name'] . '_' . $updateData['device_type']);
            
            // サービス名または装置種別が変更された場合、古いテーブルから削除
            if ($oldTableName !== $newTableName && !empty($oldServiceName) && !empty($oldDeviceType)) {
                if ($deviceManager->dynamicTableExists($oldTableName)) {
                    $deviceManager->deleteFromDynamicTable($oldTableName, $primaryKey);
                }
            }
            
            // 新しい動的テーブルに挿入または更新
            if ($deviceManager->dynamicTableExists($newTableName)) {
                // 動的テーブル用のデータを準備
                $dynamicData = [
                    'primary_key' => $primaryKey,
                    'device_name' => $updateData['device_name'],
                    'login_ip' => $updateData['login_ip'],
                    'username1' => $updateData['username1'],
                    'password1' => $updateData['password1']
                ];
                
                // username2-10, password2-10を追加
                for ($i = 2; $i <= 10; $i++) {
                    $dynamicData["username{$i}"] = $updateData["username{$i}"];
                    $dynamicData["password{$i}"] = $updateData["password{$i}"];
                }
                
                // 拡張列のデータを追加（extended_で始まるPOSTパラメータ）
                $extendedColumns = $deviceManager->getDynamicTableExtendedColumns($newTableName);
                foreach ($extendedColumns as $col) {
                    $postKey = "extended_{$col}";
                    if (isset($_POST[$postKey])) {
                        $dynamicData[$col] = $_POST[$postKey];
                    }
                }
                
                $deviceManager->insertOrUpdateDynamicData($newTableName, $dynamicData);
            }
            
            $response = [
                'success' => true,
                'data' => null,
                'message' => '装置情報を更新しました'
            ];
            break;
            
        case 'delete_device':
            // 装置情報を削除
            $primaryKey = $_POST['primary_key'] ?? '';
            $serviceName = $_POST['service_name'] ?? '';
            $deviceType = $_POST['device_type'] ?? '';
            
            if (empty($primaryKey)) {
                throw new Exception('Primary keyが指定されていません');
            }
            
            // device_infoから削除
            $deviceManager->deleteDeviceInfo($primaryKey);
            
            // 動的テーブルからも削除
            if (!empty($serviceName) && !empty($deviceType)) {
                $tableName = sanitizeTableName($serviceName . '_' . $deviceType);
                if ($deviceManager->dynamicTableExists($tableName)) {
                    $deviceManager->deleteFromDynamicTable($tableName, $primaryKey);
                }
            }
            
            $response = [
                'success' => true,
                'data' => null,
                'message' => '装置情報を削除しました'
            ];
            break;
            
        default:
            throw new Exception('不正なアクションです: ' . $action);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'data' => null,
        'message' => $e->getMessage()
    ];
    
    // エラーログに記録（本番環境では重要）
    error_log("Ajax API Error: " . $e->getMessage());
    
    // HTTPステータスコードを設定
    http_response_code(400);
} finally {
    // データベース接続を閉じる
    if (isset($database)) {
        $database->close();
    }
}

// JSON形式でレスポンスを出力
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>