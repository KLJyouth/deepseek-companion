<?php
/**
 * AIDefenseService - AI防御服务
 *
 * 负责AI驱动的威胁检测、预测和防御功能
 * 该组件是DeepSeek Companion安全架构的核心组件之一
 * 
 * @package DeepSeek\Security\AI
 * @author DeepSeek Security Team
 * @copyright © 广西港妙科技有限公司
 * @version 1.0.0
 * @license Proprietary
 */

namespace DeepSeek\Security\AI;

use DeepSeek\Security\Quantum\QuantumKeyManager;
use Exception;

/**
 * AIDefenseService类 - 实现AI安全防御的核心功能
 * 
 * 该类提供了基于深度学习的威胁检测、预测和防御功能
 * 支持多种AI模型和防御策略，确保系统安全性达到国际标准
 */
class AIDefenseService
{
    /**
     * @var ThreatPredictor AI威胁预测器
     */
    private ThreatPredictor $threatPredictor;
    
    /**
     * @var array 防御策略配置
     */
    private array $defenseConfig = [];
    
    /**
     * @var array 威胁检测历史
     */
    private array $detectionHistory = [];
    
    /**
     * @var QuantumKeyManager 量子密钥管理器
     */
    private QuantumKeyManager $keyManager;
    
    /**
     * @var int 最大历史记录数
     */
    private int $maxHistorySize = 1000;
    
    /**
     * 构造函数
     * 
     * @param ThreatPredictor|null $threatPredictor 威胁预测器
     * @param QuantumKeyManager|null $keyManager 量子密钥管理器
     * @param array $config 防御配置
     */
    public function __construct(
        ?ThreatPredictor $threatPredictor = null,
        ?QuantumKeyManager $keyManager = null,
        array $config = []
    ) {
        $this->threatPredictor = $threatPredictor ?? new ThreatPredictor();
        $this->keyManager = $keyManager ?? new QuantumKeyManager();
        $this->defenseConfig = array_merge($this->getDefaultConfig(), $config);
        $this->initializeDefenseSystem();
    }
    
    /**
     * 初始化防御系统
     */
    private function initializeDefenseSystem(): void
    {
        // 确保防御系统目录存在
        $defenseDir = sys_get_temp_dir() . '/ai_defense';
        if (!is_dir($defenseDir)) {
            mkdir($defenseDir, 0700, true);
        }
        
        // 加载历史检测数据
        $this->loadDetectionHistory();
        
        // 初始化AI模型
        $this->threatPredictor->initializeModels($this->defenseConfig['model_config']);
    }
    
    /**
     * 获取默认配置
     * 
     * @return array 默认配置
     */
    private function getDefaultConfig(): array
    {
        return [
            'sensitivity_level' => 3, // 1-5，5为最敏感
            'auto_response' => true,  // 自动响应威胁
            'learning_rate' => 0.01,  // AI学习率
            'model_config' => [
                'threat_detection' => [
                    'type' => 'transformer',
                    'layers' => 12,
                    'heads' => 8,
                    'dropout' => 0.1
                ],
                'anomaly_detection' => [
                    'type' => 'autoencoder',
                    'encoding_dim' => 32,
                    'layers' => 3
                ],
                'behavior_analysis' => [
                    'type' => 'lstm',
                    'units' => 128,
                    'recurrent_dropout' => 0.2
                ]
            ],
            'defense_strategies' => [
                'adaptive_encryption' => true,
                'dynamic_firewall' => true,
                'deception_technology' => true,
                'rate_limiting' => true
            ],
            'notification_channels' => [
                'email' => true,
                'sms' => false,
                'dashboard' => true,
                'api_webhook' => false
            ]
        ];
    }
    
    /**
     * 分析请求并检测威胁
     * 
     * @param array $requestData 请求数据
     * @return array 威胁分析结果
     */
    public function analyzeRequest(array $requestData): array
    {
        // 预处理请求数据
        $normalizedData = $this->normalizeRequestData($requestData);
        
        // 使用AI模型检测威胁
        $threatAnalysis = $this->threatPredictor->detectThreats($normalizedData);
        
        // 记录检测结果
        $this->recordDetection([
            'timestamp' => time(),
            'request_hash' => hash('sha256', json_encode($requestData)),
            'threat_level' => $threatAnalysis['threat_level'],
            'threat_types' => $threatAnalysis['threat_types'],
            'confidence' => $threatAnalysis['confidence'],
            'action_taken' => null // 将在响应后更新
        ]);
        
        // 如果启用了自动响应且威胁级别超过阈值，则自动响应
        if ($this->defenseConfig['auto_response'] && 
            $threatAnalysis['threat_level'] >= $this->defenseConfig['sensitivity_level']) {
            $response = $this->respondToThreat($threatAnalysis, $requestData);
            $threatAnalysis['response'] = $response;
            
            // 更新最后一条记录的action_taken字段
            $lastIndex = count($this->detectionHistory) - 1;
            if ($lastIndex >= 0) {
                $this->detectionHistory[$lastIndex]['action_taken'] = $response['action'];
            }
        }
        
        return $threatAnalysis;
    }
    
