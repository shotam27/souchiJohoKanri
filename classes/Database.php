<?php
/**
 * データベース接続管理クラス（PDO使用・MySQL専用）
 */
class Database {
    private ?PDO $connection = null;
    private string $host;
    private string $dbname;
    private string $username;
    private string $password;
    private string $charset;
    private int $port;

    public function __construct($host, $dbname, $username, $password, $charset = 'utf8mb4', $dbType = 'mysql', $port = null) {
        $this->host     = $host;
        $this->dbname   = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset  = $charset;
        $this->port     = (int)($port ?: 3306);
    }

    public function getDbType(): string {
        return 'mysql';
    }

    public function isMySQL(): bool {
        return true;
    }

    /**
     * PDO接続を取得（未接続であれば接続する）
     */
    public function connect(): PDO {
        if ($this->connection === null) {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Google Cloud SQL用SSL設定（DB_SSL_MODE環境変数が設定されている場合のみ）
            if (getenv('DB_SSL_MODE')) {
                $options[PDO::MYSQL_ATTR_SSL_CA]                = '/etc/ssl/certs/ca-certificates.crt';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            }
        }
        return $this->connection;
    }

    public function close(): void {
        if ($this->connection !== null) {
            try {
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
            } catch (\Exception $e) {
                error_log("Error during transaction cleanup: " . $e->getMessage());
            }
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
        if ($this->connect()->inTransaction()) {
            $this->connect()->rollBack();
        }
    }

    public function inTransaction(): bool {
        return $this->connect()->inTransaction();
    }

    /**
     * プリペアドステートメントを実行しPDOStatementを返す
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
     * 識別子（テーブル名・カラム名）をMySQLバッククォートでクォート
     */
    public function quoteIdentifier(string $name): string {
        return chr(96) . str_replace(chr(96), chr(96).chr(96), $name) . chr(96);
    }

    /**
     * テーブルの存在確認
     */
    public function tableExists(string $tableName): bool {
        try {
            $stmt = $this->connect()->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * テーブルのカラム情報を取得（COLUMN_NAME / DATA_TYPE 形式）
     */
    public function getTableColumns($tableName): array {
        try {
            if (!$this->tableExists($tableName)) {
                return [];
            }
            $stmt = $this->connect()->prepare(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?
                 ORDER BY ORDINAL_POSITION"
            );
            $stmt->execute([$tableName]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new Exception("カラム情報取得エラー: " . $e->getMessage());
        }
    }
}
