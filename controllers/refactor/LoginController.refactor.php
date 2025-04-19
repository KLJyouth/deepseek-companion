<?php
namespace Controllers\Refactor;

require_once __DIR__ . '/../../libs/AuthMiddleware.php';
require_once __DIR__ . '/../../services/LoginService.php';
require_once __DIR__ . '/../../libs/CryptoHelper.php';

use Libs\AuthMiddleware;
use Services\LoginService;
use Libs\CryptoHelper;
use Exception;

/**
 * 登录控制器重构参考实现
 * 
 * 主要改进：
 * 1. 使用LoginService处理核心业务逻辑
 * 2. 简化控制器职责
 * 3. 增强错误处理
 * 4. 保持与现有系统兼容
 */
class LoginController {
    private $dbHelper;
    private $auth;
    private $loginService;
    
    public function __construct($dbHelper) {
        $this->dbHelper = $dbHelper;
        $this->auth = new AuthMiddleware($dbHelper);
        $this->loginService = new LoginService($dbHelper);
    }
    
    /**
     * 处理登录请求
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @param bool $remember 是否记住登录状态
     * @param string|null $totpCode 二步验证码
     * @return array 登录结果
     * @throws Exception 登录失败时抛出异常
     */
    public function login($username, $password, $remember = false, $totpCode = null) {
        try {
            // 验证CSRF令牌
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!CryptoHelper::validateCsrfToken($csrfToken)) {
                $this->loginService->logFailedAttempt($username, 'invalid_csrf');
                throw new Exception("安全验证失败，请刷新页面重试");
            }

            // 调用服务处理登录
            $sessionData = $this->loginService->login($username, $password, $remember, $totpCode);
            
            // 创建会话
            $_SESSION = $sessionData;
            
            // 记住我功能
            if ($remember) {
                $this->setRememberToken($sessionData['user_id']);
            }
            
            return [
                'success' => true,
                'user' => [
                    'id' => $sessionData['user_id'],
                    'username' => $sessionData['username'],
                    'role' => $sessionData['role']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("登录控制器错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 设置记住我令牌
     */
    private function setRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
        
        $this->dbHelper->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => CryptoHelper::encrypt($token),
            'expires_at' => $expires
        ]);
        
        setcookie(
            'remember_token',
            $token,
            [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    /**
     * 注销登录
     */
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        
        // 清除记住我令牌
        if (!empty($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // 更新用户状态
        if ($userId) {
            $this->dbHelper->update('users', ['is_online' => 0], 'id = ?', [['value' => $userId, 'type' => 'i']]);
        }
        
        // 销毁会话
        $this->auth->destroySession();
    }
}

/* 
集成说明：
1. 将本文件保存为controllers/LoginController.php
2. 确保已创建services/LoginService.php
3. 更新所有调用点：
   - 保持原有方法签名不变
   - 异常处理方式不变
4. 测试要点：
   - 正常登录流程
   - 错误处理
   - 记住我功能
   - 注销功能
*/
