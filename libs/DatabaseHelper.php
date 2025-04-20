<?php
declare(strict_types=1);

namespace Libs;

require_once __DIR__ . '/CryptoHelper.php';
use Libs\CryptoHelper;
use \Exception;
use \Throwable;

/**
 * 安全数据库操作类
 */
final class DatabaseHelper {
    private static ?self $instance = null;
    private string $tablePrefix;
    private \mysqli $conn;

    private function __construct(\mysqli $conn = null, string $prefix = 'ac_') {
        // 初始化数据库连接
        if ($conn) {
            $this->conn = $conn;
        } else {
            $this->conn = new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        }
        if ($this->conn->connect_error) {
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset(DB_CHARSET);
        $this->tablePrefix = defined('DB_TABLE_PREFIX') ? DB_TABLE_PREFIX : $prefix;
    }

    public static function setTablePrefix(string $prefix): void {
        // 仅用于兼容性，建议用实例属性
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    // 新增数据库修复功能
    public function addRepairProcedure() {
        $procedureSQL = <<<SQL
CREATE PROCEDURE ac_self_healing()
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    -- 修复表结构
    CALL ac_repair_table_structure();
    -- 数据一致性校验
    CALL ac_data_consistency_check();
    COMMIT;
END
SQL;
        $this->conn->query($procedureSQL);
    }

    // 版本回滚功能
    public function versionRollback($version) {
        $this->conn->autocommit(FALSE);
        try {
            $this->conn->query("SELECT * FROM schema_versions WHERE version = '$version' FOR UPDATE");
            $this->conn->query("LOAD DATA INFILE 'backup_$version.sql' INTO TABLE schema_versions");
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new \Exception("版本回滚失败: ".$e->getMessage());
        }
    }

    // 执行修复操作
    public function executeRepair($level) {
        $this->conn->query("CALL ac_self_healing_level$level()");
        error_log('数据库修复: ' . json_encode(['level' => $level, 'status' => 'completed']));
    }
    
    /**
     * 新增深度修复方法
     */
    public function deepRepair($strategy = 'adaptive') {
        $this->conn->query("SET GLOBAL innodb_force_recovery = 6");
        $this->executeRepair(3);
        $this->conn->query("ANALYZE TABLE ac_critical_tables");
        error_log('执行L3级深度修复');
    }

    /**
     * 安全查询方法
     */
    public function secureQuery($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("预处理失败: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                $types = '';
                $values = [];
                $bind_params = [];
                
                foreach ($params as $param) {
                    if (isset($param['encrypt']) && $param['encrypt']) {
                        $value = CryptoHelper::encrypt($param['value']);
                    } else {
                        $value = $param['value'];
                    }
                    
                    $types .= $param['type'] ?? 's';
                    $values[] = $value;
                    $bind_params[] = &$values[count($values) - 1];
                }
                
                array_unshift($bind_params, $types);
                call_user_func_array([$stmt, 'bind_param'], $bind_params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("SQL执行失败: " . $stmt->error);
            }
            
            return $stmt;
        } catch (Exception $e) {
            error_log("数据库查询错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取单条记录(自动解密)
     */
    public function getRow($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        return $this->decryptRow($row);
    }
    
    /**
     * 获取多条记录(自动解密) 
     */
    public function getRows($sql, $params = []) {
        $stmt = $this->secureQuery($sql, $params);
        $result = $stmt->get_result();
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $this->decryptRow($row);
        }
        
        return $rows;
    }
    
    /**
     * 插入记录(自动加密敏感字段)
     */
    public function insert($table, $data) {
        $columns = [];
        $placeholders = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $columns[] = "`{$column}`";
            $placeholders[] = "?";
            
            // 检查是否需要加密
            $encrypt = $this->isSensitiveColumn($table, $column);
            $params[] = [
                'value' => $value,
                'encrypt' => $encrypt
            ];
        }
        
        $sql = "INSERT INTO `{$this->tablePrefix}{$table}` (" . implode(',', $columns) . ") 
                VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->secureQuery($sql, $params);
        return $this->conn->insert_id;
    }
    
    /**
     * 更新记录(自动加密敏感字段)
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $encrypt = $this->isSensitiveColumn($table, $column);
            $setClause[] = "`{$column}` = ?";
            $params[] = [
                'value' => $value,
                'encrypt' => $encrypt
            ];
        }
        
        // 添加WHERE条件参数
        foreach ($whereParams as $param) {
            $params[] = $param;
        }
        
        $sql = "UPDATE `{$this->tablePrefix}{$table}` SET " . implode(',', $setClause) . " WHERE {$where}";
        $stmt = $this->secureQuery($sql, $params);
        return $stmt->affected_rows;
    }
    
    /**
     * 记录安全审计日志
     */
    public function logAudit($action, $userId, $details = null) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        
        $sql = "INSERT INTO `{$this->tablePrefix}audit_logs` 
                (action, user_id, ip_address, user_agent, details, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $this->secureQuery($sql, [
            ['value' => $action, 'type' => 's'],
            ['value' => $userId, 'type' => 'i'],
            ['value' => $ip, 'type' => 's'],
            ['value' => $userAgent, 'type' => 's'],
            ['value' => json_encode($details), 'type' => 's']
        ]);
    }
    
    /**
     * 解密行数据
     */
    private function decryptRow($row) {
        if (!$row) return $row;
        
        // 获取表结构信息
        $table = '';
        if (isset($row['_table'])) {
            $table = $row['_table'];
            unset($row['_table']);
        }
         
        foreach ($row as $column => &$value) {
            if ($value === null) continue;
            
            // 检查是否需要解密
            if ($this->isSensitiveColumn($table, $column)) {
                try {
                    $value = CryptoHelper::decrypt($value);
                } catch (Exception $e) {
                    // 解密失败保持原值
                    error_log("解密失败: " . $e->getMessage());
                }
            }
        }
        
        return $row;
    }
    
    /**
     * 判断列是否包含敏感数据
     */
    private function isSensitiveColumn($table, $column) {
        // 定义需要加密的字段
        $sensitiveColumns = [
            'users' => ['password', 'email', 'remember_token', 'tfa_secret', 'biometric_data'],
            'ac_users' => ['password', 'email', 'remember_token', 'tfa_secret', 'biometric_data'],
            'messages' => ['content'],
            'api_usage' => ['endpoint']
        ];
        // 兼容表前缀
        $tableKey = $table;
        if (strpos($table, $this->tablePrefix) === 0) {
            $tableKey = substr($table, strlen($this->tablePrefix));
        }
        return isset($sensitiveColumns[$tableKey]) && 
               in_array($column, $sensitiveColumns[$tableKey]);
    }

    /**
     * 检查用户是否启用了生物识别认证
     */
    public function isBiometricEnabled($userId) {
        $sql = "SELECT biometric_enabled FROM {$this->tablePrefix}users WHERE id = ?";
        $stmt = $this->secureQuery($sql, [
            ['value' => $userId, 'type' => 'i']
        ]);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row && $row['biometric_enabled'] == 1;
    }

    /**
     * 更新用户生物识别数据
     */
    public function updateBiometricData($userId, $data) {
        $sql = "UPDATE {$this->tablePrefix}users SET 
                biometric_data = ?, 
                biometric_enabled = 1 
                WHERE id = ?";
        
        return $this->secureQuery($sql, [
            ['value' => json_encode($data), 'type' => 's', 'encrypt' => true],
            ['value' => $userId, 'type' => 'i']
        ]);
    }

    /**
     * 更新用户密码并记录历史
     */
    public function updateUserPassword($userId, $newPassword) {
        // 获取当前密码历史
        $user = $this->getRow("SELECT password, password_history FROM {$this->tablePrefix}users WHERE id = ?", [
            ['value' => $userId, 'type' => 'i']
        ]);
        
        // 准备密码历史记录
        $history = $user['password_history'] ? json_decode($user['password_history'], true) : [];
        if (!empty($user['password'])) {
            array_unshift($history, $user['password']);
            $history = array_slice($history, 0, 5); // 保留最近5个密码
        }
        
        // 计算密码强度
        $strength = $this->calculatePasswordStrength($newPassword);
        
        // 更新密码
        return $this->update('users', [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'password_history' => json_encode($history),
            'password_strength' => $strength
        ], 'id = ?', [
            ['value' => $userId, 'type' => 'i']
        ]);
    }

    /**
     * 计算密码强度(1-5)
     */
    private function calculatePasswordStrength(string $password): int {
        $strength = 0;
        
        // 长度加分
        $length = strlen($password);
        if ($length >= 12) $strength++;
        if ($length >= 16) $strength++;
        
        // 字符类型加分
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
        
        return min($strength, 5);
    }

    /**
     * 检查密码是否在历史记录中
     */
    public function isPasswordInHistory($userId, $password) {
        $user = $this->getRow("SELECT password_history FROM {$this->tablePrefix}users WHERE id = ?", [
            ['value' => $userId, 'type' => 'i']
        ]);
        
        if (empty($user['password_history'])) {
            return false;
        }
        
        $history = json_decode($user['password_history'], true);
        foreach ($history as $oldHash) {
            if (password_verify($password, $oldHash)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 测试数据库连接是否有效
     * @return bool 返回连接状态
     */
    public function testConnection() {
        try {
            return $this->conn && $this->conn->ping();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 删除记录
     * @param string $table 表名
     * @param string $where WHERE条件
     * @param array $params 绑定参数
     * @return int 受影响的行数
     */
    public function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM `{$this->tablePrefix}{$table}` WHERE {$where}";
        $stmt = $this->secureQuery($sql, $params);
        return $stmt->affected_rows;
    }

    public function beginTransaction(): bool
    {
        $this->logger->info('Begin transaction');
        return $this->connection->begin_transaction();
    }

    public function commit(): bool 
    {
        $this->logger->info('Commit transaction');
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        $this->logger->info('Rollback transaction');
        return $this->connection->rollback();
    }

    public function transaction(callable $callback)
    {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}