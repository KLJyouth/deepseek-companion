<?php
namespace Libs;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseHelper {
    private static $instance = null;
    private $connection;
    private $config;

    public function __construct() {
        $this->config = Config::getInstance()->get('database.connections.mysql');
        $this->connect();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function checkPDODriver(): void {
        if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
            throw new RuntimeException(
                "PDO MySQL扩展未加载。请确保已安装并启用以下PHP扩展:\n" .
                "- pdo\n" .
                "- pdo_mysql\n\n" .
                "在php.ini中启用这些扩展后重启web服务器。"
            );
        }
    }

    private function connect(): void {
        $this->checkPDODriver();

        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};" .
                   "dbname={$this->config['database']};charset={$this->config['charset']}";

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false
                ]
            );

            // 测试连接是否有效
            $this->connection->query("SELECT 1");
        } catch (PDOException $e) {
            throw new RuntimeException(
                "数据库连接失败:\n" .
                "- 错误信息: " . $e->getMessage() . "\n" .
                "- 请检查:\n" .
                "  1. 数据库服务是否运行\n" .
                "  2. 数据库配置是否正确\n" .
                "  3. 用户名和密码是否正确\n" .
                "  4. 数据库是否存在\n\n" .
                "当前配置:\n" .
                "- 主机: {$this->config['host']}\n" .
                "- 端口: {$this->config['port']}\n" .
                "- 数据库名: {$this->config['database']}\n" .
                "- 用户名: {$this->config['username']}"
            );
        }
    }

    public function query(string $sql, array $params = []): array {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException(
                "数据库查询失败:\n" .
                "- SQL: " . $sql . "\n" .
                "- 参数: " . json_encode($params) . "\n" .
                "- 错误: " . $e->getMessage()
            );
        }
    }

    // ... [保留其他方法]
}