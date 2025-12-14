<?php
/**
 * Ajax API エンドポイント
 * フロントエンドからの非同期リクエストを処理
 */

require_once 'config.php';

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
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
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