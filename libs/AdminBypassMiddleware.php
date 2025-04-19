<?php
namespace Libs;

require_once __DIR__ . '/DatabaseHelper.php';

use Libs\DatabaseHelper;
use Exception;

class AdminBypassMiddleware {
    private $dbHelper;
    
    public function __construct(DatabaseHelper $dbHelper) {
        $this->dbHelper = $dbHelper;
    }
    
    public function handle($next) {
        // 检查管理员跳过密码
        $adminBypass = $_POST['admin_bypass'] ?? '';
        if (!empty($adminBypass) && defined('ADMIN_BYPASS_HASH')) {
            if (password_verify($adminBypass, ADMIN_BYPASS_HASH)) {
                // 创建管理员会话
                $_SESSION = [
                    'user_id' => 0,
                    'username' => 'admin_bypass',
                    'role' => 'admin',
                    'initiated' => true,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'last_regenerate' => time()
                ];
                
                // 记录日志
                $this->dbHelper->insert('admin_bypass_logs', [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // 跳过后续中间件
                return true;
            }
        }
        
        // 继续后续中间件
        return $next();
    }
}