    /**
     * 响应检测到的威胁
     * 
     * @param array $threatAnalysis 威胁分析结果
     * @param array $requestData 原始请求数据
     * @return array 响应结果
     */
    public function respondToThreat(array $threatAnalysis, array $requestData): array
    {
        $response = [
            'timestamp' => time(),
            'threat_id' => uniqid('threat_', true),
            'action' => 'monitor', // 默认只监控
            'details' => []
        ];
        
        // 根据威胁级别和类型确定响应策略
        switch (true) {
            // 严重威胁 - 阻止并升级
            case ($threatAnalysis['threat_level'] >= 4):
                $response['action'] = 'block';
                $response['details']['reason'] = '检测到严重安全威胁';
                $response['details']['escalation'] = true;
                
                // 增强加密策略
                if ($this->defenseConfig['defense_strategies']['adaptive_encryption']) {
                    $this->enhanceEncryption();
                    $response['details']['encryption_enhanced'] = true;
                }
                break;
                
            // 中等威胁 - 挑战或限制
            case ($threatAnalysis['threat_level'] >= 3):
                $response['action'] = 'challenge';
                $response['details']['reason'] = '检测到可疑活动';
                $response['details']['rate_limited'] = true;
                
                // 应用速率限制
                if ($this->defenseConfig['defense_strategies']['rate_limiting']) {
                    $response['details']['rate_limit'] = $this->applyRateLimit($requestData);
                }
                break;
                
            // 低等威胁 - 监控并记录
            default:
                $response['action'] = 'monitor';
                $response['details']['reason'] = '检测到异常但非紧急活动';
                $response['details']['enhanced_logging'] = true;
                break;
        }
        
        // 发送通知
        $this->sendNotifications($threatAnalysis, $response);
        
        // 更新AI模型
        $this->updateDefenseModel($threatAnalysis, $response);
        
        return $response;
    }
    
