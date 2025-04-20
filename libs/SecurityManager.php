<?php
namespace Libs;

use Exception;
use Libs\DatabaseHelper;

/**
 * AI驱动的动态安全管理系统
 */
class SecurityManager {
    private static $instance = null;
    public $crypto;
    public $quantumCrypto;
    public $auditHelper;
    private $currentRiskLevel = 3; // 默认中等风险
    private $apiEndpoints = [];
    private $decoySystems = [];
    private $logger;
    
    private function __construct() {
        $this->crypto = class_exists('\Libs\CryptoHelper') ? new \Libs\CryptoHelper() : null;
        $this->quantumCrypto = class_exists('\Libs\QuantumCryptoHelper') ? new \Libs\QuantumCryptoHelper() : null;
        $this->auditHelper = class_exists('\Libs\SecurityAuditHelper') ? new \Libs\SecurityAuditHelper() : null;
        $this->initDefaultConfig();
        $this->logger = new LogHelper();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initDefaultConfig(): void {
        // 初始化API端点配置
        $this->apiEndpoints = [
            'high' => ['/api/login', '/api/payment'],
            'medium' => ['/api/profile', '/api/contacts'],
            'low' => ['/api/news', '/api/weather']
        ];
        
        // 初始化诱饵系统
        $this->decoySystems = [
            'fake_login' => [
                'path' => '/admin/login',
                'response' => 'Invalid credentials'
            ],
            'fake_db' => [
                'path' => '/db/query',
                'response' => 'Database error'
            ]
        ];
    }
    
    /**
     * 评估当前风险等级
     */
    public function assessRisk(): void {
        try {
            // 本地简单风险评估逻辑
            $this->currentRiskLevel = $this->calculateLocalRiskLevel();

            // 根据风险等级调整暴露的API端点
            $this->adjustApiExposure();
            
            // 动态生成诱饵系统
            $this->generateDecoys();
            
        } catch (Exception $e) {
            error_log('安全风险评估失败: ' . $e->getMessage());
        }
    }

    /**
     * 本地风险评估逻辑（可扩展为AI/规则/统计等）
     */
    private function calculateLocalRiskLevel(): int {
        // 示例：根据最近10分钟登录失败次数动态调整
        $db = DatabaseHelper::getInstance();
        $result = $db->getRow(
            "SELECT COUNT(*) as fail_count FROM login_attempts WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        $failCount = $result['fail_count'] ?? 0;
        if ($failCount > 20) return 5;
        if ($failCount > 10) return 4;
        if ($failCount > 5) return 3;
        if ($failCount > 2) return 2;
        return 1;
    }
    
    /**
     * 调整API暴露程度
     */
    private function adjustApiExposure(): void {
        // 高风险时只暴露必要API
        if ($this->currentRiskLevel >= 4) {
            $this->apiEndpoints['exposed'] = $this->apiEndpoints['high'];
        } 
        // 中等风险暴露中等敏感API
        elseif ($this->currentRiskLevel >= 2) {
            $this->apiEndpoints['exposed'] = array_merge(
                $this->apiEndpoints['high'],
                $this->apiEndpoints['medium']
            );
        }
        // 低风险暴露所有API
        else {
            $this->apiEndpoints['exposed'] = array_merge(
                $this->apiEndpoints['high'],
                $this->apiEndpoints['medium'],
                $this->apiEndpoints['low']
            );
        }
    }
    
    /**
     * 生成动态诱饵系统
     */
    private function generateDecoys(): void {
        // 高风险时增加更多诱饵
        if ($this->currentRiskLevel >= 4) {
            $this->decoySystems['fake_admin'] = [
                'path' => '/wp-admin',
                'response' => 'Access denied'
            ];
            $this->decoySystems['fake_config'] = [
                'path' => '/config.ini',
                'response' => 'Configuration loaded'
            ];
        }
    }
    
    /**
     * 检查请求是否合法
     */
    public function validateRequest(array $request): bool {
        $rules = [
            'sql_injection' => '/(\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b|\balter\b)/i',
            'xss' => '/<[^>]*?>|\bjavascript:|<script|\bonload\b|\bonerror\b/i',
            'path_traversal' => '/\.\.\/|\.\.\\/',
            'command_injection' => '/[;&|`]/'
        ];

        foreach ($request as $key => $value) {
            if ($this->matchesPattern($value, $rules)) {
                $this->logger->alert("检测到潜在攻击", [
                    'type' => 'request_validation',
                    'key' => $key,
                    'value' => substr($value, 0, 100)
                ]);
                return false;
            }
        }
        return true;
    }

    private function matchesPattern($value, array $rules): bool {
        if (!is_string($value)) return false;
        
        foreach ($rules as $type => $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 记录攻击尝试
     */
    private function logAttackAttempt(string $path): void {
        $logEntry = [
            'timestamp' => date('c'),
            'path' => $path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'risk_level' => $this->currentRiskLevel
        ];
        
        file_put_contents('/var/log/decoy_attacks.log', json_encode($logEntry)."\n", FILE_APPEND);
    }
    
    /**
     * 验证生物识别请求
     */
    public function validateBiometricRequest(array $requestData): bool {
        // 检查必要字段
        if (empty($requestData['biometric_token']) || 
            empty($requestData['device_id']) ||
            empty($requestData['ip_address'])) {
            return false;
        }
        
        // 评估设备风险（本地简单实现）
        $deviceRisk = $this->calculateDeviceRisk(
            $requestData['device_id'],
            $requestData['ip_address']
        );
        
        // 高风险设备拒绝生物识别
        if ($deviceRisk >= 4) {
            $this->logSecurityEvent('high_risk_biometric_attempt', [
                'device_id' => $requestData['device_id'],
                'ip_address' => $requestData['ip_address']
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * 本地设备风险评估
     */
    private function calculateDeviceRisk(string $deviceId, string $ip): int {
        // 示例：同一设备ID在短时间内多次失败则风险高
        $db = DatabaseHelper::getInstance();
        $result = $db->getRow(
            "SELECT COUNT(*) as fail_count FROM login_attempts WHERE user_agent = ? AND ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [
                ['value' => $deviceId, 'type' => 's'],
                ['value' => $ip, 'type' => 's']
            ]
        );
        $failCount = $result['fail_count'] ?? 0;
        if ($failCount > 10) return 5;
        if ($failCount > 5) return 4;
        if ($failCount > 2) return 3;
        if ($failCount > 0) return 2;
        return 1;
    }

    /**
     * 获取当前安全状态
     */
    public function getSecurityStatus(): array {
        return [
            'risk_level' => $this->currentRiskLevel,
            'exposed_apis' => count($this->apiEndpoints['exposed']),
            'active_decoys' => count($this->decoySystems),
            'last_assessment' => date('c')
        ];
    }

    /**
     * 记录安全事件
     */
    private function logSecurityEvent(string $eventType, array $data): void {
        $logEntry = [
            'timestamp' => date('c'),
            'event_type' => $eventType,
            'data' => $data,
            'risk_level' => $this->currentRiskLevel
        ];
        
        file_put_contents('/var/log/security_events.log', json_encode($logEntry)."\n", FILE_APPEND);
    }

    /**
     * 验证nonce唯一性
     * @param string $nonce 要验证的nonce值
     * @return bool 是否有效
     */
    public static function verifyNonce(string $nonce): bool {
        $db = DatabaseHelper::getInstance();
        
        // 检查nonce格式
        if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            return false;
        }
        
        // 检查是否已使用过
        $result = $db->getRows(
            "SELECT COUNT(*) as count FROM used_nonces WHERE nonce = ? AND expires_at > NOW()",
            [['value' => $nonce, 'type' => 's']]
        );
        
        if (!empty($result) && $result[0]['count'] > 0) {
            return false;
        }
        
        // 记录新nonce
        $db->insert('used_nonces', [
            'nonce' => $nonce,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600) // 1小时有效期
        ]);
        
        return true;
    }
}