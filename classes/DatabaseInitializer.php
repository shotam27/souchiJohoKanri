<?php
/**
 * データベース初期化クラス
 * 必要なテーブルの存在確認と自動作成を行う
 */
class DatabaseInitializer {
    private $database;
    private $isPgsql;
    
    public function __construct(Database $database) {
        $this->database = $database;
        $this->isPgsql = $database->getDbType() === 'pgsql';
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
    private function initializeDeviceInfoTable() {
        if ($this->database->tableExists('device_info')) {
            error_log("device_info table already exists");
            return false;
        }
        
        error_log("Creating device_info table");
        
        if ($this->isPgsql) {
            // PostgreSQL用
            $sql = "
                CREATE TABLE IF NOT EXISTS device_info (
                    primary_key VARCHAR(500) NOT NULL PRIMARY KEY,
                    service_name VARCHAR(100) NOT NULL,
                    device_type VARCHAR(100) NOT NULL,
                    device_name VARCHAR(100) NOT NULL,
                    device_ip VARCHAR(45),
                    username VARCHAR(100) NOT NULL,
                    password VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $this->database->execute($sql);
            
            // インデックス作成
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_service_device_type ON device_info (service_name, device_type)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_device_info ON device_info (service_name, device_type, device_name, username)");
            
        } else {
            // MySQL用
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
            $this->database->execute($sql);
        }
        
        error_log("device_info table created successfully");
        return true;
    }
    
    /**
     * service_device_type_relationsテーブルの初期化
     * @return bool テーブルを新規作成した場合true
     * @throws Exception
     */
    private function initializeRelationTable() {
        if ($this->database->tableExists('service_device_type_relations')) {
            error_log("service_device_type_relations table already exists");
            return false;
        }
        
        error_log("Creating service_device_type_relations table");
        
        if ($this->isPgsql) {
            // PostgreSQL用
            $sql = "
                CREATE TABLE IF NOT EXISTS service_device_type_relations (
                    id SERIAL PRIMARY KEY,
                    service_name VARCHAR(100) NOT NULL,
                    device_type VARCHAR(100) NOT NULL,
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (service_name, device_type)
                )
            ";
            $this->database->execute($sql);
            
            // インデックス作成
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_service_name ON service_device_type_relations (service_name)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_device_type ON service_device_type_relations (device_type)");
            
        } else {
            // MySQL用
            $sql = "
                CREATE TABLE IF NOT EXISTS service_device_type_relations (
                    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'リレーションID',
                    service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
                    device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
                    description TEXT COMMENT '説明',
                    is_active BOOLEAN DEFAULT TRUE COMMENT 'アクティブフラグ',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                    UNIQUE KEY unique_service_device (service_name, device_type),
                    INDEX idx_service_name (service_name),
                    INDEX idx_device_type (device_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='サービス-装置種別リレーションテーブル'
            ";
            $this->database->execute($sql);
        }
        
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
}
?>
