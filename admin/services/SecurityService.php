<?php
namespace Admin\Services;

use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Libs\Exception\SecurityException;
use Libs\SecurityManager;
use Admin\Services\OperationLog;

class SecurityService
{
    /**
     * 验证请求签名
     */
    public function verifyRequestSignature(string $signature, string $timestamp, string $nonce, string $payload): bool
    {
        // 验证时间戳有效性（防止重放攻击）
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        // 验证nonce唯一性
        if (!SecurityManager::verifyNonce($nonce)) {
            return false;
        }

        // 生成预期签名
        $expected = hash_hmac('sha256', 
            $timestamp.$nonce.$payload, 
            CryptoHelper::decrypt(getenv('API_SIGNATURE_KEY'))
        );

        return hash_equals($expected, $signature);
    }
    const THREAT_API = 'https://api.threatintel.com/v3/feed';
    const MODEL_ENDPOINT = 'http://localhost:8501/v1/models/ai_firewall:predict';

    public function enableWAF($config) {
        // 深度防御规则加载
        $this->loadL3DefenseRules();
        
        OperationLog::log(
            $_SESSION['admin_id'],
            '安全系统',
            ['action' => 'WAF启用', 'config' => $config]
        );
    }

    private function loadL3DefenseRules() {
        // 加载L3级防御规则库
        $rules = DatabaseHelper::getInstance()->query(
            "SELECT * FROM defense_rules WHERE level = 3 ORDER BY priority DESC"
        );
        
        SecurityAuditHelper::audit('defense_rules', 
            '加载深度防御规则'.count($rules).'条'
        );
    }

    public function detectIntrusion($payload) {
        // 多维度攻击特征检测
        $patternAnalysis = $this->patternMatch($payload);
        $behaviorAnalysis = $this->analyzeBehavior($payload);
        $modelPrediction = $this->deepLearningAnalyze($payload);

        return $patternAnalysis['score'] > 90 || 
               $behaviorAnalysis['risk'] === 'high' ||
               $modelPrediction['malicious'] > 0.85;
    }

    private function deepLearningAnalyze($payload) {
        // 调用AI防火墙模型
        $ch = curl_init(self::MODEL_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: '.getenv('AI_FIREWALL_KEY')
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['instances' => [$payload]]),
            CURLOPT_RETURNTRANSFER => true
        ]);

        return json_decode(curl_exec($ch), true);
    }

    public function fetchThreatIntelligence() {
        // 获取全球威胁情报
        $feed = file_get_contents(self::THREAT_API."?key=".getenv('THREAT_API_KEY'));
        $data = json_decode($feed, true);

        DatabaseHelper::getInstance()->batchInsert(
            'threat_intelligence',
            ['type', 'signature', 'severity'],
            array_map(function($item) {
                return [
                    $item['category'],
                    base64_encode($item['pattern']),
                    $item['risk_level']
                ];
            }, $data['results'])
        );
    }
}