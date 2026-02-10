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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if ($isPgsql) {
            // PostgreSQL用SQL
            $sql = "
                CREATE TABLE IF NOT EXISTS device_info (
                    primary_key VARCHAR(500) NOT NULL PRIMARY KEY,
                    service_name VARCHAR(100) NOT NULL,
                    device_type VARCHAR(100) NOT NULL,
                    device_name VARCHAR(100) NOT NULL,
                    login_ip VARCHAR(45),
                    username1 VARCHAR(100) NOT NULL,
                    password1 VARCHAR(255),
                    username2 VARCHAR(100),
                    password2 VARCHAR(255),
                    username3 VARCHAR(100),
                    password3 VARCHAR(255),
                    username4 VARCHAR(100),
                    password4 VARCHAR(255),
                    username5 VARCHAR(100),
                    password5 VARCHAR(255),
                    username6 VARCHAR(100),
                    password6 VARCHAR(255),
                    username7 VARCHAR(100),
                    password7 VARCHAR(255),
                    username8 VARCHAR(100),
                    password8 VARCHAR(255),
                    username9 VARCHAR(100),
                    password9 VARCHAR(255),
                    username10 VARCHAR(100),
                    password10 VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $this->database->execute($sql);
            // インデックス作成
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_service_device_type ON device_info (service_name, device_type)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_device_info ON device_info (service_name, device_type, device_name, username1)");
        } else {
            // MySQL用SQL
            $sql = "
                CREATE TABLE IF NOT EXISTS device_info (
                    primary_key VARCHAR(500) NOT NULL PRIMARY KEY COMMENT 'サービス名_装置種別名_装置名_ユーザ名の複合キー',
                    service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
                    device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
                    device_name VARCHAR(100) NOT NULL COMMENT '装置名称',
                    login_ip VARCHAR(45) COMMENT 'ログインIP',
                    username1 VARCHAR(100) NOT NULL COMMENT 'ユーザー名1',
                    password1 VARCHAR(255) COMMENT 'パスワード1',
                    username2 VARCHAR(100) COMMENT 'ユーザー名2',
                    password2 VARCHAR(255) COMMENT 'パスワード2',
                    username3 VARCHAR(100) COMMENT 'ユーザー名3',
                    password3 VARCHAR(255) COMMENT 'パスワード3',
                    username4 VARCHAR(100) COMMENT 'ユーザー名4',
                    password4 VARCHAR(255) COMMENT 'パスワード4',
                    username5 VARCHAR(100) COMMENT 'ユーザー名5',
                    password5 VARCHAR(255) COMMENT 'パスワード5',
                    username6 VARCHAR(100) COMMENT 'ユーザー名6',
                    password6 VARCHAR(255) COMMENT 'パスワード6',
                    username7 VARCHAR(100) COMMENT 'ユーザー名7',
                    password7 VARCHAR(255) COMMENT 'パスワード7',
                    username8 VARCHAR(100) COMMENT 'ユーザー名8',
                    password8 VARCHAR(255) COMMENT 'パスワード8',
                    username9 VARCHAR(100) COMMENT 'ユーザー名9',
                    password9 VARCHAR(255) COMMENT 'パスワード9',
                    username10 VARCHAR(100) COMMENT 'ユーザー名10',
                    password10 VARCHAR(255) COMMENT 'パスワード10',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                    INDEX idx_service_device_type (service_name, device_type),
                    INDEX idx_device_info (service_name, device_type, device_name, username1)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装置基本情報テーブル'
            ";
            $this->database->execute($sql);
        }
        
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
    public function createDynamicTable($tableName, $primaryKeyColumn, $extendedColumns) {
        // テーブル名のみサニタイズ（カラム名は日本語を保持）
        $tableName = sanitizeTableName($tableName);
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        $columnDefinitions = [];
        
        // MySQL/PostgreSQLで適切なクォート文字を使用
        $quote = $isPgsql ? '"' : '`';
        
        $columnDefinitions[] = "{$quote}{$primaryKeyColumn}{$quote} VARCHAR(500) NOT NULL PRIMARY KEY";
        
        foreach ($extendedColumns as $column) {
            // カラム名は日本語のまま使用、適切なクォートでエスケープ
            $columnDefinitions[] = "{$quote}{$column}{$quote} TEXT";
        }
        
        $columnDefinitions[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        
        if ($isPgsql) {
            $columnDefinitions[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        } else {
            $columnDefinitions[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'";
        }
        
        if ($isPgsql) {
            $sql = "
                CREATE TABLE IF NOT EXISTS \"{$tableName}\" (
                    " . implode(",\n                    ", $columnDefinitions) . "
                )
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS `{$tableName}` (
                    " . implode(",\n                    ", $columnDefinitions) . "
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='動的テーブル: {$tableName}'
            ";
        }
        
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
     * 装置情報を挿入または更新（動的カラム対応）
     * @param array $deviceData
     * @param array $additionalData 追加カラムデータ
     * @return bool
     * @throws Exception
     */
    public function insertOrUpdateDeviceInfo($deviceData, $additionalData = []) {
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        // 基本カラム
        $baseColumns = ['primary_key', 'service_name', 'device_type', 'device_name', 'login_ip', 'username1', 'password1'];
        for ($i = 2; $i <= 10; $i++) {
            $baseColumns[] = "username{$i}";
            $baseColumns[] = "password{$i}";
        }
        
        $columns = $baseColumns;
        $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
        $updateColumns = array_diff($columns, ['primary_key']);
        
        $params = $deviceData;
        
        // 作成者・更新者の情報を追加
        require_once __DIR__ . '/../includes/auth_helper.php';
        $currentUser = getLoggedInUsername();
        
        // created_byは新規作成時のみ設定（ON DUPLICATE KEY UPDATEでは更新されない）
        if ($currentUser) {
            $columns[] = 'created_by';
            $placeholders[] = ':created_by';
            $params['created_by'] = $currentUser;
            
            // updated_byは常に設定
            $columns[] = 'updated_by';
            $placeholders[] = ':updated_by';
            $updateColumns[] = 'updated_by';
            $params['updated_by'] = $currentUser;
        }
        
        // device_infoテーブルの既存カラムを取得
        $existingColumns = $this->getTableColumns('device_info');
        
        // 追加データがある場合、device_infoに存在するカラムのみ追加
        foreach ($additionalData as $column => $value) {
            if (in_array($column, $existingColumns) && !in_array($column, $columns)) {
                $columns[] = $isPgsql ? "\"{$column}\"" : "`{$column}`";
                $placeholders[] = ":{$column}";
                $updateColumns[] = $column;
                $params[$column] = $value;
            }
        }
        
        if ($isPgsql) {
            // PostgreSQL用: ON CONFLICT ... DO UPDATE
            $updateClauses = [];
            foreach ($updateColumns as $col) {
                $quotedCol = in_array($col, ['service_name', 'device_type', 'device_name', 'login_ip', 'created_by', 'updated_by']) ||
                             preg_match('/^(username|password)\d+$/', $col)
                    ? $col 
                    : "\"{$col}\"";
                $updateClauses[] = "{$quotedCol} = EXCLUDED.{$quotedCol}";
            }
            
            $sql = "
                INSERT INTO device_info 
                (" . implode(", ", $columns) . ")
                VALUES (" . implode(", ", $placeholders) . ")
                ON CONFLICT (primary_key) DO UPDATE SET
                    " . implode(",\n                    ", $updateClauses) . ",
                    updated_at = CURRENT_TIMESTAMP
            ";
        } else {
            // MySQL用: ON DUPLICATE KEY UPDATE
            $updateClauses = [];
            foreach ($updateColumns as $col) {
                $quotedCol = in_array($col, ['service_name', 'device_type', 'device_name', 'login_ip', 'created_by', 'updated_by']) ||
                             preg_match('/^(username|password)\d+$/', $col)
                    ? $col 
                    : "`{$col}`";
                $updateClauses[] = "{$quotedCol} = VALUES({$quotedCol})";
            }
            
            $sql = "
                INSERT INTO device_info 
                (" . implode(", ", $columns) . ")
                VALUES (" . implode(", ", $placeholders) . ")
                ON DUPLICATE KEY UPDATE
                    " . implode(",\n                    ", $updateClauses) . ",
                    updated_at = CURRENT_TIMESTAMP
            ";
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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if (empty($data)) {
            return true;
        }
        
        // カラム名とプレースホルダーを準備
        $columns = [];
        $placeholders = [];
        $updateClauses = [];
        $params = [];
        $placeholderIndex = 0;
        $primaryKeyColumn = null;
        
        // 適切なクォート文字を選択
        $quote = $isPgsql ? '"' : '`';
        
        foreach ($data as $key => $value) {
            if ($placeholderIndex === 0) {
                $primaryKeyColumn = $key; // 最初のカラムが主キー
            }
            
            // カラム名は日本語のまま使用（適切なクォートでエスケープ）
            $columns[] = "{$quote}{$key}{$quote}";
            
            // プレースホルダー名は英数字のみ（param0, param1, ...）
            $placeholder = "param" . $placeholderIndex;
            $placeholders[] = ":{$placeholder}";
            $params[$placeholder] = $value;
            
            // 主キー以外の更新句を作成
            if ($key !== $primaryKeyColumn) {
                if ($isPgsql) {
                    $updateClauses[] = "{$quote}{$key}{$quote} = EXCLUDED.{$quote}{$key}{$quote}";
                } else {
                    $updateClauses[] = "{$quote}{$key}{$quote} = VALUES({$quote}{$key}{$quote})";
                }
            }
            
            $placeholderIndex++;
        }
        
        if ($isPgsql) {
            // PostgreSQL用
            $tableQuote = '"';
            $sql = "
                INSERT INTO {$tableQuote}{$tableName}{$tableQuote} 
                (" . implode(", ", $columns) . ")
                VALUES (" . implode(", ", $placeholders) . ")
            ";
            
            if (!empty($updateClauses)) {
                $sql .= " ON CONFLICT ({$quote}{$primaryKeyColumn}{$quote}) DO UPDATE SET " 
                     . implode(", ", $updateClauses) 
                     . ", updated_at = CURRENT_TIMESTAMP";
            }
        } else {
            // MySQL用
            $sql = "
                INSERT INTO `{$tableName}` 
                (" . implode(", ", $columns) . ")
                VALUES (" . implode(", ", $placeholders) . ")
            ";
            
            if (!empty($updateClauses)) {
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(", ", $updateClauses) . ", updated_at = CURRENT_TIMESTAMP";
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
        
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        try {
            // PostgreSQLの場合、エラーハンドリングを改善
            if ($isPgsql) {
                // 各ステップを個別のトランザクションで処理
                error_log("PostgreSQL mode: Using individual transactions");
            }
            
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
            
            // ここまでの変更をコミット（PostgreSQLの場合、テーブル定義変更を確定）
            if ($isPgsql) {
                $this->database->commit();
                error_log("Table structure changes committed");
            } else {
                // MySQLの場合は1つのトランザクションで処理を続ける
            }
            
            // データを処理
            foreach ($data as $rowIndex => $row) {
                // PostgreSQLの場合は各行ごとに新しいトランザクションを開始
                if ($isPgsql) {
                    $this->database->beginTransaction();
                    error_log("Started new transaction for row {$rowIndex}");
                }
                
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
                    
                    // PostgreSQLの場合はロールバックして次の行へ
                    if ($isPgsql) {
                        $this->database->rollback();
                        error_log("Transaction rolled back for row {$rowIndex}");
                    }
                    
                    throw new Exception("行" . ($rowIndex + 2) . "のdevice_info登録でエラー: " . $e->getMessage());
                }
                
                // サービス名と装置種別のリレーションを登録
                // PostgreSQLの場合、トランザクション管理を簡素化するため、
                // upload.php側でまとめて登録するのでここではスキップ
                if (!$isPgsql) {
                    try {
                        $this->registerServiceDeviceTypeRelation(
                            $row['サービス名'],
                            $row['装置種別'],
                            'CSV自動登録'
                        );
                        error_log("Successfully registered relation for row {$rowIndex}");
                    } catch (Exception $e) {
                        // リレーション登録エラーはログに記録するが処理は継続
                        error_log("リレーション登録エラー: " . $e->getMessage());
                    }
                } else {
                    error_log("Skipping relation registration in PostgreSQL mode (will be done in upload.php)");
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
                    error_log("Stack trace: " . $e->getTraceAsString());
                    
                    // PostgreSQLの場合はロールバックして次の行へ
                    if ($isPgsql) {
                        $this->database->rollback();
                        error_log("Transaction rolled back for row {$rowIndex}");
                    }
                    
                    throw new Exception("行" . ($rowIndex + 2) . "の動的テーブル登録でエラー: " . $e->getMessage());
                }
                
                // PostgreSQLの場合は各行の処理後にコミット
                if ($isPgsql) {
                    $this->database->commit();
                    error_log("Transaction committed for row {$rowIndex}");
                }
            }
            
            // MySQLの場合のみ最終コミット（PostgreSQLは各行でコミット済み）
            if (!$isPgsql) {
                $this->database->commit();
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
        
        $sql = "
            SELECT * FROM device_info 
            {$whereClause}
            ORDER BY service_name, device_type, device_name, username1
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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if ($isPgsql) {
            // PostgreSQL用SQL
            $sql = "
                CREATE TABLE IF NOT EXISTS service_device_type_relations (
                    id SERIAL PRIMARY KEY,
                    service_name VARCHAR(100) NOT NULL,
                    device_type VARCHAR(100) NOT NULL,
                    description TEXT,
                    is_active SMALLINT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (service_name, device_type)
                )
            ";
            $this->database->execute($sql);
            // インデックス作成
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_service_name ON service_device_type_relations (service_name)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_device_type ON service_device_type_relations (device_type)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_active ON service_device_type_relations (is_active)");
        } else {
            // MySQL用SQL
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
            $this->database->execute($sql);
        }
        
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
        
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if ($isPgsql) {
            // PostgreSQL用
            $sql = "
                INSERT INTO service_device_type_relations 
                (service_name, device_type, description) 
                VALUES (:service_name, :device_type, :description)
                ON CONFLICT (service_name, device_type) 
                DO UPDATE SET
                    description = EXCLUDED.description,
                    is_active = TRUE,
                    updated_at = CURRENT_TIMESTAMP
            ";
        } else {
            // MySQL用
            $sql = "
                INSERT INTO service_device_type_relations 
                (service_name, device_type, description) 
                VALUES (:service_name, :device_type, :description)
                ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
            ";
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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if ($isPgsql) {
            $sql = "
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = :table_name
                ORDER BY ordinal_position
            ";
            $params = ['table_name' => $tableName];
        } else {
            $sql = "
                SELECT COLUMN_NAME as column_name
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                ORDER BY ORDINAL_POSITION
            ";
            $params = ['table_name' => $tableName];
        }
        
        try {
            $stmt = $this->database->execute($sql, $params);
            $columns = [];
            while ($row = $stmt->fetch()) {
                $columns[] = $row['column_name'];
            }
            return $columns;
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
    public function addColumnToDynamicTable($tableName, $columnName) {
        $tableName = sanitizeTableName($tableName);
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        if ($isPgsql) {
            $sql = "ALTER TABLE \"{$tableName}\" ADD COLUMN \"{$columnName}\" TEXT";
        } else {
            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` TEXT";
        }
        
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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        try {
            if ($isPgsql) {
                $sql = "DELETE FROM \"{$tableName}\" WHERE primary_key = :primary_key";
            } else {
                $sql = "DELETE FROM `{$tableName}` WHERE primary_key = :primary_key";
            }
            
            $this->database->execute($sql, ['primary_key' => $primaryKey]);
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
        $isPgsql = $this->database->getDbType() === 'pgsql';
        
        try {
            if ($isPgsql) {
                $sql = "SELECT * FROM \"{$tableName}\" WHERE primary_key = :primary_key";
            } else {
                $sql = "SELECT * FROM `{$tableName}` WHERE primary_key = :primary_key";
            }
            
            $stmt = $this->database->execute($sql, ['primary_key' => $primaryKey]);
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