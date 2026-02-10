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
                    created_by VARCHAR(100),
                    updated_by VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $this->database->execute($sql);
            
            // インデックス作成
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_service_device_type ON device_info (service_name, device_type)");
            $this->database->execute("CREATE INDEX IF NOT EXISTS idx_device_info ON device_info (service_name, device_type, device_name, username1)");
            
        } else {
            // MySQL用
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
                    created_by VARCHAR(100) COMMENT '作成者',
                    updated_by VARCHAR(100) COMMENT '更新者',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
                    INDEX idx_service_device_type (service_name, device_type),
                    INDEX idx_device_info (service_name, device_type, device_name, username1)
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
    
    /**
     * device_infoテーブルにcreated_by、updated_byカラムを追加するマイグレーション
     * @return void
     */
    private function migrateCreatedUpdatedByColumns() {
        try {
            // device_infoテーブルが存在しない場合はスキップ
            if (!$this->database->tableExists('device_info')) {
                return;
            }
            
            // 既存のカラムを確認
            if ($this->isPgsql) {
                $checkSql = "
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'device_info' 
                    AND column_name IN ('created_by', 'updated_by')
                ";
            } else {
                $dbName = $this->database->query("SELECT DATABASE()")[0]['DATABASE()'];
                $checkSql = "
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'device_info' 
                    AND TABLE_SCHEMA = '{$dbName}'
                    AND COLUMN_NAME IN ('created_by', 'updated_by')
                ";
            }
            
            $existingColumns = $this->database->query($checkSql);
            $existingColumnNames = array_column($existingColumns, $this->isPgsql ? 'column_name' : 'COLUMN_NAME');
            
            // created_byカラムを追加
            if (!in_array('created_by', $existingColumnNames)) {
                error_log("Adding created_by column to device_info table");
                if ($this->isPgsql) {
                    $sql = "ALTER TABLE device_info ADD COLUMN created_by VARCHAR(100)";
                } else {
                    $sql = "ALTER TABLE device_info ADD COLUMN created_by VARCHAR(100) COMMENT '作成者' AFTER password10";
                }
                $this->database->execute($sql);
                error_log("created_by column added successfully");
            }
            
            // updated_byカラムを追加
            if (!in_array('updated_by', $existingColumnNames)) {
                error_log("Adding updated_by column to device_info table");
                if ($this->isPgsql) {
                    $sql = "ALTER TABLE device_info ADD COLUMN updated_by VARCHAR(100)";
                } else {
                    $sql = "ALTER TABLE device_info ADD COLUMN updated_by VARCHAR(100) COMMENT '更新者' AFTER created_by";
                }
                $this->database->execute($sql);
                error_log("updated_by column added successfully");
            }
            
        } catch (Exception $e) {
            error_log("Migration error for created_by/updated_by columns: " . $e->getMessage());
            // マイグレーションエラーは警告として扱い、処理を継続
        }
    }
}
?>
