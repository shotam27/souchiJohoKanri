<?php
/**
 * Database接続管理クラス
 */
class Database {
    private $connection;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $inTransaction = false;
    
    public function __construct($host, $dbname, $username, $password, $charset = 'utf8mb4') {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
    }
    
    /**
     * データベースに接続
     * @return PDO
     * @throws Exception
     */
    public function connect() {
        if ($this->connection === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
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