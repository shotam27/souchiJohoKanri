<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * Database接続管理クラス（Doctrine DBAL使用）
 */
class Database {
    private ?DBALConnection $connection = null;
    private string $host;
    private string $dbname;
    private string $username;
    private string $password;
    private string $charset;
    private string $dbType;
    private int $port;

    public function __construct($host, $dbname, $username, $password, $charset = 'utf8mb4', $dbType = 'mysql', $port = null) {
        $this->host     = $host;
        $this->dbname   = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset  = $charset;
        $this->dbType   = $dbType;
        $this->port     = (int)($port ?: ($dbType === 'pgsql' ? 5432 : 3306));
    }

    public function getDbType(): string {
        return $this->dbType;
    }

    /**
     * 現在のDBがMySQLかどうかを返す（PostgreSQLならfalse）
     */
    public function isMySQL(): bool {
        return $this->connect()->getDatabasePlatform() instanceof MySQLPlatform;
    }

    /**
     * DBAL接続を取得（接続済みでなければ接続する）
     */
    public function connect(): DBALConnection {
        if ($this->connection === null) {
            $driver = $this->dbType === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql';

            $params = [
                'driver'   => $driver,
                'host'     => $this->host,
                'dbname'   => $this->dbname,
                'user'     => $this->username,
                'password' => $this->password,
                'port'     => $this->port,
                'charset'  => $this->charset,
            ];

            // Google Cloud SQL用SSL設定（DB_SSL_MODE環境変数が設定されている時のみ＝Render環境）
            if ($this->dbType === 'mysql' && getenv('DB_SSL_MODE')) {
                $params['driverOptions'] = [
                    PDO::MYSQL_ATTR_SSL_CA              => '/etc/ssl/certs/ca-certificates.crt',
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                ];
            }

            try {
                $this->connection = DriverManager::getConnection($params);
                if ($this->dbType === 'pgsql') {
                    $this->connection->executeStatement("SET NAMES 'UTF8'");
                }
            } catch (\Exception $e) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            }
        }
        return $this->connection;
    }

    public function close(): void {
        if ($this->connection !== null) {
            try {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();
                }
            } catch (\Exception $e) {
                error_log("Error during transaction cleanup: " . $e->getMessage());
            }
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function beginTransaction(): void {
        $this->connect()->beginTransaction();
    }

    public function commit(): void {
        $this->connect()->commit();
    }

    public function rollBack(): void {
        if ($this->connect()->isTransactionActive()) {
            $this->connect()->rollBack();
        }
    }

    public function inTransaction(): bool {
        return $this->connect()->isTransactionActive();
    }

    /**
     * プリペアドステートメントの実行（後方互換：PDOStatementを返す）
     */
    public function execute($query, $params = []) {
        try {
            $pdo  = $this->connect()->getNativeConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\Exception $e) {
            throw new Exception("クエリ実行エラー: " . $e->getMessage() . " SQL: " . $query);
        }
    }

    /**
     * SQLクエリを実行して結果を配列で返す
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->execute($query, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new Exception("クエリ実行エラー: " . $e->getMessage() . " SQL: " . $query);
        }
    }

    /**
     * 識別子（テーブル名・カラム名）をDBに応じてクォート
     * MySQL: `name` / PostgreSQL: "name"
     */
    public function quoteIdentifier(string $name): string {
        return $this->connect()->quoteIdentifier($name);
    }

    /**
     * DBALスキーママネージャーを取得
     */
    public function getSchemaManager(): AbstractSchemaManager {
        return $this->connect()->createSchemaManager();
    }

    /**
     * テーブルの存在確認
     */
    public function tableExists(string $tableName): bool {
        return $this->getSchemaManager()->tablesExist([$tableName]);
    }

    /**
     * テーブルのカラム情報を取得（後方互換：COLUMN_NAME / DATA_TYPE 形式）
     */
    public function getTableColumns($tableName): array {
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return [];
            }
            $result = [];
            foreach ($sm->listTableColumns($tableName) as $name => $col) {
                $result[] = [
                    'COLUMN_NAME'    => $name,
                    'DATA_TYPE'      => $col->getType()->getName(),
                    'IS_NULLABLE'    => $col->getNotnull() ? 'NO' : 'YES',
                    'COLUMN_DEFAULT' => $col->getDefault(),
                ];
            }
            return $result;
        } catch (\Exception $e) {
            throw new Exception("カラム情報取得エラー: " . $e->getMessage());
        }
    }
}
?>
    private $connection;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $dbType;
    private $port;
    private $inTransaction = false;
    
    public function __construct($host, $dbname, $username, $password, $charset = 'utf8mb4', $dbType = 'mysql', $port = null) {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
        $this->dbType = $dbType;
        $this->port = $port ?: ($dbType === 'pgsql' ? 5432 : 3306);
    }
    
    /**
     * データベースタイプを取得
     * @return string
     */
    public function getDbType() {
        return $this->dbType;
    }
    
    /**
     * データベースに接続
     * @return PDO
     * @throws Exception
     */
    public function connect() {
        if ($this->connection === null) {
            try {
                // DSN生成（PostgreSQLとMySQL対応）
                if ($this->dbType === 'pgsql') {
                    $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
                } else {
                    $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
                }
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                // Google Cloud SQL用SSL設定（DB_SSL_MODE環境変数が設定されている時のみ＝Render環境）
                if ($this->dbType === 'mysql' && getenv('DB_SSL_MODE')) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
                
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
                
                // PostgreSQLの場合はUTF-8エンコーディングを設定
                if ($this->dbType === 'pgsql') {
                    $this->connection->exec("SET NAMES 'UTF8'");
                }
            } catch (PDOException $e) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    /**
     * 接続を閉じる
     */
    public function close() {
        if ($this->connection !== null) {
            // アクティブなトランザクションがあればロールバック
            try {
                if ($this->connection->inTransaction()) {
                    error_log("Warning: Active transaction found during connection close, rolling back");
                    $this->connection->rollBack();
                    $this->inTransaction = false;
                }
            } catch (Exception $e) {
                error_log("Error during transaction cleanup: " . $e->getMessage());
            }
            
            $this->connection = null;
        }
    }
    
    /**
     * トランザクション開始
     */
    public function beginTransaction() {
        $connection = $this->connect();
        if (!$connection->inTransaction()) {
            $result = $connection->beginTransaction();
            if ($result) {
                $this->inTransaction = true;
            }
            return $result;
        }
        return true; // 既にトランザクション中
    }
    
    /**
     * コミット
     */
    public function commit() {
        $connection = $this->connect();
        if ($connection->inTransaction()) {
            $result = $connection->commit();
            $this->inTransaction = false;
            return $result;
        }
        return true; // アクティブなトランザクションがない
    }
    
    /**
     * ロールバック
     */
    public function rollBack() {
        $connection = $this->connect();
        if ($connection->inTransaction()) {
            $result = $connection->rollBack();
            $this->inTransaction = false;
            return $result;
        }
        return true; // アクティブなトランザクションがない
    }
    
    /**
     * トランザクション状態を確認
     */
    public function inTransaction() {
        return $this->connect()->inTransaction();
    }
    
    /**
     * プリペアドステートメントの実行
     * @param string $query
     * @param array $params
     * @return PDOStatement
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->connect()->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("クエリ実行エラー: " . $e->getMessage() . " SQL: " . $query);
        }
    }
    
    /**
     * SQLクエリを実行して結果を配列で返す
     * @param string $query
     * @param array $params
     * @return array
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connect()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("クエリ実行エラー: " . $e->getMessage() . " SQL: " . $query);
        }
    }

    /**
     * テーブルの存在確認
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->execute(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                [$this->dbname, $tableName]
            );
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            throw new Exception("テーブル存在確認エラー: " . $e->getMessage());
        }
    }
    
    /**
     * テーブルのカラム情報を取得
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName) {
        try {
            $stmt = $this->execute(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                 FROM information_schema.columns 
                 WHERE table_schema = ? AND table_name = ? 
                 ORDER BY ORDINAL_POSITION",
                [$this->dbname, $tableName]
            );
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception("カラム情報取得エラー: " . $e->getMessage());
        }
    }
}
?>