<?php
namespace Libs;

require_once __DIR__ . '/../libs/CryptoHelper.php';
use \Exception;

/**
 * 认证中间件
 * 处理用户认证和权限检查
 */
class AuthMiddleware {
    private $dbHelper;
    
    public function __construct($dbHelper) {
        $this->dbHelper = $dbHelper;
    }

    /**
     * 静态方法检查登录状态
     * @return bool 是否已登录
     */
    public static function checkAuth(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['user_id']);
    }

    /**
     * 安全会话设置
     * 包含会话安全配置和验证
     */
    public static function secureSession(): void {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        
        session_name('AIC_SESSID');
        session_start();
        
        // 防止会话固定
        if (empty($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            $_SESSION['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        }
        
        // 定期更新会话ID
        if (!isset($_SESSION['last_regenerate']) || 
            time() - $_SESSION['last_regenerate'] > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regenerate'] = time();
        }
        
        // 验证会话安全性
        $currentIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $currentAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        if ($_SESSION['ip_address'] !== $currentIp ||
            $_SESSION['user_agent'] !== $currentAgent) {
            session_unset();
            session_destroy();
            throw new Exception("会话安全验证失败");
        }
    }
    
    /**
     * 验证用户登录状态
     */
    public function authenticate() {
        // 验证请求签名
        if (!empty($_SERVER['HTTP_X_SIGNATURE'])) {
            $this->verifyRequestSignature();
        }
        
        // 检查管理员跳过密码
        if (!empty($_POST['admin_bypass'])) {
            $bypassHash = defined('ADMIN_BYPASS_HASH') ? ADMIN_BYPASS_HASH : '';
            if (!empty($bypassHash) && CryptoHelper::verifyPassword($_POST['admin_bypass'], $bypassHash)) {
                $_SESSION['user_id'] = 0;
                $_SESSION['username'] = 'admin_bypass';
                $_SESSION['role'] = 'admin';
                $_SESSION['initiated'] = true;
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $_SESSION['last_regenerate'] = time();
                
                $this->dbHelper->logAudit('admin_bypass_used', null, [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                return;
            }
        }

        // 启动安全会话
        self::secureSession();
        
        // 检查会话中的登录状态
        if (empty($_SESSION['user_id'])) {
            // 检查记住我cookie
            if (!$this->checkRememberToken()) {
                $this->redirectToLogin();
            }
        }
        
        // 验证会话安全性
        $this->validateSession();
        
        // 检查账户状态
        $this->checkAccountStatus();
        
        // 定期更新会话
        $this->rotateSession();
    }
    
    /**
     * 检查权限
     */
    public function authorize($requiredRole = 'user') {
        $this->authenticate();
        
        // 获取当前用户角色
        $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
        
        // 角色权限检查
        // 从数据库加载权限策略
        $permissionPolicy = $this->dbHelper->getCached('permission_policy', function() {
            return $this->dbHelper->getRow("SELECT policy FROM permission_policies WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
        }, 300);

        // 解析权限策略
        $policy = json_decode($permissionPolicy['policy'] ?? '{}', true);
        
        // 验证权限继承关系
        $isAllowed = in_array($requiredRole, $policy[$userRole] ?? []) 
            || ($policy['inheritance'][$userRole] ?? false && $this->checkInheritedPermissions($userRole, $requiredRole, $policy));
        
        if (!$isAllowed) {
            $this->dbHelper->logAudit('unauthorized_access', $_SESSION['user_id'], [
                'attempted' => $requiredRole,
                'actual' => $userRole
            ]);
            $this->denyAccess();
        }
    }
    
    /**
     * 检查记住我token
     */
    /**
     * 验证请求签名
     */
    private function verifyRequestSignature() {
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
        
        // 验证时间戳有效性
        if (empty($timestamp) || abs(time() - $timestamp) > SIGNATURE_TIMEOUT) {
            throw new SecurityException('签名已过期');
        }
        
        // 构建签名数据
        $data = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'timestamp' => $timestamp,
            'params' => $_POST
        ];
        
        // 生成预期签名
        $expectedSignature = hash_hmac('sha256', json_encode($data), SIGNATURE_KEY);
        
        if (!hash_equals($expectedSignature, $signature)) {
            $this->dbHelper->logAudit('invalid_signature', $_SESSION['user_id'] ?? null, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            throw new SecurityException('无效的请求签名');
        }
    }
    
    private function checkRememberToken() {
        if (empty($_COOKIE['remember_token'])) {
            return false;
        }
        
        try {
            // 从数据库验证token
            $token = CryptoHelper::customDecode($_COOKIE['remember_token']);
            $user = $this->dbHelper->getRow(
                "SELECT u.* FROM users u 
                 JOIN remember_tokens rt ON u.id = rt.user_id 
                 WHERE rt.token = ? AND rt.expires_at > NOW()",
                [['value' => $token, 'encrypt' => false]]
            );
            
            if ($user) {
                // 创建新会话
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'];
                $_SESSION['initiated'] = true;
                
                // 更新最后登录时间
                $this->dbHelper->update('users', [
                    'last_login' => date('Y-m-d H:i:s'),
                    'is_online' => 1
                ], 'id = ?', [['value' => $user['id'], 'type' => 'i']]);
                
                return true;
            }
        } catch (\Exception $e) {
            error_log("记住我token验证失败: " . $e->getMessage());
            SecurityAuditHelper::logSecurityEvent(
                'AUTH_FAILURE',
                $_SERVER['REMOTE_ADDR'],
                ['error' => $e->getMessage()]
            );
            throw new \Libs\Exception\SecurityException(
                '身份验证失败: ' . $e->getMessage(),
                SecurityAuditHelper::RISK_LEVELS['high'],
                $e
            );
        }
        
        // 清除无效token
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }
    
    /**
     * 验证会话安全性
     */
    private function validateSession() {
        $currentIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $currentAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        
        if ($_SESSION['ip_address'] !== $currentIp || 
            $_SESSION['user_agent'] !== $currentAgent) {
            $this->dbHelper->logAudit('session_hijack_attempt', $_SESSION['user_id'] ?? null, [
                'expected_ip' => $_SESSION['ip_address'],
                'actual_ip' => $currentIp,
                'expected_agent' => $_SESSION['user_agent'],
                'actual_agent' => $currentAgent
            ]);
            
            $this->destroySession();
            $this->redirectToLogin();
        }
    }
    
    /**
     * 检查账户状态
     */
    private function checkAccountStatus() {
        $user = $this->dbHelper->getRow(
            "SELECT status FROM users WHERE id = ?",
            [['value' => $_SESSION['user_id'], 'type' => 'i']]
        );
        
        if (empty($user) || $user['status'] != 1) {
            $this->destroySession();
            $this->redirectToLogin();
        }
    }
    
    /**
     * 定期更新会话
     */
    private function rotateSession() {
        $lastRegenerate = isset($_SESSION['last_regenerate']) ? $_SESSION['last_regenerate'] : 0;
        if (time() - $lastRegenerate > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regenerate'] = time();
        }
    }
    
    /**
     * 销毁会话
     */
    public function destroySession() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * 重定向到登录页
     */
    private function redirectToLogin() {
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect("login.php?return_url=$returnUrl");
    }
    
    /**
     * 拒绝访问
     */
    private function denyAccess() {
        header('HTTP/1.1 403 Forbidden');
        exit('无权访问此资源');
    }
    
    /**
     * 检查继承权限
     */
    private function checkInheritedPermissions(string $userRole, string $requiredRole, array $policy): bool {
        return \Libs\PermissionPolicyValidator::checkInheritanceChain(
            $policy,
            $userRole,
            $requiredRole
        );
    }
}