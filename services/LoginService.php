<?php
namespace Services;

require_once __DIR__ . '/../libs/DatabaseHelper.php';
require_once __DIR__ . '/../libs/CryptoHelper.php';
require_once __DIR__ . '/../libs/RateLimiter.php';

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Libs\RateLimiter;
use Exception;

class LoginService {
    private $dbHelper;
    private $rateLimiter;
    
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_TIME = 1800; // 30分钟

    public function __construct(DatabaseHelper $dbHelper) {
        $this->dbHelper = $dbHelper;
        $this->rateLimiter = new RateLimiter($dbHelper, self::MAX_ATTEMPTS, self::LOCKOUT_TIME);
    }

    /**
     * 处理用户登录
     */
    public function login($username, $password, $remember = false, $totpCode = null) {
        // 验证输入
        if (empty($username) || empty($password)) {
            throw new Exception("用户名和密码不能为空");
        }

        // 检查账户锁定
        if ($this->isAccountLocked($username)) {
            $lockTime = $this->getRemainingLockTime($username);
            throw new Exception("账户已锁定，请{$lockTime}分钟后再试");
        }

        // 获取用户数据
        $user = $this->getUserByUsername($username);
        if (!$user) {
            throw new Exception("用户名或密码错误");
        }

        // 验证密码
        if (!CryptoHelper::verifyPassword($password, $user['password'])) {
            $this->logFailedAttempt($username, 'wrong_password', $user['id']);
            throw new Exception("用户名或密码错误");
        }

        // 创建会话
        return $this->createUserSession($user, $remember);
    }

    /**
     * 获取用户信息
     */
    private function getUserByUsername($username) {
        return $this->dbHelper->getRow(
            "SELECT * FROM users WHERE username = ?",
            [['value' => $username, 'encrypt' => false]]
        );
    }

    /**
     * 检查账户锁定状态
     */
    private function isAccountLocked($username) {
        $attempts = $this->dbHelper->getRow(
            "SELECT COUNT(*) as count FROM login_attempts 
             WHERE username = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
             AND reason NOT IN ('invalid_csrf')",
            [
                ['value' => $username, 'encrypt' => false],
                ['value' => self::LOCKOUT_TIME, 'type' => 'i']
            ]
        );
        return $attempts['count'] >= self::MAX_ATTEMPTS;
    }

    /**
     * 获取剩余锁定时间(分钟)
     */
    private function getRemainingLockTime($username) {
        $lastAttempt = $this->getLastAttemptTime($username);
        return ceil((self::LOCKOUT_TIME - (time() - $lastAttempt)) / 60);
    }

    /**
     * 记录失败尝试
     */
    public function logFailedAttempt($username, $reason, $userId = null) {
        $logData = [
            'username' => $username,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $this->dbHelper->insert('login_attempts', $logData);
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
        
        if ($userId) {
            $this->dbHelper->logAudit('login_failed', $userId, [
                'reason' => $reason,
                'ip' => $logData['ip_address']
            ]);
        }
    }

    /**
     * 创建用户会话
     */
    private function createUserSession($user, $remember) {
        $sessionData = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'initiated' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'last_regenerate' => time()
        ];

        // 更新最后登录时间
        $this->dbHelper->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'is_online' => 1
        ], 'id = ?', [['value' => $user['id'], 'type' => 'i']]);

        return $sessionData;
    }
}
