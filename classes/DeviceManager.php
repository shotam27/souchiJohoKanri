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
    public function createDeviceInfoTable(): bool {
        $sm = $this->database->getSchemaManager();
        if ($sm->tablesExist(['device_info'])) {
            return false;
        }

        $table = new \Doctrine\DBAL\Schema\Table('device_info');
        $table->addColumn('primary_key',  \Doctrine\DBAL\Types\Types::STRING, ['length' => 500, 'notnull' => true]);
        $table->addColumn('service_name', \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('device_type',  \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('device_name',  \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('login_ip',     \Doctrine\DBAL\Types\Types::STRING, ['length' => 45,  'notnull' => false]);
        $table->addColumn('username1',    \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->addColumn('password1',    \Doctrine\DBAL\Types\Types::STRING, ['length' => 255, 'notnull' => false]);
        for ($i = 2; $i <= 10; $i++) {
            $table->addColumn("username{$i}", \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => false]);
            $table->addColumn("password{$i}", \Doctrine\DBAL\Types\Types::STRING, ['length' => 255, 'notnull' => false]);
        }
        $table->addColumn('created_by', \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => false]);
        $table->addColumn('updated_by', \Doctrine\DBAL\Types\Types::STRING, ['length' => 100, 'notnull' => false]);
        $table->addColumn('created_at', \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['primary_key']);
        $table->addIndex(['service_name', 'device_type'], 'idx_service_device_type');
        $table->addIndex(['service_name', 'device_type', 'device_name', 'username1'], 'idx_device_info');
        $table->addOption('engine',  'InnoDB');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        $sm->createTable($table);
        return true;
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
    public function createDynamicTable($tableName, $primaryKeyColumn, $extendedColumns): bool {
        $tableName = sanitizeTableName($tableName);
        $sm = $this->database->getSchemaManager();

        if ($sm->tablesExist([$tableName])) {
            return false;
        }

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn($primaryKeyColumn, \Doctrine\DBAL\Types\Types::STRING, ['length' => 500, 'notnull' => true]);
        foreach ($extendedColumns as $column) {
            $table->addColumn($column, \Doctrine\DBAL\Types\Types::TEXT, ['notnull' => false]);
        }
        $table->addColumn('created_at', \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->setPrimaryKey([$primaryKeyColumn]);
        $table->addOption('engine',  'InnoDB');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        try {
            $sm->createTable($table);
            return true;
        } catch (\Exception $e) {
            throw new Exception("動的テーブル '{$tableName}' の作成に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報を挿入または更新（動的カラム対応）
     * @param array $deviceData
     * @param array $additionalData 追加カラムデータ
     * @return bool
     * @throws Exception
     */
    public function insertOrUpdateDeviceInfo($deviceData, $additionalData = []): bool {
        $baseColumns = ['primary_key', 'service_name', 'device_type', 'device_name', 'login_ip', 'username1', 'password1'];
        for ($i = 2; $i <= 10; $i++) {
            $baseColumns[] = "username{$i}";
            $baseColumns[] = "password{$i}";
        }

        $columns       = $baseColumns;
        $updateColumns = array_values(array_diff($baseColumns, ['primary_key']));
        $params        = $deviceData;

        require_once __DIR__ . '/../includes/auth_helper.php';
        $currentUser = getLoggedInUsername();
        if ($currentUser) {
            $columns[]       = 'created_by';
            $params['created_by'] = $currentUser;
            $columns[]       = 'updated_by';
            $updateColumns[] = 'updated_by';
            $params['updated_by'] = $currentUser;
        }

        $existingColumns = $this->getTableColumns('device_info');
        foreach ($additionalData as $column => $value) {
            if (in_array($column, $existingColumns) && !in_array($column, $columns)) {
                $columns[]       = $column;
                $updateColumns[] = $column;
                $params[$column] = $value;
            }
        }

        $qTable        = $this->database->quoteIdentifier('device_info');
        $qConflict     = $this->database->quoteIdentifier('primary_key');
        $quotedCols    = array_map(fn($c) => $this->database->quoteIdentifier($c), $columns);
        $placeholders  = array_map(fn($c) => ":{$c}", $columns);

        if ($this->database->isMySQL()) {
            $updateClauses = array_map(function ($col) {
                $q = $this->database->quoteIdentifier($col);
                return "{$q} = VALUES({$q})";
            }, $updateColumns);
            $sql = "INSERT INTO {$qTable} (" . implode(', ', $quotedCols) . ")
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE
                    " . implode(",\n                    ", $updateClauses) . ",
                    updated_at = CURRENT_TIMESTAMP";
        } else {
            $updateClauses = array_map(function ($col) {
                $q = $this->database->quoteIdentifier($col);
                return "{$q} = EXCLUDED.{$q}";
            }, $updateColumns);
            $sql = "INSERT INTO {$qTable} (" . implode(', ', $quotedCols) . ")
                VALUES (" . implode(', ', $placeholders) . ")
                ON CONFLICT ({$qConflict}) DO UPDATE SET
                    " . implode(",\n                    ", $updateClauses) . ",
                    updated_at = CURRENT_TIMESTAMP";
        }

        try {
            $this->database->execute($sql, $params);
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

        $columns          = [];
        $placeholders     = [];
        $updateClauses    = [];
        $params           = [];
        $placeholderIndex = 0;
        $primaryKeyColumn = null;

        foreach ($data as $key => $value) {
            if ($placeholderIndex === 0) {
                $primaryKeyColumn = $key;
            }
            $columns[] = $this->database->quoteIdentifier($key);
            $placeholder = 'param' . $placeholderIndex;
            $placeholders[] = ":{$placeholder}";
            $params[$placeholder] = $value;

            if ($key !== $primaryKeyColumn) {
                $q = $this->database->quoteIdentifier($key);
                if ($this->database->isMySQL()) {
                    $updateClauses[] = "{$q} = VALUES({$q})";
                } else {
                    $updateClauses[] = "{$q} = EXCLUDED.{$q}";
                }
            }
            $placeholderIndex++;
        }

        $qTable    = $this->database->quoteIdentifier($tableName);
        $qConflict = $this->database->quoteIdentifier($primaryKeyColumn);
        $colList   = implode(', ', $columns);
        $phList    = implode(', ', $placeholders);
        $updateStr = implode(', ', $updateClauses);

        if ($this->database->isMySQL()) {
            $sql = "INSERT INTO {$qTable} ({$colList}) VALUES ({$phList})";
            if (!empty($updateClauses)) {
                $sql .= " ON DUPLICATE KEY UPDATE {$updateStr}, updated_at = CURRENT_TIMESTAMP";
            }
        } else {
            $sql = "INSERT INTO {$qTable} ({$colList}) VALUES ({$phList})";
            if (!empty($updateClauses)) {
                $sql .= " ON CONFLICT ({$qConflict}) DO UPDATE SET {$updateStr}, updated_at = CURRENT_TIMESTAMP";
            }
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
            'columns_added' => [],
            'errors' => []
        ];
        
        try {
            
            // トランザクション開始
            $this->database->beginTransaction();
            
            // 装置情報テーブルの存在確認（DatabaseInitializerで作成済みのはず）
            if (!$this->deviceInfoTableExists()) {
                error_log("Warning: device_info table not found. Creating now...");
                $this->createDeviceInfoTable();
            }
            
            $data = $csvProcessor->getData();
            $extendedColumns = $csvProcessor->getExtendedColumns();
            
            // device_infoテーブルの既存カラムを取得
            $deviceInfoExistingColumns = $this->getTableColumns('device_info');
            
            // 動的テーブルごとに必要なカラムを判定
            $dynamicTableColumns = [];
            foreach ($data as $row) {
                $tableName = $csvProcessor->generateTableName($row);
                
                if (!isset($dynamicTableColumns[$tableName])) {
                    $dynamicTableColumns[$tableName] = [];
                }
                
                // 各拡張カラムをdevice_infoまたは動的テーブルに振り分け
                foreach ($extendedColumns as $column) {
                    // device_infoに存在しないカラムのみ動的テーブルへ
                    if (!in_array($column, $deviceInfoExistingColumns)) {
                        if (!in_array($column, $dynamicTableColumns[$tableName])) {
                            $dynamicTableColumns[$tableName][] = $column;
                        }
                    }
                }
            }
            
            // 動的テーブルを作成またはカラム追加
            foreach ($dynamicTableColumns as $tableName => $columns) {
                if (!$this->dynamicTableExists($tableName)) {
                    // テーブル新規作成
                    $primaryKeyColumn = $csvProcessor->generatePrimaryKeyColumnName($data[0]);
                    $this->createDynamicTable($tableName, $primaryKeyColumn, $columns);
                    $results['dynamic_tables_created'][] = $tableName;
                } else {
                    // 既存テーブルに不足カラムを追加
                    $existingColumns = $this->getTableColumns($tableName);
                    foreach ($columns as $column) {
                        if (!in_array($column, $existingColumns)) {
                            $this->addColumnToDynamicTable($tableName, $column);
                            $results['columns_added'][] = "{$tableName}.{$column}";
                        }
                    }
                }
            }
            
            // リレーションテーブルの存在確認（DatabaseInitializerで作成済みのはず）
            if (!$this->relationTableExists()) {
                error_log("Warning: service_device_type_relations table not found. Creating now...");
                $this->createRelationTable();
            }
            
            // テーブル定義変更をコミット（次のデータ挿入トランザクション開始前に確定）
            $this->database->commit();
            
            // データを処理
            foreach ($data as $rowIndex => $row) {
                $this->database->beginTransaction();
                error_log("Started transaction for row {$rowIndex}");
                
                error_log("Processing row {$rowIndex}: " . json_encode($row, JSON_UNESCAPED_UNICODE));
                
                try {
                    // 装置情報テーブルに挿入（拡張カラムも含む）
                    $deviceInfo = $csvProcessor->convertToDeviceInfo($row);
                    error_log("Device info data: " . json_encode($deviceInfo, JSON_UNESCAPED_UNICODE));
                    
                    // 拡張カラムの中でdevice_infoに存在するカラムを抽出
                    $additionalDeviceInfoData = [];
                    foreach ($extendedColumns as $column) {
                        if (in_array($column, $deviceInfoExistingColumns)) {
                            $additionalDeviceInfoData[$column] = isset($row[$column]) ? $row[$column] : null;
                        }
                    }
                    error_log("Additional device info data: " . json_encode($additionalDeviceInfoData, JSON_UNESCAPED_UNICODE));
                    
                    $this->insertOrUpdateDeviceInfo($deviceInfo, $additionalDeviceInfoData);
                    error_log("Successfully inserted device_info for row {$rowIndex}");
                    $results['device_info_count']++;
                    
                } catch (Exception $e) {
                    error_log("Row {$rowIndex} - device_info insert error: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    
                    $this->database->rollBack();
                    error_log("Transaction rolled back for row {$rowIndex}");
                    throw new Exception("行" . ($rowIndex + 2) . "のdevice_info登録でエラー: " . $e->getMessage());
                }
                
                // サービス名と装置種別のリレーションを登録
                try {
                    $this->registerServiceDeviceTypeRelation(
                        $row['サービス名'],
                        $row['装置種別'],
                        'CSV自動登録'
                    );
                    error_log("Successfully registered relation for row {$rowIndex}");
                } catch (Exception $e) {
                    error_log("リレーション登録エラー: " . $e->getMessage());
                }
                
                // 動的テーブルに挿入
                try {
                    $tableName = $csvProcessor->generateTableName($row);
                    error_log("Dynamic table name: {$tableName}");
                    
                    // 動的テーブル用のデータを作成
                    $dynamicData = [];
                    $dynamicData[$csvProcessor->generatePrimaryKeyColumnName($row)] = $csvProcessor->generatePrimaryKey($row);
                    
                    foreach ($extendedColumns as $column) {
                        // device_infoに存在するカラムはdevice_infoに、それ以外は動的テーブルに
                        if (!in_array($column, $deviceInfoExistingColumns)) {
                            // 動的テーブルのカラムとして登録
                            $dynamicData[$column] = isset($row[$column]) ? $row[$column] : null;
                        }
                    }
                    
                    error_log("Dynamic data: " . json_encode($dynamicData, JSON_UNESCAPED_UNICODE));
                    
                    // 動的テーブルに挿入（動的テーブル用のデータが存在する場合のみ）
                    if (count($dynamicData) > 1) { // primary_key以外のカラムがある場合
                        $this->insertOrUpdateDynamicData($tableName, $dynamicData);
                        error_log("Successfully inserted dynamic data for row {$rowIndex}");
                        $results['dynamic_data_count']++;
                    } else {
                        error_log("No dynamic data to insert for row {$rowIndex}");
                    }
                    
                } catch (Exception $e) {
                    error_log("Row {$rowIndex} - dynamic table insert error: " . $e->getMessage());
                    $this->database->rollBack();
                    throw new Exception("行" . ($rowIndex + 2) . "の動的テーブル登録でエラー: " . $e->getMessage());
                }

                $this->database->commit();
                error_log("Transaction committed for row {$rowIndex}");
            }
            $results['success'] = true;
            
        } catch (Exception $e) {
            // ロールバック（トランザクションがアクティブな場合のみ）
            try {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                    error_log("Transaction rolled back due to error");
                }
            } catch (Exception $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
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
        
        $limitInt  = (int)$limit;
        $offsetInt = (int)$offset;
        $sql = "
            SELECT * FROM device_info 
            {$whereClause}
            ORDER BY service_name, device_type, device_name, username1
            LIMIT {$limitInt} OFFSET {$offsetInt}
        ";
        
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
    public function getDynamicTables(): array {
        try {
            $systemTables = ['device_info', 'service_device_type_relations', 'audit_logs', 'users'];
            $all = $this->database->getSchemaManager()->listTableNames();
            return array_values(array_filter($all, fn($t) => !in_array($t, $systemTables)));
        } catch (\Exception $e) {
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
        
        $limitInt  = (int)$limit;
        $offsetInt = (int)$offset;
        $sql = "
            SELECT 
                primary_key,
                service_name,
                device_type,
                device_name,
                login_ip,
                username1,
                password1,
                username2,
                password2,
                username3,
                password3,
                username4,
                password4,
                username5,
                password5,
                username6,
                password6,
                username7,
                password7,
                username8,
                password8,
                username9,
                password9,
                username10,
                password10,
                created_by,
                updated_by,
                created_at,
                updated_at
            FROM device_info 
            {$whereClause}
            ORDER BY service_name, device_type, device_name, username1
            LIMIT {$limitInt} OFFSET {$offsetInt}
        ";
        
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
    public function createRelationTable(): bool {
        $sm = $this->database->getSchemaManager();
        if ($sm->tablesExist(['service_device_type_relations'])) {
            return false;
        }

        $table = new \Doctrine\DBAL\Schema\Table('service_device_type_relations');
        $table->addColumn('id',           \Doctrine\DBAL\Types\Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('service_name', \Doctrine\DBAL\Types\Types::STRING,  ['length' => 100, 'notnull' => true]);
        $table->addColumn('device_type',  \Doctrine\DBAL\Types\Types::STRING,  ['length' => 100, 'notnull' => true]);
        $table->addColumn('description',  \Doctrine\DBAL\Types\Types::TEXT,    ['notnull' => false]);
        $table->addColumn('is_active',    \Doctrine\DBAL\Types\Types::SMALLINT, ['notnull' => false, 'default' => 1]);
        $table->addColumn('created_at',   \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at',   \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['service_name', 'device_type'], 'unique_service_device_type');
        $table->addIndex(['service_name'], 'idx_service_name');
        $table->addIndex(['device_type'],  'idx_device_type');
        $table->addIndex(['is_active'],    'idx_active');
        $table->addOption('engine',  'InnoDB');
        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collate', 'utf8mb4_unicode_ci');

        $sm->createTable($table);
        return true;
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
        
        if ($this->database->isMySQL()) {
            $sql = "INSERT INTO service_device_type_relations
                (service_name, device_type, description)
                VALUES (:service_name, :device_type, :description)
                ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP";
        } else {
            $sql = "INSERT INTO service_device_type_relations
                (service_name, device_type, description)
                VALUES (:service_name, :device_type, :description)
                ON CONFLICT (service_name, device_type) DO UPDATE SET
                    description = EXCLUDED.description,
                    is_active = TRUE,
                    updated_at = CURRENT_TIMESTAMP";
        }
        
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
    
    /**
     * テーブルのカラム一覧を取得
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName) {
        try {
            $sm = $this->database->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return [];
            }
            return array_keys($sm->listTableColumns($tableName));
        } catch (Exception $e) {
            error_log("Failed to get columns for table {$tableName}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 動的テーブルにカラムを追加
     * @param string $tableName
     * @param string $columnName
     * @return bool
     * @throws Exception
     */
    public function addColumnToDynamicTable($tableName, $columnName): bool {
        $tableName = sanitizeTableName($tableName);

        // カラムが既に存在する場合はスキップ
        $existingColumns = $this->database->getTableColumns($tableName);
        foreach ($existingColumns as $col) {
            if (strcasecmp($col['COLUMN_NAME'], $columnName) === 0) {
                return false; // 既に存在
            }
        }

        $qTable = $this->database->quoteIdentifier($tableName);
        $qCol   = $this->database->quoteIdentifier($columnName);
        $sql = "ALTER TABLE {$qTable} ADD COLUMN {$qCol} TEXT";
        
        try {
            error_log("Adding column to dynamic table: {$sql}");
            $this->database->execute($sql);
            return true;
        } catch (Exception $e) {
            error_log("Failed to add column {$columnName} to table {$tableName}: " . $e->getMessage());
            throw new Exception("カラム '{$columnName}' をテーブル '{$tableName}' に追加できませんでした: " . $e->getMessage());
        }
    }
    
    /**
     * Primary Keyで装置情報を取得
     * @param string $primaryKey
     * @return array|null
     */
    public function getDeviceByPrimaryKey($primaryKey) {
        $sql = "SELECT * FROM device_info WHERE primary_key = :primary_key";
        
        try {
            $stmt = $this->database->execute($sql, ['primary_key' => $primaryKey]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("装置情報の取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報を更新
     * @param string $primaryKey
     * @param array $deviceData
     * @return bool
     */
    public function updateDeviceInfo($primaryKey, $deviceData) {
        $updateFields = [];
        $params = ['primary_key' => $primaryKey];
        
        // 更新可能なフィールド
        $allowedFields = ['service_name', 'device_type', 'device_name', 'login_ip'];
        for ($i = 1; $i <= 10; $i++) {
            $allowedFields[] = "username{$i}";
            $allowedFields[] = "password{$i}";
        }
        
        foreach ($deviceData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("更新するフィールドがありません");
        }
        
        // 更新者の情報を追加
        require_once __DIR__ . '/../includes/auth_helper.php';
        $currentUser = getLoggedInUsername();
        if ($currentUser) {
            $updateFields[] = "updated_by = :updated_by";
            $params['updated_by'] = $currentUser;
        }
        
        $sql = "UPDATE device_info SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE primary_key = :primary_key";
        
        try {
            $this->database->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            throw new Exception("装置情報の更新に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 装置情報を削除
     * @param string $primaryKey
     * @return bool
     */
    public function deleteDeviceInfo($primaryKey) {
        try {
            // device_infoから削除
            $sql = "DELETE FROM device_info WHERE primary_key = :primary_key";
            $this->database->execute($sql, ['primary_key' => $primaryKey]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("装置情報の削除に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 動的テーブルからも関連データを削除
     * @param string $tableName
     * @param string $primaryKey
     * @return bool
     */
    public function deleteFromDynamicTable($tableName, $primaryKey) {
        $qTable = $this->database->quoteIdentifier(sanitizeTableName($tableName));
        try {
            $this->database->execute("DELETE FROM {$qTable} WHERE primary_key = :primary_key", ['primary_key' => $primaryKey]);
            return true;
        } catch (Exception $e) {
            throw new Exception("動的テーブルからの削除に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 動的テーブルからデータを取得
     * @param string $tableName
     * @param string $primaryKey
     * @return array|null
     */
    public function getDynamicTableData($tableName, $primaryKey) {
        $qTable = $this->database->quoteIdentifier(sanitizeTableName($tableName));
        try {
            $stmt = $this->database->execute("SELECT * FROM {$qTable} WHERE primary_key = :primary_key", ['primary_key' => $primaryKey]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("動的テーブル '{$tableName}' からのデータ取得に失敗: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 動的テーブルの拡張列名を取得（基本列を除く）
     * @param string $tableName
     * @return array
     */
    public function getDynamicTableExtendedColumns($tableName) {
        $allColumns = $this->getTableColumns($tableName);
        
        // 基本列（device_infoと共通の列）を除外
        $baseColumns = [
            'primary_key', 'device_name', 'login_ip',
            'username1', 'password1', 'username2', 'password2',
            'username3', 'password3', 'username4', 'password4',
            'username5', 'password5', 'username6', 'password6',
            'username7', 'password7', 'username8', 'password8',
            'username9', 'password9', 'username10', 'password10',
            'created_at', 'updated_at'
        ];
        
        $extendedColumns = [];
        foreach ($allColumns as $column) {
            if (!in_array($column, $baseColumns)) {
                $extendedColumns[] = $column;
            }
        }
        
        return $extendedColumns;
    }
}
?>