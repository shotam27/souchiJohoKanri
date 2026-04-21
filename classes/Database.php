<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * Database謗･邯夂ｮ｡逅・け繝ｩ繧ｹ・・octrine DBAL菴ｿ逕ｨ・・
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
     * 迴ｾ蝨ｨ縺ｮDB縺勲ySQL縺九←縺・°繧定ｿ斐☆・・ostgreSQL縺ｪ繧映alse・・
     */
    public function isMySQL(): bool {
        return $this->connect()->getDatabasePlatform() instanceof MySQLPlatform;
    }

    /**
     * DBAL謗･邯壹ｒ蜿門ｾ暦ｼ域磁邯壽ｸ医∩縺ｧ縺ｪ縺代ｌ縺ｰ謗･邯壹☆繧具ｼ・
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

            // Google Cloud SQL逕ｨSSL險ｭ螳夲ｼ・B_SSL_MODE迺ｰ蠅・､画焚縺瑚ｨｭ螳壹＆繧後※縺・ｋ譎ゅ・縺ｿ・抒ender迺ｰ蠅・ｼ・
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
                throw new Exception("繝・・繧ｿ繝吶・繧ｹ謗･邯壹お繝ｩ繝ｼ: " . $e->getMessage());
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
     * 繝励Μ繝壹い繝峨せ繝・・繝医Γ繝ｳ繝医・螳溯｡鯉ｼ亥ｾ梧婿莠呈鋤・啀DOStatement繧定ｿ斐☆・・
     */
    public function execute($query, $params = []) {
        try {
            $pdo  = $this->connect()->getNativeConnection();
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\Exception $e) {
            throw new Exception("繧ｯ繧ｨ繝ｪ螳溯｡後お繝ｩ繝ｼ: " . $e->getMessage() . " SQL: " . $query);
        }
    }

    /**
     * SQL繧ｯ繧ｨ繝ｪ繧貞ｮ溯｡後＠縺ｦ邨先棡繧帝・蛻励〒霑斐☆
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->execute($query, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new Exception("繧ｯ繧ｨ繝ｪ螳溯｡後お繝ｩ繝ｼ: " . $e->getMessage() . " SQL: " . $query);
        }
    }

    /**
     * 隴伜挨蟄撰ｼ医ユ繝ｼ繝悶Ν蜷阪・繧ｫ繝ｩ繝蜷搾ｼ峨ｒDB縺ｫ蠢懊§縺ｦ繧ｯ繧ｩ繝ｼ繝・
     * MySQL: `name` / PostgreSQL: "name"
     */
    public function quoteIdentifier(string $name): string {
        return $this->connect()->quoteIdentifier($name);
    }

    /**
     * DBAL繧ｹ繧ｭ繝ｼ繝槭・繝阪・繧ｸ繝｣繝ｼ繧貞叙蠕・
     */
    public function getSchemaManager(): AbstractSchemaManager {
        return $this->connect()->createSchemaManager();
    }

    /**
     * 繝・・繝悶Ν縺ｮ蟄伜惠遒ｺ隱・
     */
    public function tableExists(string $tableName): bool {
        return $this->getSchemaManager()->tablesExist([$tableName]);
    }

    /**
     * 繝・・繝悶Ν縺ｮ繧ｫ繝ｩ繝諠・ｱ繧貞叙蠕暦ｼ亥ｾ梧婿莠呈鋤・咾OLUMN_NAME / DATA_TYPE 蠖｢蠑擾ｼ・
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
            throw new Exception("繧ｫ繝ｩ繝諠・ｱ蜿門ｾ励お繝ｩ繝ｼ: " . $e->getMessage());
        }
    }
}

