<?php
/**
 * 装置情報管理クラス
 */
class DeviceManager {
    private $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * 装置情報テーブルが存在するかチェック
     * @return bool
     */
    public function deviceInfoTableExists() {
        return $this->database->tableExists('device_info');
    }
    
    /**
     * 装置情報テーブルを作成
     * @return bool
     * @throws Exception
     */
    public function createDeviceInfoTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS device_info (
                primary_key VARCHAR(500) NOT NULL PRIMARY KEY COMMENT 'サービス名_装置種別名_装置名_ユーザ名の複合キー',
                service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
                device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
                device_name VARCHAR(100) NOT NULL COMMENT '装置名称',
                device_ip VARCHAR(45) COMMENT '装置IP',
                username VARCHAR(100) NOT NULL COMMENT 'ユーザー名',
                password VARCHAR(255) COMMENT 'パスワード',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                INDEX idx_service_device_type (service_name, device_type),
                INDEX idx_device_info (service_name, device_type, device_name, username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装置基本情報テーブル'
        ";
        
        try {
            $this->database->execute($sql);
            return true;
        } catch (Exception $e) {
            throw new Exception("装置情報テーブルの作成に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 動的テーブルが存在するかチェック
     * @param string $tableName
     * @return bool
     */
    public function dynamicTableExists($tableName) {
        return $this->database->tableExists($tableName);
    }
    
    /**
     * 動的テーブルを作成
     * @param string $tableName
     * @param string $primaryKeyColumn
     * @param array $extendedColumns
     * @return bool
     * @throws Exception
     */
    public function createDynamicTable($tableName, $primaryKeyColumn, $extendedColumns) {
        // テーブル名のみサニタイズ（カラム名は日本語を保持）
        $tableName = sanitizeTableName($tableName);
        
        $columnDefinitions = [];
        $columnDefinitions[] = "`{$primaryKeyColumn}` VARCHAR(500) NOT NULL PRIMARY KEY COMMENT '主キー'";
        
        foreach ($extendedColumns as $column) {
            // カラム名は日本語のまま使用、バッククォートでエスケープ
            $columnDefinitions[] = "`{$column}` TEXT COMMENT '" . addslashes($column) . "'";
        }
        
        $columnDefinitions[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'";
        $columnDefinitions[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                " . implode(",\n                ", $columnDefinitions) . "
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='動的テーブル: {$tableName}'
        ";
        
        try {
            error_log("Creating dynamic table SQL: " . $sql);
            $this->database->execute($sql);
            return true;
        } catch (Exception $e) {
            error_log("Dynamic table creation error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw new Exception("動的テーブル '{$tableName}' の作成に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報を挿入または更新
     * @param array $deviceData
     * @return bool
     * @throws Exception
     */
    public function insertOrUpdateDeviceInfo($deviceData) {
        $sql = "
            INSERT INTO device_info 
            (primary_key, service_name, device_type, device_name, device_ip, username, password)
            VALUES (:primary_key, :service_name, :device_type, :device_name, :device_ip, :username, :password)
            ON DUPLICATE KEY UPDATE
                service_name = VALUES(service_name),
                device_type = VALUES(device_type),
                device_name = VALUES(device_name),
                device_ip = VALUES(device_ip),
                username = VALUES(username),
                password = VALUES(password),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        try {
            $this->database->execute($sql, $deviceData);
            return true;
        } catch (Exception $e) {
            throw new Exception("装置情報の挿入に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 動的テーブルにデータを挿入または更新
     * @param string $tableName
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function insertOrUpdateDynamicData($tableName, $data) {
        $tableName = sanitizeTableName($tableName);
        
        if (empty($data)) {
            return true;
        }
        
        // カラム名とプレースホルダーを準備
        $columns = [];
        $placeholders = [];
        $updateClauses = [];
        $params = [];
        $placeholderIndex = 0;
        
        foreach ($data as $key => $value) {
            // カラム名は日本語のまま使用（バッククォートでエスケープ）
            $columns[] = "`{$key}`";
            
            // プレースホルダー名は英数字のみ（param0, param1, ...）
            $placeholder = "param" . $placeholderIndex;
            $placeholders[] = ":{$placeholder}";
            $params[$placeholder] = $value;
            
            // 主キー以外の更新句を作成
            $primaryKeyColumns = array_keys($data);
            if ($key !== $primaryKeyColumns[0]) {
                $updateClauses[] = "`{$key}` = VALUES(`{$key}`)";
            }
            
            $placeholderIndex++;
        }
        
        $sql = "
            INSERT INTO `{$tableName}` 
            (" . implode(", ", $columns) . ")
            VALUES (" . implode(", ", $placeholders) . ")
        ";
        
        if (!empty($updateClauses)) {
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(", ", $updateClauses) . ", updated_at = CURRENT_TIMESTAMP";
        }
        
        try {
            error_log("Dynamic insert SQL: " . $sql);
            error_log("Dynamic insert params: " . json_encode($params, JSON_UNESCAPED_UNICODE));
            
            $this->database->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Dynamic insert error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params, JSON_UNESCAPED_UNICODE));
            throw new Exception("動的テーブル '{$tableName}' へのデータ挿入に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * CSVデータを一括処理
     * @param CsvProcessor $csvProcessor
     * @return array 処理結果
     * @throws Exception
     */
    public function processCsvData(CsvProcessor $csvProcessor) {
        $results = [
            'success' => false,
            'device_info_count' => 0,
            'dynamic_tables_created' => [],
            'dynamic_data_count' => 0,
            'errors' => []
        ];
        
        try {
            // トランザクション開始
            $this->database->beginTransaction();
            
            // 装置情報テーブルが存在しない場合は作成
            if (!$this->deviceInfoTableExists()) {
                $this->createDeviceInfoTable();
            }
            
            $data = $csvProcessor->getData();
            $extendedColumns = $csvProcessor->getExtendedColumns();
            
            // 作成が必要な動的テーブルを特定
            $dynamicTables = [];
            foreach ($data as $row) {
                $tableName = $csvProcessor->generateTableName($row);
                if (!isset($dynamicTables[$tableName])) {
                    $dynamicTables[$tableName] = true;
                }
            }
            
            // 動的テーブルを作成
            foreach (array_keys($dynamicTables) as $tableName) {
                if (!$this->dynamicTableExists($tableName)) {
                    // 主キーカラム名を正しく生成
                    $primaryKeyColumn = $csvProcessor->generatePrimaryKeyColumnName($data[0]);
                    $this->createDynamicTable($tableName, $primaryKeyColumn, $extendedColumns);
                    $results['dynamic_tables_created'][] = $tableName;
                }
            }
            
            // リレーションテーブルが存在しない場合は作成
            if (!$this->relationTableExists()) {
                $this->createRelationTable();
            }
            
            // データを処理
            foreach ($data as $row) {
                // 装置情報テーブルに挿入
                $deviceInfo = $csvProcessor->convertToDeviceInfo($row);
                $this->insertOrUpdateDeviceInfo($deviceInfo);
                $results['device_info_count']++;
                
                // サービス名と装置種別のリレーションを登録
                try {
                    $this->registerServiceDeviceTypeRelation(
                        $row['サービス名'],
                        $row['装置種別'],
                        'CSV自動登録'
                    );
                } catch (Exception $e) {
                    // リレーション登録エラーはログに記録するが処理は継続
                    error_log("リレーション登録エラー: " . $e->getMessage());
                }
                
                // 動的テーブルに挿入（拡張カラムが存在する場合のみ）
                if (!empty($extendedColumns)) {
                    $tableName = $csvProcessor->generateTableName($row);
                    $extendedData = $csvProcessor->convertToExtendedData($row);
                    $this->insertOrUpdateDynamicData($tableName, $extendedData);
                    $results['dynamic_data_count']++;
                }
            }
            
            // コミット
            $this->database->commit();
            $results['success'] = true;
            
        } catch (Exception $e) {
            // ロールバック
            $this->database->rollBack();
            $results['errors'][] = $e->getMessage();
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * 装置情報を検索
     * @param string $serviceName
     * @param string $deviceType
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function searchDevices($serviceName = null, $deviceType = null, $limit = 100, $offset = 0) {
        $whereConditions = [];
        $params = [];
        
        if ($serviceName !== null && $serviceName !== '') {
            $whereConditions[] = "service_name LIKE :service_name";
            $params['service_name'] = '%' . $serviceName . '%';
        }
        
        if ($deviceType !== null && $deviceType !== '') {
            $whereConditions[] = "device_type LIKE :device_type";
            $params['device_type'] = '%' . $deviceType . '%';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT * FROM device_info 
            {$whereClause}
            ORDER BY service_name, device_type, device_name, username
            LIMIT :limit OFFSET :offset
        ";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("装置情報の検索に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報の総数を取得
     * @param string $serviceName
     * @param string $deviceType
     * @return int
     */
    public function countDevices($serviceName = null, $deviceType = null) {
        $whereConditions = [];
        $params = [];
        
        if ($serviceName !== null && $serviceName !== '') {
            $whereConditions[] = "service_name LIKE :service_name";
            $params['service_name'] = '%' . $serviceName . '%';
        }
        
        if ($deviceType !== null && $deviceType !== '') {
            $whereConditions[] = "device_type LIKE :device_type";
            $params['device_type'] = '%' . $deviceType . '%';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM device_info {$whereClause}";
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("装置数の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 動的テーブルの一覧を取得
     * @return array
     */
    public function getDynamicTables() {
        $sql = "
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name != 'device_info'
            ORDER BY table_name
        ";
        
        try {
            $stmt = $this->database->execute($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            throw new Exception("動的テーブル一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * サービス名の一覧を取得
     * @return array
     */
    public function getServiceNames() {
        $sql = "
            SELECT DISTINCT service_name 
            FROM device_info 
            ORDER BY service_name
        ";
        
        try {
            $stmt = $this->database->execute($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            throw new Exception("サービス名一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 指定サービス名の装置種別一覧を取得
     * @param string|null $serviceName
     * @return array
     */
    public function getDeviceTypes($serviceName = null) {
        $sql = "
            SELECT DISTINCT device_type 
            FROM device_info
        ";
        
        $params = [];
        if ($serviceName !== null && $serviceName !== '') {
            $sql .= " WHERE service_name = :service_name";
            $params['service_name'] = $serviceName;
        }
        
        $sql .= " ORDER BY device_type";
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            throw new Exception("装置種別一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報を詳細検索
     * @param string|null $serviceName
     * @param string|null $deviceType
     * @param string|null $deviceName
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function searchDevicesAdvanced($serviceName = null, $deviceType = null, $deviceName = null, $limit = 50, $offset = 0) {
        $whereConditions = [];
        $params = [];
        
        if ($serviceName !== null && $serviceName !== '') {
            $whereConditions[] = "service_name = :service_name";
            $params['service_name'] = $serviceName;
        }
        
        if ($deviceType !== null && $deviceType !== '') {
            $whereConditions[] = "device_type = :device_type";
            $params['device_type'] = $deviceType;
        }
        
        if ($deviceName !== null && $deviceName !== '') {
            $whereConditions[] = "device_name LIKE :device_name";
            $params['device_name'] = '%' . $deviceName . '%';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT 
                primary_key,
                service_name,
                device_type,
                device_name,
                device_ip,
                username,
                password,
                created_at,
                updated_at
            FROM device_info 
            {$whereClause}
            ORDER BY service_name, device_type, device_name, username
            LIMIT :limit OFFSET :offset
        ";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("装置情報の詳細検索に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 詳細検索の結果件数を取得
     * @param string|null $serviceName
     * @param string|null $deviceType
     * @param string|null $deviceName
     * @return int
     */
    public function countDevicesAdvanced($serviceName = null, $deviceType = null, $deviceName = null) {
        $whereConditions = [];
        $params = [];
        
        if ($serviceName !== null && $serviceName !== '') {
            $whereConditions[] = "service_name = :service_name";
            $params['service_name'] = $serviceName;
        }
        
        if ($deviceType !== null && $deviceType !== '') {
            $whereConditions[] = "device_type = :device_type";
            $params['device_type'] = $deviceType;
        }
        
        if ($deviceName !== null && $deviceName !== '') {
            $whereConditions[] = "device_name LIKE :device_name";
            $params['device_name'] = '%' . $deviceName . '%';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) FROM device_info {$whereClause}";
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("装置数の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置統計情報を取得
     * @return array
     */
    public function getDeviceStatistics() {
        try {
            $statistics = [
                'total_devices' => 0,
                'total_services' => 0,
                'total_device_types' => 0,
                'total_combinations' => 0,
                'total_relations' => 0
            ];
            
            // device_infoテーブルが存在する場合の統計
            if ($this->database->tableExists('device_info')) {
                $sql = "
                    SELECT 
                        COUNT(*) as total_devices,
                        COUNT(DISTINCT service_name) as total_services,
                        COUNT(DISTINCT device_type) as total_device_types,
                        COUNT(DISTINCT CONCAT(service_name, '_', device_type)) as total_combinations
                    FROM device_info
                ";
                $stmt = $this->database->execute($sql);
                $result = $stmt->fetch();
                
                $statistics['total_devices'] = (int)$result['total_devices'];
                $statistics['total_services'] = (int)$result['total_services'];
                $statistics['total_device_types'] = (int)$result['total_device_types'];
                $statistics['total_combinations'] = (int)$result['total_combinations'];
            }
            
            // リレーション数
            if ($this->relationTableExists()) {
                $sql = "SELECT COUNT(*) as count FROM service_device_type_relations WHERE is_active = 1";
                $stmt = $this->database->execute($sql);
                $result = $stmt->fetch();
                $statistics['total_relations'] = (int)$result['count'];
            }
            
            return $statistics;
            
        } catch (Exception $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [
                'total_devices' => 0,
                'total_services' => 0,
                'total_device_types' => 0,
                'total_combinations' => 0,
                'total_relations' => 0
            ];
        }
    }
    
    /**
     * リレーションテーブルが存在するかチェック
     * @return bool
     */
    public function relationTableExists() {
        return $this->database->tableExists('service_device_type_relations');
    }
    
    /**
     * リレーションテーブルを作成
     * @return bool
     * @throws Exception
     */
    public function createRelationTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS service_device_type_relations (
                id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
                service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
                device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
                description TEXT COMMENT '説明',
                is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ(1:有効, 0:無効)',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                UNIQUE KEY unique_service_device_type (service_name, device_type),
                INDEX idx_service_name (service_name),
                INDEX idx_device_type (device_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='サービス名と装置種別のリレーションテーブル'
        ";
        
        try {
            $this->database->execute($sql);
            return true;
        } catch (Exception $e) {
            throw new Exception("リレーションテーブルの作成に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * サービス名と装置種別のリレーションを登録
     * @param string $serviceName
     * @param string $deviceType
     * @param string $description
     * @return bool
     * @throws Exception
     */
    public function registerServiceDeviceTypeRelation($serviceName, $deviceType, $description = null) {
        // リレーションテーブルが存在しない場合は作成
        if (!$this->relationTableExists()) {
            $this->createRelationTable();
        }
        
        $sql = "
            INSERT INTO service_device_type_relations 
            (service_name, device_type, description) 
            VALUES (:service_name, :device_type, :description)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $params = [
            'service_name' => $serviceName,
            'device_type' => $deviceType,
            'description' => $description
        ];
        
        try {
            $this->database->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Relation registration error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params, JSON_UNESCAPED_UNICODE));
            throw new Exception("リレーション登録に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 指定サービス名の装置種別一覧をリレーションテーブルから取得
     * @param string|null $serviceName
     * @return array
     */
    public function getDeviceTypesFromRelation($serviceName = null) {
        // リレーションテーブルが存在しない場合は従来の方法
        if (!$this->relationTableExists()) {
            return $this->getDeviceTypes($serviceName);
        }
        
        $sql = "
            SELECT DISTINCT device_type 
            FROM service_device_type_relations
            WHERE is_active = 1
        ";
        
        $params = [];
        if ($serviceName !== null && $serviceName !== '') {
            $sql .= " AND service_name = :service_name";
            $params['service_name'] = $serviceName;
        }
        
        $sql .= " ORDER BY device_type";
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            throw new Exception("装置種別一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * リレーションテーブルから全サービス名を取得
     * @return array
     */
    public function getServiceNamesFromRelation() {
        // リレーションテーブルが存在しない場合は従来の方法
        if (!$this->relationTableExists()) {
            return $this->getServiceNames();
        }
        
        $sql = "
            SELECT DISTINCT service_name 
            FROM service_device_type_relations
            WHERE is_active = 1
            ORDER BY service_name
        ";
        
        try {
            $stmt = $this->database->execute($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            throw new Exception("サービス名一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * リレーションの存在チェック
     * @param string $serviceName
     * @param string $deviceType
     * @return bool
     */
    public function checkRelationExists($serviceName, $deviceType) {
        // リレーションテーブルが存在しない場合は常にtrueを返す
        if (!$this->relationTableExists()) {
            return true;
        }
        
        $sql = "
            SELECT COUNT(*) 
            FROM service_device_type_relations
            WHERE service_name = :service_name 
            AND device_type = :device_type 
            AND is_active = 1
        ";
        
        $params = [
            'service_name' => $serviceName,
            'device_type' => $deviceType
        ];
        
        try {
            $stmt = $this->database->execute($sql, $params);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            throw new Exception("リレーション存在確認に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 既存のdevice_infoからリレーションを自動構築
     * @return array 構築結果
     * @throws Exception
     */
    public function buildRelationsFromExistingData() {
        // リレーションテーブルの作成
        if (!$this->relationTableExists()) {
            $this->createRelationTable();
        }
        
        // 既存のサービス名と装置種別の組み合わせを取得
        $sql = "
            SELECT DISTINCT 
                service_name, 
                device_type,
                COUNT(*) as device_count
            FROM device_info 
            GROUP BY service_name, device_type
            ORDER BY service_name, device_type
        ";
        
        try {
            $stmt = $this->database->execute($sql);
            $combinations = $stmt->fetchAll();
            
            $registered = 0;
            $errors = [];
            
            foreach ($combinations as $combination) {
                try {
                    $description = "装置数: {$combination['device_count']}台 (自動構築)";
                    $this->registerServiceDeviceTypeRelation(
                        $combination['service_name'],
                        $combination['device_type'],
                        $description
                    );
                    $registered++;
                } catch (Exception $e) {
                    $errors[] = "{$combination['service_name']} - {$combination['device_type']}: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'registered' => $registered,
                'total_combinations' => count($combinations),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            throw new Exception("リレーション自動構築に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 全リレーション一覧を取得（管理用）
     * @return array
     */
    public function getAllRelations() {
        if (!$this->relationTableExists()) {
            return [];
        }
        
        $sql = "
            SELECT 
                id,
                service_name,
                device_type,
                description,
                is_active,
                created_at,
                updated_at
            FROM service_device_type_relations
            ORDER BY service_name, device_type
        ";
        
        try {
            $stmt = $this->database->execute($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("リレーション一覧の取得に失敗しました: " . $e->getMessage());
        }
    }
}
?>