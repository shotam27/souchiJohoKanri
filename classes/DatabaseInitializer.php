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

        $this->database->getSchemaManager()->createTable($table);
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

        $this->database->getSchemaManager()->createTable($table);
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

            $sm = $this->database->getSchemaManager();
            $existingCols = array_keys($sm->listTableColumns('device_info'));

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
