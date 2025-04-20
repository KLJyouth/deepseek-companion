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
use Services\DeviceManagementService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class LoginController {
    private $dbHelper;
    private $auth;
    private $rateLimiter;
    private $deviceService;
    
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_TIME = 1800; // 30分钟

    public function __construct(DatabaseHelper $dbHelper) {
        // 验证数据库连接
        try {
            if (!$dbHelper->testConnection()) {
                throw new Exception("数据库连接异常: 数据库连接测试失败，可能是配置错误或数据库服务不可用");
            }
        } catch (Exception $e) {
            error_log("数据库连接异常: " . $e->getMessage());
            throw new Exception("数据库连接异常: " . $e->getMessage());
        }
        
        $this->dbHelper = $dbHelper;
        $this->deviceService = new DeviceManagementService($dbHelper);
        
        // 初始化加密功能
        try {
            CryptoHelper::init(ENCRYPTION_KEY, ENCRYPTION_IV);
            $test = CryptoHelper::encrypt('test');
            CryptoHelper::decrypt($test);
        } catch (Exception $e) {
            error_log("加密初始化失败: " . $e->getMessage());
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
    
    public function login(string $username, string $password, bool $remember = false, ?string $totpCode = null): array {
        // 验证CSRF令牌
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!CryptoHelper::validateCsrfToken($csrfToken)) {
            $this->logFailedAttempt($username, 'invalid_csrf');
            error_log("CSRF令牌验证失败: 用户名={$username}, IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            throw new Exception("安全验证失败，请刷新页面重试");
        }

        // 设备指纹验证
        $deviceFingerprint = $this->deviceService->generateDeviceFingerprint();
        if (!$this->deviceService->validateDeviceFingerprint($username, $deviceFingerprint)) {
            $this->deviceService->sendUnknownDeviceAlert($username);
            error_log("设备指纹验证失败: 用户名={$username}, 指纹={$deviceFingerprint}");
            throw new Exception("检测到未知设备登录，已发送确认邮件");
        }

        // 地理位置异常检测
        $location = $this->deviceService->getLocationFromIP($_SERVER['REMOTE_ADDR']);
        if ($this->deviceService->isLocationSuspicious($username, $location)) {
            $this->logFailedAttempt($username, 'suspicious_location');
            error_log("地理位置异常检测失败: 用户名={$username}, 位置=" . json_encode($location));
            throw new Exception("检测到异常登录地点，请联系管理员");
        }

        // 检查是否超过最大失败次数
        if ($this->isAccountLocked($username)) {
            $this->logFailedAttempt($username, 'account_locked');
            error_log("账户锁定: 用户名={$username}");
            throw new Exception("账户已锁定，请" . (self::LOGIN_LOCKOUT_TIME/60) . "分钟后再试");
        }

        // 获取用户信息(包含2FA设置)
        $user = $this->dbHelper->getRow(
            // 兼容ac_users和users表
            "SELECT u.*, tfa.secret as tfa_secret, tfa.recovery_codes as tfa_recovery_codes
             FROM (SELECT * FROM ac_users WHERE username = ? AND status = 1
                   UNION ALL
                   SELECT * FROM users WHERE username = ? AND status = 1) u
             LEFT JOIN two_factor_auth tfa ON tfa.user_id = u.id
             LIMIT 1",
            [
                ['value' => $username, 'encrypt' => false],
                ['value' => $username, 'encrypt' => false]
            ]
        );

        if (!$user) {
            $this->logFailedAttempt($username, 'user_not_found');
            error_log("用户不存在: 用户名={$username}");
            throw new Exception("用户名或密码错误");
        }

        // 验证密码
        if (!CryptoHelper::verifyPassword($password, $user['password'])) {
            $this->logFailedAttempt($username, 'wrong_password', $user['id']);
            error_log("密码验证失败: 用户名={$username}, 用户ID={$user['id']}");
            throw new Exception("用户名或密码错误");
        }

        // 检查密码是否过期(90天)
        $lastChanged = strtotime($user['password_changed_at'] ?? '2000-01-01');
        if (time() - $lastChanged > 7776000) { // 90天
            error_log("密码过期: 用户名={$username}, 用户ID={$user['id']}");
            throw new Exception("密码已过期，请修改密码");
        }

        // 检查密码强度并更新
        try {
            $strength = $this->dbHelper->calculatePasswordStrength($user['password']);
            if (empty($user['password_strength']) || $user['password_strength'] != $strength) {
                $this->dbHelper->update('users', 
                    ['password_strength' => $strength],
                    'id = ?',
                    [['value' => $user['id'], 'type' => 'i']]
                );
                error_log("密码强度更新: 用户ID={$user['id']}, 新强度={$strength}");
            }
        } catch (\Throwable $e) {
            error_log("密码强度检查异常: " . $e->getMessage());
        }

        // 记录登录成功
        $this->dbHelper->update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [['value' => $user['id'], 'type' => 'i']]
        );

        // 多因素认证检查
        $request = $_POST; // 修复未定义变量
        if (!empty($user['tfa_secret']) || !empty($user['biometric_data'])) {
            if (empty($totpCode) && empty($request['biometric_token'])) {
                error_log("2FA或生物识别要求: 用户ID={$user['id']}");
                return [
                    'requires_2fa' => true,
                    'supports_biometric' => !empty($user['biometric_data']),
                    'user_id' => $user['id']
                ];
            }

            // 优先验证生物识别
            if (!empty($user['biometric_data']) && !empty($request['biometric_token'])) {
                try {
                    $biometricData = CryptoHelper::decrypt($user['biometric_data']);
                    if (!$this->verifyBiometric($biometricData, $request['biometric_token'])) {
                        $this->logFailedAttempt($username, 'invalid_biometric', $user['id']);
                        error_log("生物识别验证失败: 用户ID={$user['id']}");
                        throw new Exception("生物识别验证失败");
                    }
                } catch (Exception $e) {
                    error_log("生物识别验证异常: " . $e->getMessage());
                    throw $e;
                }
            }
            // 其次验证2FA
            elseif (!empty($user['tfa_secret'])) {
                try {
                    $secret = CryptoHelper::decrypt($user['tfa_secret']);
                    if (!$this->verifyTotp($secret, $totpCode) && !$this->useRecoveryCode($user['id'], $totpCode)) {
                        $this->logFailedAttempt($username, 'invalid_2fa', $user['id']);
                        error_log("2FA验证码无效: 用户ID={$user['id']}");
                        throw new Exception("验证码无效");
                    }
                } catch (Exception $e) {
                    error_log("2FA验证异常: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        // 创建安全会话
        try {
            AuthMiddleware::secureSession();
            $this->createUserSession($user, $remember);
        } catch (Exception $e) {
            error_log("会话创建失败: " . $e->getMessage());
            throw new Exception("会话创建失败: " . $e->getMessage());
        }
        
        // 记录成功登录
        $this->logSuccessfulLogin($user['id']);
        
        // 登录成功后生成JWT
        $jwtToken = $this->generateJwtToken($user['id'], $user['username'], $user['role']);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ],
            'jwt_token' => $jwtToken
        ];
    }
    
    public function generateJwtToken($userId, $username, $role) {
        $payload = [
            'sub' => $userId,
            'username' => $username,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRE_TIME
        ];
        return JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
    }

    private function createUserSession($user, $remember) {
        try {
            AuthMiddleware::secureSession();
            // 避免覆盖$_SESSION，逐项赋值
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'] ?? '';
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $_SESSION['last_regenerate'] = time();
            session_regenerate_id(true);

            if ($remember) {
                $this->setRememberToken($user['id']);
            }

            $this->dbHelper->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'is_online' => 1
            ], 'id = ?', [['value' => $user['id'], 'type' => 'i']]);
        } catch (Exception $e) {
            error_log("用户会话创建失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function setRememberToken($userId) {
        try {
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
        } catch (Exception $e) {
            error_log("记住我功能设置失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function isAccountLocked($username) {
        try {
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
        } catch (Exception $e) {
            error_log("账户锁定检查异常: " . $e->getMessage());
            return false;
        }
    }
    
    private function logFailedAttempt($username, $reason, $userId = null) {
        try {
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
        } catch (Exception $e) {
            error_log("登录失败尝试记录异常: " . $e->getMessage());
        }
    }
    
    private function logSuccessfulLogin($userId) {
        try {
            $this->dbHelper->insert('login_attempts', [
                'username' => $_SESSION['username'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'success' => 1
            ]);
            
            $this->dbHelper->logAudit('login_success', $userId, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("登录成功记录异常: " . $e->getMessage());
        }
    }

    /**
     * 验证TOTP代码
     */
    private function verifyTotp($secret, $code) {
        try {
            if (strlen($code) !== 6 || !ctype_digit($code)) {
                error_log("TOTP格式错误: code={$code}");
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

            $result = in_array($code, $expectedCodes);
            if (!$result) {
                error_log("TOTP验证失败: code={$code}, expected=" . implode(',', $expectedCodes));
            }
            return $result;
        } catch (Exception $e) {
            error_log("TOTP验证异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 生成TOTP代码
     */
    private function generateTotpCode($secret, $timestamp) {
        $key = CryptoHelper::base32_decode($secret);
        // 8字节大端序
        $counter = pack('N*', 0, $timestamp);
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
        try {
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
            
            error_log("恢复代码无效: 用户ID={$userId}");
            return false;
        } catch (Exception $e) {
            error_log("恢复代码使用异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证生物识别令牌
     */
    private function verifyBiometric($storedData, $token) {
        try {
            // 解码存储的生物识别数据
            $data = json_decode($storedData, true);
            if (!$data || empty($data['publicKey']) || empty($data['algorithm'])) {
                error_log("生物识别数据无效");
                return false;
            }

            // 使用公钥验证签名
            $publicKey = openssl_pkey_get_public($data['publicKey']);
            $verified = openssl_verify(
                $data['challenge'],
                base64_decode($token),
                $publicKey,
                $data['algorithm']
            );
            
            // openssl_free_key已弃用，自动释放
            return $verified === 1;
        } catch (Exception $e) {
            error_log("生物识别验证错误: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证密码强度
     */
    private function validatePasswordStrength(string $password): bool {
        try {
            // 至少12个字符
            if (strlen($password) < 12) {
                error_log("密码强度不足: 长度小于12");
                return false;
            }
            
            // 必须包含大写字母
            if (!preg_match('/[A-Z]/', $password)) {
                error_log("密码强度不足: 缺少大写字母");
                return false;
            }
            
            // 必须包含小写字母
            if (!preg_match('/[a-z]/', $password)) {
                error_log("密码强度不足: 缺少小写字母");
                return false;
            }
            
            // 必须包含数字
            if (!preg_match('/[0-9]/', $password)) {
                error_log("密码强度不足: 缺少数字");
                return false;
            }
            
            // 必须包含特殊字符
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                error_log("密码强度不足: 缺少特殊字符");
                return false;
            }
            
            // 检查常见弱密码
            $commonPasswords = ['password', '123456', 'qwerty', 'admin'];
            if (in_array(strtolower($password), $commonPasswords)) {
                error_log("密码强度不足: 常见弱密码");
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("密码强度验证异常: " . $e->getMessage());
            return false;
        }
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
            try {
                $this->dbHelper->update('users', ['is_online' => 0], 'id = ?', [['value' => $userId, 'type' => 'i']]);
                $this->dbHelper->logAudit('logout', $userId);
            } catch (Exception $e) {
                error_log("登出时更新用户状态失败: " . $e->getMessage());
            }
        }
        
        try {
            $this->auth->destroySession();
        } catch (Exception $e) {
            error_log("销毁会话失败: " . $e->getMessage());
        }
    }
}