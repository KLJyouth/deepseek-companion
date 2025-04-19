<?php
namespace Libs;

use Exception;
use DeepSeek\ThreatAnalyzer;

/**
 * AI驱动的动态安全管理系统
 */
class SecurityManager {
    private static $instance;
    private $threatAnalyzer;
    private $currentRiskLevel = 3; // 默认中等风险
    private $apiEndpoints = [];
    private $decoySystems = [];
    
    private function __construct() {
        $this->threatAnalyzer = new ThreatAnalyzer();
        $this->initDefaultConfig();
    }
    
    public static function getInstance(): self {
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
            $this->currentRiskLevel = $this->threatAnalyzer->getCurrentRiskLevel();
            
            // 根据风险等级调整暴露的API端点
            $this->adjustApiExposure();
            
            // 动态生成诱饵系统
            $this->generateDecoys();
            
        } catch (Exception $e) {
            error_log('安全风险评估失败: ' . $e->getMessage());
        }
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
    public function validateRequest(string $path): bool {
        // 如果是诱饵路径，记录攻击尝试
        if (isset($this->decoySystems[$path])) {
            $this->logAttackAttempt($path);
            return false;
        }
        
        // 检查请求路径是否在暴露的API中
        return in_array($path, $this->apiEndpoints['exposed']);
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
        
        // 评估设备风险
        $deviceRisk = $this->threatAnalyzer->assessDeviceRisk(
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
}