    /**
     * 增强加密策略
     * 
     * @return bool 操作是否成功
     */
    private function enhanceEncryption(): bool
    {
        try {
            // 轮换加密密钥
            $this->keyManager->rotateAllKeys();
            
            // 减少密钥有效期
            $currentLifetime = $this->keyManager->getKeyLifetime();
            $newLifetime = max(3600, intval($currentLifetime * 0.5)); // 至少1小时，最多减半
            $this->keyManager->setKeyLifetime($newLifetime);
            
            return true;
        } catch (Exception $e) {
            // 记录错误但不中断操作
            error_log('增强加密失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 应用速率限制
     * 
     * @param array $requestData 请求数据
     * @return array 速率限制详情
     */
    private function applyRateLimit(array $requestData): array
    {
        // 提取请求源信息
        $source = $requestData['source'] ?? [
            'ip' => $requestData['ip'] ?? '0.0.0.0',
            'user_agent' => $requestData['user_agent'] ?? 'unknown',
            'user_id' => $requestData['user_id'] ?? null
        ];
        
        // 计算限制参数
        $limitDetails = [
            'window_seconds' => 300, // 5分钟窗口
            'max_requests' => 30,    // 最大请求数
            'expires_at' => time() + 300,
            'source' => $source
        ];
        
        // 在实际实现中，这里应该与速率限制服务交互
        // 这里只返回计算的限制详情
        return $limitDetails;
    }
    
    /**
     * 发送威胁通知
     * 
     * @param array $threatAnalysis 威胁分析
     * @param array $response 响应详情
     */
    private function sendNotifications(array $threatAnalysis, array $response): void
    {
        $channels = $this->defenseConfig['notification_channels'];
        $notificationData = [
            'timestamp' => time(),
            'threat_level' => $threatAnalysis['threat_level'],
            'threat_types' => $threatAnalysis['threat_types'],
            'action_taken' => $response['action'],
            'details' => $response['details']
        ];
        
        // 在实际实现中，这里应该调用通知服务发送通知
        // 这里只模拟通知逻辑
        if ($channels['dashboard']) {
            // 发送到仪表板
            // dashboard_notification_service->send($notificationData);
        }
        
        if ($channels['email'] && $threatAnalysis['threat_level'] >= 3) {
            // 发送电子邮件
            // email_service->sendSecurityAlert($notificationData);
        }
        
        if ($channels['sms'] && $threatAnalysis['threat_level'] >= 4) {
            // 发送短信
            // sms_service->sendUrgentAlert($notificationData);
        }
        
        if ($channels['api_webhook']) {
            // 调用webhook
            // webhook_service->trigger('security_alert', $notificationData);
        }
    }
    
    /**
     * 更新防御模型
     * 
     * @param array $threatAnalysis 威胁分析
     * @param array $response 响应详情
     */
    private function updateDefenseModel(array $threatAnalysis, array $response): void
    {
        // 准备训练数据
        $trainingData = [
            'features' => $threatAnalysis['feature_vector'],
            'label' => [
                'threat_level' => $threatAnalysis['threat_level'],
                'threat_types' => $threatAnalysis['threat_types']
            ],
            'response_effectiveness' => null // 将在后续评估中更新
        ];
        
        // 增量更新模型
        $this->threatPredictor->updateModel($trainingData, $this->defenseConfig['learning_rate']);
    }
    
    /**
     * 标准化请求数据
     * 
     * @param array $requestData 原始请求数据
     * @return array 标准化后的数据
     */
    private function normalizeRequestData(array $requestData): array
    {
        // 确保关键字段存在
        $normalized = [
            'timestamp' => time(),
            'ip' => $requestData['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'method' => $requestData['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'),
            'uri' => $requestData['uri'] ?? ($_SERVER['REQUEST_URI'] ?? '/'),
            'user_agent' => $requestData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
            'payload' => $requestData['payload'] ?? [],
            'headers' => $requestData['headers'] ?? [],
            'session_data' => $requestData['session_data'] ?? [],
            'user_id' => $requestData['user_id'] ?? null,
            'context' => $requestData['context'] ?? []
        ];
        
        // 添加地理位置信息（如果可用）
        if (isset($requestData['geo_location'])) {
            $normalized['geo_location'] = $requestData['geo_location'];
        }
        
        // 添加设备信息（如果可用）
        if (isset($requestData['device_info'])) {
            $normalized['device_info'] = $requestData['device_info'];
        }
        
        return $normalized;
    }
    
    /**
     * 记录检测结果
     * 
     * @param array $detection 检测结果
     */
    private function recordDetection(array $detection): void
    {
        // 添加到历史记录
        $this->detectionHistory[] = $detection;
        
        // 如果超过最大历史记录数，删除最旧的记录
        if (count($this->detectionHistory) > $this->maxHistorySize) {
            array_shift($this->detectionHistory);
        }
        
        // 持久化存储检测历史
        $this->persistDetectionHistory();
    }
    
    /**
     * 持久化存储检测历史
     */
    private function persistDetectionHistory(): void
    {
        // 在实际实现中，应使用安全的存储机制
        // 这里使用简化的文件存储实现
        $historyFile = sys_get_temp_dir() . '/ai_defense/detection_history.dat';
        $encryptedData = $this->encryptData(json_encode($this->detectionHistory));
        
        file_put_contents($historyFile, $encryptedData);
        chmod($historyFile, 0600); // 设置适当的权限
    }
    
    /**
     * 加载检测历史
     */
    private function loadDetectionHistory(): void
    {
        $historyFile = sys_get_temp_dir() . '/ai_defense/detection_history.dat';
        
        if (file_exists($historyFile)) {
            $encryptedData = file_get_contents($historyFile);
            $data = $this->decryptData($encryptedData);
            
            if ($data) {
                $this->detectionHistory = json_decode($data, true) ?: [];
            }
        }
    }
    
    /**
     * 加密数据
     * 
     * @param string $data 原始数据
     * @return string 加密后的数据
     */
    private function encryptData(string $data): string
    {
        // 获取或创建加密密钥
        $encryptionKey = $this->keyManager->getOrCreateEncryptionKey('CRYSTALS-Kyber-768');
        
        // 在实际实现中，应使用量子安全加密
        // 这里使用AES-256-GCM作为示例
        $iv = random_bytes(16);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', base64_decode($encryptionKey['key_data']), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        
        // 组合IV、认证标签、密钥ID和密文
        $result = [
            'key_id' => $encryptionKey['id'],
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($encrypted)
        ];
        
        return json_encode($result);
    }
    
    /**
     * 解密数据
     * 
     * @param string $encryptedData 加密数据
     * @return string|null 解密后的数据或null（如果解密失败）
     */
    private function decryptData(string $encryptedData): ?string
    {
        try {
            $data = json_decode($encryptedData, true);
            
            if (!$data || !isset($data['key_id'], $data['iv'], $data['tag'], $data['data'])) {
                return null;
            }
            
            // 获取解密密钥
            $decryptionKey = $this->keyManager->getDecryptionKey($data['key_id']);
            
            // 解密数据
            $iv = base64_decode($data['iv']);
            $tag = base64_decode($data['tag']);
            $ciphertext = base64_decode($data['data']);
            
            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                base64_decode($decryptionKey['key_data']),
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            return $decrypted ?: null;
        } catch (Exception $e) {
            error_log('解密数据失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取威胁检测历史
     * 
     * @param int $limit 限制返回记录数
     * @param array $filters 过滤条件
     * @return array 检测历史
     */
    public function getDetectionHistory(int $limit = 100, array $filters = []): array
    {
        // 应用过滤器
        $filteredHistory = $this->detectionHistory;
        
        // 按威胁级别过滤
        if (isset($filters['min_threat_level'])) {
            $filteredHistory = array_filter($filteredHistory, function($item) use ($filters) {
                return $item['threat_level'] >= $filters['min_threat_level'];
            });
        }
        
        // 按时间范围过滤
        if (isset($filters['start_time'])) {
            $filteredHistory = array_filter($filteredHistory, function($item) use ($filters) {
                return $item['timestamp'] >= $filters['start_time'];
            });
        }
        
        if (isset($filters['end_time'])) {
            $filteredHistory = array_filter($filteredHistory, function($item) use ($filters) {
                return $item['timestamp'] <= $filters['end_time'];
            });
        }
        
        // 按威胁类型过滤
        if (isset($filters['threat_type'])) {
            $filteredHistory = array_filter($filteredHistory, function($item) use ($filters) {
                return in_array($filters['threat_type'], $item['threat_types']);
            });
        }
        
        // 限制记录数并返回最新的记录
        return array_slice(array_values($filteredHistory), -$limit);
    }
    
    /**
     * 获取威胁统计信息
     * 
     * @param int $timeRange 时间范围（秒）
     * @return array 统计信息
     */
    public function getThreatStatistics(int $timeRange = 86400): array
    {
        $startTime = time() - $timeRange;
        $relevantHistory = array_filter($this->detectionHistory, function($item) use ($startTime) {
            return $item['timestamp'] >= $startTime;
        });
        
        // 初始化统计数据
        $stats = [
            'total_detections' => count($relevantHistory),
            'threat_levels' => [
                1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0
            ],
            'threat_types' => [],
            'actions_taken' => [
                'monitor' => 0,
                'challenge' => 0,
                'block' => 0,
                'other' => 0
            ],
            'hourly_distribution' => array_fill(0, 24, 0)
        ];
        
        // 计算统计数据
        foreach ($relevantHistory as $detection) {
            // 威胁级别统计
            $level = $detection['threat_level'];
            if (isset($stats['threat_levels'][$level])) {
                $stats['threat_levels'][$level]++;
            }
            
            // 威胁类型统计
            foreach ($detection['threat_types'] as $type) {
                if (!isset($stats['threat_types'][$type])) {
                    $stats['threat_types'][$type] = 0;
                }
                $stats['threat_types'][$type]++;
            }
            
            // 响应动作统计
            $action = $detection['action_taken'] ?? 'monitor';
            if (isset($stats['actions_taken'][$action])) {
                $stats['actions_taken'][$action]++;
            } else {
                $stats['actions_taken']['other']++;
            }
            
            // 小时分布统计
            $hour = (int)date('G', $detection['timestamp']);
            $stats['hourly_distribution'][$hour]++;
        }
        
        return $stats;
    }
    
    /**
     * 设置防御配置
     * 
     * @param array $config 新配置
     * @return self
     */
    public function setDefenseConfig(array $config): self
    {
        $this->defenseConfig = array_merge($this->defenseConfig, $config);
        return $this;
    }
    
    /**
     * 获取当前防御配置
     * 
     * @return array 当前配置
     */
    public function getDefenseConfig(): array
    {
        return $this->defenseConfig;
    }
    
    /**
     * 获取威胁预测器
     * 
     * @return ThreatPredictor
     */
    public function getThreatPredictor(): ThreatPredictor
    {
        return $this->threatPredictor;
    }
}