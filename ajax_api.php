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
            
            $macroContent = $generator->generate();

            // 操作ログ
            try {
                $actLogger = new ActivityLogger($database);
                $actLogger->log(
                    getLoggedInUsername() ?? 'unknown',
                    ActivityLogger::ACTION_TERATERM,
                    'device: ' . $deviceName . ' (' . $deviceIp . ')'
                );
            } catch (Exception $logEx) {
                error_log('Teraterm log error: ' . $logEx->getMessage());
            }

            echo $macroContent;
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
                // 動的テーブル用のデータを準備（primary_keyと拡張カラムのみ）
                $dynamicData = [
                    'primary_key' => $primaryKey
                ];
                
                // 拡張列のデータを追加（extended_で始まるPOSTパラメータ）
                $extendedColumns = $deviceManager->getDynamicTableExtendedColumns($newTableName);
                foreach ($extendedColumns as $col) {
                    $postKey = "extended_{$col}";
                    if (isset($_POST[$postKey])) {
                        $dynamicData[$col] = $_POST[$postKey];
                    }
                }
                
                // 拡張カラムがある場合のみ動的テーブルを更新
                if (count($dynamicData) > 1) {
                    $deviceManager->insertOrUpdateDynamicData($newTableName, $dynamicData);
                }
            }
            
            // 操作ログ
            $actLogger = new ActivityLogger($database);
            $actLogger->log(
                getLoggedInUsername() ?? 'unknown',
                ActivityLogger::ACTION_UPDATE_DEVICE,
                'primary_key: ' . $primaryKey
            );

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
            
            // 操作ログ
            $actLogger = new ActivityLogger($database);
            $actLogger->log(
                getLoggedInUsername() ?? 'unknown',
                ActivityLogger::ACTION_DELETE_DEVICE,
                'primary_key: ' . $primaryKey . ', service: ' . $serviceName . ', type: ' . $deviceType
            );

            $response = [
                'success' => true,
                'data' => null,
                'message' => '装置情報を削除しました'
            ];
            break;
            
        default:
            // ---- コマンド群 アクション ----
            if (in_array($action, ['save_command_group', 'get_command_groups', 'get_command_group_detail', 'delete_command_group', 'update_command_group'], true)) {
                // テーブルが無ければ自動作成
                if (!$database->tableExists('command_groups')) {
                    require_once __DIR__ . '/admin/init_command_groups_table.php';
                }

                if ($action === 'save_command_group') {
                    $groupName  = trim($_POST['group_name']  ?? '');
                    $deviceType = trim($_POST['device_type'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $prompts    = $_POST['prompts']   ?? [];
                    $commands   = $_POST['commands']  ?? [];

                    if ($groupName === '')  throw new Exception('コマンド群名は必須です');
                    if ($deviceType === '') throw new Exception('装置種別は必須です');
                    if (empty($commands))  throw new Exception('コマンドを1行以上登録してください');

                    $conn = $database->connect();
                    $conn->beginTransaction();
                    try {
                        $conn->executeStatement(
                            "INSERT INTO command_groups (group_name, device_type, description, created_by)
                             VALUES (?, ?, ?, ?)",
                            [$groupName, $deviceType, $description ?: null, $_SESSION['username'] ?? null]
                        );
                        $groupId = $conn->lastInsertId();
                        for ($i = 0; $i < count($commands); $i++) {
                            $cmd = trim($commands[$i]);
                            if ($cmd === '') continue;
                            $pmt = trim($prompts[$i] ?? '#');
                            $conn->executeStatement(
                                "INSERT INTO command_group_items (command_group_id, sort_order, prompt, command)
                                 VALUES (?, ?, ?, ?)",
                                [$groupId, $i, $pmt, $cmd]
                            );
                        }
                        $conn->commit();
                    } catch (Exception $ex) {
                        $conn->rollBack();
                        throw $ex;
                    }
                    $response = ['success' => true, 'message' => 'コマンド群を登録しました'];

                } elseif ($action === 'get_command_groups') {
                    $rows = $database->query(
                        "SELECT cg.id, cg.group_name, cg.device_type, cg.description,
                                COUNT(ci.id) AS item_count
                         FROM command_groups cg
                         LEFT JOIN command_group_items ci ON ci.command_group_id = cg.id
                         GROUP BY cg.id, cg.group_name, cg.device_type, cg.description
                         ORDER BY cg.device_type, cg.group_name"
                    );
                    $response = ['success' => true, 'groups' => $rows];

                } elseif ($action === 'get_command_group_detail') {
                    $id = (int)($_POST['id'] ?? 0);
                    if (!$id) throw new Exception('IDが不正です');
                    $groups = $database->query(
                        "SELECT id, group_name, device_type, description FROM command_groups WHERE id = ?", [$id]
                    );
                    if (empty($groups)) throw new Exception('コマンド群が見つかりません');
                    $group = $groups[0];
                    $group['items'] = $database->query(
                        "SELECT prompt, command FROM command_group_items
                         WHERE command_group_id = ? ORDER BY sort_order", [$id]
                    );
                    $response = ['success' => true, 'group' => $group];

                } elseif ($action === 'delete_command_group') {
                    $id = (int)($_POST['id'] ?? 0);
                    if (!$id) throw new Exception('IDが不正です');
                    $database->execute("DELETE FROM command_groups WHERE id = ?", [$id]);
                    $response = ['success' => true, 'message' => '削除しました'];

                } elseif ($action === 'update_command_group') {
                    $id          = (int)($_POST['id']          ?? 0);
                    $groupName   = trim($_POST['group_name']   ?? '');
                    $deviceType  = trim($_POST['device_type']  ?? '');
                    $description = trim($_POST['description']  ?? '');
                    $prompts     = $_POST['prompts']   ?? [];
                    $commands    = $_POST['commands']  ?? [];

                    if (!$id)            throw new Exception('IDが不正です');
                    if ($groupName === '')  throw new Exception('コマンド群名は必須です');
                    if ($deviceType === '') throw new Exception('装置種別は必須です');
                    if (empty($commands))  throw new Exception('コマンドを1行以上登録してください');

                    $conn = $database->connect();
                    $conn->beginTransaction();
                    try {
                        $conn->executeStatement(
                            "UPDATE command_groups SET group_name=?, device_type=?, description=? WHERE id=?",
                            [$groupName, $deviceType, $description ?: null, $id]
                        );
                        $conn->executeStatement(
                            "DELETE FROM command_group_items WHERE command_group_id=?", [$id]
                        );
                        for ($i = 0; $i < count($commands); $i++) {
                            $cmd = trim($commands[$i]);
                            if ($cmd === '') continue;
                            $pmt = trim($prompts[$i] ?? '#');
                            $conn->executeStatement(
                                "INSERT INTO command_group_items (command_group_id, sort_order, prompt, command) VALUES (?, ?, ?, ?)",
                                [$id, $i, $pmt, $cmd]
                            );
                        }
                        $conn->commit();
                    } catch (Exception $ex) {
                        $conn->rollBack();
                        throw $ex;
                    }
                    $response = ['success' => true, 'message' => 'コマンド群を更新しました'];
                }
            } elseif ($action === 'get_devices_for_macro') {
                    // 装置一覧取得（サービス名＋装置種別でフィルタ）
                    $svc = trim($_POST['service_name'] ?? '');
                    $dt  = trim($_POST['device_type']  ?? '');
                    if ($svc === '' || $dt === '') throw new Exception('サービス名と装置種別を指定してください');
                    $devices = $database->query(
                        "SELECT primary_key, device_name, login_ip, username1, password1
                         FROM device_info
                         WHERE service_name = ? AND device_type = ?
                         ORDER BY device_name",
                        [$svc, $dt]
                    );
                    $response = ['success' => true, 'devices' => $devices];

                } elseif ($action === 'get_command_groups_by_device_type') {
                    // 装置種別に紐づくコマンド群一覧
                    if (!$database->tableExists('command_groups')) {
                        $response = ['success' => true, 'groups' => []];
                    } else {
                        $dt = trim($_POST['device_type'] ?? '');
                        $sql  = "SELECT id, group_name, device_type FROM command_groups";
                        $params = [];
                        if ($dt !== '') { $sql .= " WHERE device_type = ?"; $params[] = $dt; }
                        $sql .= " ORDER BY device_type, group_name";
                        $response = ['success' => true, 'groups' => $database->query($sql, $params)];
                    }

                } else {
                throw new Exception('不正なアクションです: ' . $action);
            }    }
    
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