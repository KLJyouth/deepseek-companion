<?php
namespace Admin\Services;

use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Libs\Exception\SecurityException;
use Libs\SecurityManager;
// 日志替代
use error_log;

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
        
        // 日志替代
        error_log('WAF启用: ' . json_encode($config));
    }

    private function loadL3DefenseRules() {
        // 加载L3级防御规则库
        $rules = DatabaseHelper::getInstance()->getRows(
            "SELECT * FROM defense_rules WHERE level = 3 ORDER BY priority DESC"
        );
        // 日志替代
        error_log('加载深度防御规则'.count($rules).'条');
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

    /**
     * 模式匹配分析
     */
    private function patternMatch($payload): array {
        // 常见攻击模式检测
        $patterns = [
            '/<script\b[^>]*>([\s\S]*?)<\/script>/i' => 'XSS',
            '/union\s+all\s+select/i' => 'SQL注入',
            '/etc\/passwd/i' => '路径遍历',
            '/<\?php\b[^>]*>([\s\S]*?)\?>/i' => 'PHP注入'
        ];

        $matches = [];
        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $payload)) {
                $matches[$type] = $pattern;
            }
        }

        return [
            'score' => count($matches) * 30,
            'matches' => $matches
        ];
    }

    /**
     * 行为分析
     */
    private function analyzeBehavior($payload): array {
        // 异常行为特征检测
        $length = strlen($payload);
        $entropy = $this->calculateEntropy($payload);
        $suspiciousFunctions = preg_match_all('/(eval|system|exec|shell_exec|passthru)/i', $payload);

        $risk = 'low';
        if ($length > 10000 || $entropy > 6.5 || $suspiciousFunctions > 0) {
            $risk = 'high';
        } elseif ($length > 5000 || $entropy > 5.5) {
            $risk = 'medium';
        }

        return [
            'risk' => $risk,
            'length' => $length,
            'entropy' => $entropy,
            'suspicious_functions' => $suspiciousFunctions
        ];
    }

    /**
     * 计算字符串熵值
     */
    private function calculateEntropy($string): float {
        $entropy = 0;
        $len = strlen($string);
        $chars = count_chars($string, 1);

        foreach ($chars as $char => $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    public function fetchThreatIntelligence() {
        // 获取全球威胁情报
        $feed = file_get_contents(self::THREAT_API."?key=".getenv('THREAT_API_KEY'));
        $data = json_decode($feed, true);

        // 用循环插入代替batchInsert
        $db = DatabaseHelper::getInstance();
        foreach ($data['results'] as $item) {
            $db->insert('threat_intelligence', [
                'type' => $item['category'],
                'signature' => base64_encode($item['pattern']),
                'severity' => $item['risk_level']
            ]);
        }
    }
}