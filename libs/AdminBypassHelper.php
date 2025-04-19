<?php
namespace Libs;

require_once __DIR__ . '/DatabaseHelper.php';
use \Exception;



class AdminBypassHelper {
    private $dbHelper;
    
    public function __construct(DatabaseHelper $dbHelper) {
        $this->dbHelper = $dbHelper;
    }
    
    /**
     * 验证管理员跳过密码
     */
    public function verifyBypassPassword($password) {
        if (empty($password)) {
            return false;
        }
        
        if (!defined('ADMIN_BYPASS_HASH')) {
            throw new Exception("管理员跳过功能未配置");
        }
        
// 检查常量 ADMIN_BYPASS_HASH 是否已定义，若未定义，在前面的逻辑中已抛出异常，这里直接使用该常量
// 检查命名空间下的常量是否定义，若未定义则抛出异常，前面逻辑已处理，这里直接使用
return password_verify($password, ADMIN_BYPASS_HASH);
    }
    
    /**
     * 创建管理员会话
     */
    public function createAdminSession() {
        return [
            'user_id' => 0,
            'username' => 'admin_bypass',
            'role' => 'admin',
            'initiated' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'last_regenerate' => time()
        ];
    }
    
    /**
     * 记录管理员跳过日志
     */
    public function logBypassAttempt() {
        $this->dbHelper->insert('admin_bypass_logs', [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
