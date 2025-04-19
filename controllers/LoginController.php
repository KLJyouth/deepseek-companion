<?php
namespace Controllers;

require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../libs/CryptoHelper.php';
require_once __DIR__ . '/../libs/DatabaseHelper.php';
require_once __DIR__ . '/../libs/RateLimiter.php';

use Libs\AuthMiddleware;
use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Libs\RateLimiter;
use Exception;

class LoginController {
    private $dbHelper;
    private $auth;
    private $rateLimiter;
    
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_TIME = 1800; // 30分钟

    public function __construct(DatabaseHelper $dbHelper) {
        // 验证数据库连接
        if (!$dbHelper->testConnection()) {
            throw new Exception("数据库连接异常");
        }
        
        $this->dbHelper = $dbHelper;
        
        // 初始化加密功能
        try {
            CryptoHelper::init(ENCRYPTION_KEY, ENCRYPTION_IV);
            $test = CryptoHelper::encrypt('test');
            CryptoHelper::decrypt($test);
        } catch (Exception $e) {
            throw new Exception("加密初始化失败: " . $e->getMessage());
        }
        
        // 初始化认证组件
        $this->auth = new AuthMiddleware($dbHelper);
        $this->rateLimiter = new RateLimiter($dbHelper, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCKOUT_TIME);
        
        // 记录初始化日志
        $dbHelper->logAudit('controller_init', 0, [
            'controller' => 'LoginController',
            'status' => 'success'
        ]);
    }
    
    public function login($username, $password, $remember = false, $totpCode = null) {
        // 验证CSRF令牌
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!CryptoHelper::validateCsrfToken($csrfToken)) {
            $this->logFailedAttempt($username, 'invalid_csrf');
            throw new Exception("安全验证失败，请刷新页面重试");
        }

        // 检查是否超过最大失败次数
        if ($this->isAccountLocked($username)) {
            $this->logFailedAttempt($username, 'account_locked');
            throw new Exception("账户已锁定，请" . (self::LOGIN_LOCKOUT_TIME/60) . "分钟后再试");
        }

        // 获取用户信息(包含2FA设置)
        $user = $this->dbHelper->getRow(
            "SELECT u.*, tfa.secret as tfa_secret, tfa.recovery_codes as tfa_recovery_codes
             FROM users u
             LEFT JOIN two_factor_auth tfa ON tfa.user_id = u.id
             WHERE u.username = ? AND u.status = 1",
            [['value' => $username, 'encrypt' => false]]
        );

        if (!$user) {
            $this->logFailedAttempt($username, 'user_not_found');
            throw new Exception("用户名或密码错误");
        }

        // 验证密码
        if (!CryptoHelper::verifyPassword($password, $user['password'])) {
            $this->logFailedAttempt($username, 'wrong_password', $user['id']);
            throw new Exception("用户名或密码错误");
        }

        // 检查2FA
        if (!empty($user['tfa_secret'])) {
            if (empty($totpCode)) {
                return [
                    'requires_2fa' => true,
                    'user_id' => $user['id']
                ];
            }

            $secret = CryptoHelper::decrypt($user['tfa_secret']);
            if (!$this->verifyTotp($secret, $totpCode) && !$this->useRecoveryCode($user['id'], $totpCode)) {
                $this->logFailedAttempt($username, 'invalid_2fa', $user['id']);
                throw new Exception("验证码无效");
            }
        }

        // 创建安全会话
        AuthMiddleware::secureSession();
        $this->createUserSession($user, $remember);
        
        // 记录成功登录
        $this->logSuccessfulLogin($user['id']);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ];
    }
    
    private function createUserSession($user, $remember) {
        AuthMiddleware::secureSession();
        
        $_SESSION = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'avatar' => $user['avatar'] ?? '',
            'initiated' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'last_regenerate' => time()
        ];
        
        if ($remember) {
            $this->setRememberToken($user['id']);
        }
        
        $this->dbHelper->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'is_online' => 1
        ], 'id = ?', [['value' => $user['id'], 'type' => 'i']]);
    }
    
    private function setRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
        
        $this->dbHelper->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expires
        ]);
        
        setcookie(
            'remember_token', 
            CryptoHelper::customEncode($token), 
            [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    private function isAccountLocked($username) {
        $attempts = $this->dbHelper->getRow(
            "SELECT COUNT(*) as count FROM login_attempts 
             WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
             AND success = 0",
            [
                ['value' => $username, 'encrypt' => false],
                ['value' => self::LOGIN_LOCKOUT_TIME, 'type' => 'i']
            ]
        );
        
        return $attempts['count'] >= self::MAX_LOGIN_ATTEMPTS;
    }
    
    private function logFailedAttempt($username, $reason, $userId = null) {
        $this->dbHelper->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'success' => 0,
            'reason' => $reason
        ]);
        
        if ($userId) {
            $this->dbHelper->logAudit('login_failed', $userId, [
                'reason' => $reason,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    }
    
    private function logSuccessfulLogin($userId) {
        $this->dbHelper->insert('login_attempts', [
            'username' => $_SESSION['username'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'success' => 1
        ]);
        
        $this->dbHelper->logAudit('login_success', $userId, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * 验证TOTP代码
     */
    private function verifyTotp($secret, $code) {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        // 使用Google Authenticator兼容算法
        $timestamp = floor(time() / 30);
        $expectedCodes = [];
        
        // 检查前后两个时间窗口以解决时间同步问题
        for ($i = -1; $i <= 1; $i++) {
            $time = $timestamp + $i;
            $expectedCodes[] = $this->generateTotpCode($secret, $time);
        }

        return in_array($code, $expectedCodes);
    }

    /**
     * 生成TOTP代码
     */
    private function generateTotpCode($secret, $timestamp) {
        $key = CryptoHelper::base32_decode($secret);
        $counter = pack('N*', '', $timestamp);
        $hash = hash_hmac('sha1', $counter, $key, true);
        
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset+0]) & 0x7f) << 24 ) |
            ((ord($hash[$offset+1]) & 0xff) << 16 ) |
            ((ord($hash[$offset+2]) & 0xff) << 8 ) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 使用恢复代码
     */
    private function useRecoveryCode($userId, $code) {
        // 获取用户所有未使用的恢复代码
        $codes = $this->dbHelper->getRows(
            "SELECT id, code_hash FROM recovery_codes 
             WHERE user_id = ? AND used = 0 AND (expires_at IS NULL OR expires_at > NOW())",
            [['value' => $userId, 'type' => 'i']]
        );

        foreach ($codes as $record) {
            if (password_verify($code, $record['code_hash'])) {
                // 标记为已使用
                $this->dbHelper->update(
                    'recovery_codes',
                    ['used' => 1],
                    'id = ?',
                    [['value' => $record['id'], 'type' => 'i']]
                );
                
                // 记录审计日志
                $this->dbHelper->logAudit('recovery_code_used', $userId, [
                    'code_id' => $record['id']
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!empty($_COOKIE['remember_token'])) {
            try {
                $token = CryptoHelper::customDecode($_COOKIE['remember_token']);
                $this->dbHelper->delete('remember_tokens', 'token = ?', [['value' => $token, 'encrypt' => false]]);
            } catch (Exception $e) {
                error_log("清除记住我token失败: " . $e->getMessage());
            }
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        if ($userId) {
            $this->dbHelper->update('users', ['is_online' => 0], 'id = ?', [['value' => $userId, 'type' => 'i']]);
            $this->dbHelper->logAudit('logout', $userId);
        }
        
        $this->auth->destroySession();
    }
}
