<?php
/**
 * データベース初期化クラス
 * 必要なテーブルの存在確認と自動作成を行う
 */
class DatabaseInitializer {
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * すべての必須テーブルを初期化
     * @return array 初期化結果
     * @throws Exception
     */
    public function initializeAllTables() {
        $results = [
            'success' => false,
            'tables_created' => [],
            'errors' => []
        ];
        
        try {
            error_log("Database initialization started");
            
            // device_infoテーブルの初期化
            if ($this->initializeDeviceInfoTable()) {
                $results['tables_created'][] = 'device_info';
                error_log("device_info table initialized");
            }
            
            // service_device_type_relationsテーブルの初期化
            if ($this->initializeRelationTable()) {
                $results['tables_created'][] = 'service_device_type_relations';
                error_log("service_device_type_relations table initialized");
            }
            
            // created_by、updated_byカラムのマイグレーション
            $this->migrateCreatedUpdatedByColumns();
            
            $results['success'] = true;
            error_log("Database initialization completed successfully");
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            error_log("Database initialization error: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * device_infoテーブルの初期化
     * @return bool テーブルを新規作成した場合true
     * @throws Exception
     */
    private function initializeDeviceInfoTable(): bool {
        if ($this->database->tableExists('device_info')) {
            error_log("device_info table already exists");
            return false;
        }

        error_log("Creating device_info table");

        $userCols = '';
        for ($i = 2; $i <= 10; $i++) {
            $userCols .= "    `username{$i}` VARCHAR(100) DEFAULT NULL,\n";
            $userCols .= "    `password{$i}` VARCHAR(255) DEFAULT NULL,\n";
        }
        $sql = "CREATE TABLE `device_info` (\n"
             . "    `primary_key`  VARCHAR(500) NOT NULL,\n"
             . "    `service_name` VARCHAR(100) NOT NULL,\n"
             . "    `device_type`  VARCHAR(100) NOT NULL,\n"
             . "    `device_name`  VARCHAR(100) NOT NULL,\n"
             . "    `login_ip`     VARCHAR(45)  DEFAULT NULL,\n"
             . "    `username1`    VARCHAR(100) NOT NULL,\n"
             . "    `password1`    VARCHAR(255) DEFAULT NULL,\n"
             . $userCols
             . "    `created_by`   VARCHAR(100) DEFAULT NULL,\n"
             . "    `updated_by`   VARCHAR(100) DEFAULT NULL,\n"
             . "    `created_at`   DATETIME     DEFAULT NULL,\n"
             . "    `updated_at`   DATETIME     DEFAULT NULL,\n"
             . "    PRIMARY KEY (`primary_key`),\n"
             . "    INDEX `idx_service_device_type` (`service_name`, `device_type`),\n"
             . "    INDEX `idx_device_info` (`service_name`, `device_type`, `device_name`, `username1`)\n"
             . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->database->execute($sql);
        error_log("device_info table created successfully");
        return true;
    }
    
    /**
     * service_device_type_relationsテーブルの初期化
     * @return bool テーブルを新規作成した場合true
     * @throws Exception
     */
    private function initializeRelationTable(): bool {
        if ($this->database->tableExists('service_device_type_relations')) {
            error_log("service_device_type_relations table already exists");
            return false;
        }

        error_log("Creating service_device_type_relations table");

        $sql = "CREATE TABLE `service_device_type_relations` (\n"
             . "    `id`           INT          NOT NULL AUTO_INCREMENT,\n"
             . "    `service_name` VARCHAR(100) NOT NULL,\n"
             . "    `device_type`  VARCHAR(100) NOT NULL,\n"
             . "    `description`  TEXT         DEFAULT NULL,\n"
             . "    `is_active`    SMALLINT     DEFAULT 1,\n"
             . "    `created_at`   DATETIME     DEFAULT NULL,\n"
             . "    `updated_at`   DATETIME     DEFAULT NULL,\n"
             . "    PRIMARY KEY (`id`),\n"
             . "    UNIQUE INDEX `unique_service_device_type` (`service_name`, `device_type`),\n"
             . "    INDEX `idx_service_name` (`service_name`),\n"
             . "    INDEX `idx_device_type`  (`device_type`),\n"
             . "    INDEX `idx_active`       (`is_active`)\n"
             . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->database->execute($sql);
        error_log("service_device_type_relations table created successfully");
        return true;
    }
    
    /**
     * テーブルの存在確認
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        return $this->database->tableExists($tableName);
    }
    
    /**
     * 必須テーブルがすべて存在するか確認
     * @return array 各テーブルの存在状況
     */
    public function checkRequiredTables() {
        return [
            'device_info' => $this->database->tableExists('device_info'),
            'service_device_type_relations' => $this->database->tableExists('service_device_type_relations')
        ];
    }
    
    /**
     * device_infoテーブルにcreated_by、updated_byカラムを追加するマイグレーション
     * @return void
     */
    private function migrateCreatedUpdatedByColumns(): void {
        try {
            if (!$this->database->tableExists('device_info')) {
                return;
            }

            $stmt = $this->database->execute(
                "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'device_info'"
            );
            $existingCols = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('created_by', $existingCols)) {
                error_log("Adding created_by column to device_info table");
                $qTable = $this->database->quoteIdentifier('device_info');
                $this->database->execute("ALTER TABLE {$qTable} ADD COLUMN created_by VARCHAR(100)");
                error_log("created_by column added successfully");
            }

            if (!in_array('updated_by', $existingCols)) {
                error_log("Adding updated_by column to device_info table");
                $qTable = $this->database->quoteIdentifier('device_info');
                $this->database->execute("ALTER TABLE {$qTable} ADD COLUMN updated_by VARCHAR(100)");
                error_log("updated_by column added successfully");
            }
        } catch (Exception $e) {
            error_log("Migration error for created_by/updated_by columns: " . $e->getMessage());
        }
    }
}
?>
