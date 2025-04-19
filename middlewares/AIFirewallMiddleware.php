<?php
namespace Middlewares;

use Admin\Services\SecurityService;
use Libs\DatabaseHelper;

class AIFirewallMiddleware {
    private $patterns = [
        '/\b(union|select|insert|delete|update|drop|alter)\b/i',
        '/<script[^>]*>[\s\S]*?<\/script>/i'
    ];

    // 新增行为分析功能
    private $behaviorPatterns = [
        'request_frequency' => [
            'threshold' => 50,
            'window' => 60
        ],
        'sensitive_sequence' => [
            'login_attempts' => 5,
            'timeframe' => 300
        ]
    ];

    private $threatIntel = [
        'malicious_ips' => [],
        'tor_exit_nodes' => []
    ];

    // 新增威胁情报加载方法
    private function loadThreatIntel() {
        $this->threatIntel['malicious_ips'] = ThreatIntelligenceMiddleware::getMaliciousIPs();
        $this->threatIntel['tor_exit_nodes'] = ThreatIntelligenceMiddleware::getTorNodes();
    }

    // 行为模式分析方法
    private function analyzeBehavior($request) {
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $requestPath = parse_url($request['uri'], PHP_URL_PATH);

        // 实时威胁情报检查
        if (in_array($clientIP, $this->threatIntel['malicious_ips'])) {
            SecurityAuditHelper::audit('firewall_block', '已知恶意IP访问: '.$clientIP);
            return false;
        }

        // 请求频率分析
        $reqCount = apcu_fetch('req_count_'.$clientIP) ?: 0;
        if ($reqCount > $this->behaviorPatterns['request_frequency']['threshold']) {
            SecurityAuditHelper::audit('firewall_block', '高频请求异常: '.$clientIP);
            return false;
        }

        // 敏感操作序列检测
        $loginAttempts = apcu_fetch('login_attempts_'.$clientIP) ?: 0;
        if ($loginAttempts > $this->behaviorPatterns['sensitive_sequence']['login_attempts']) {
            SecurityAuditHelper::audit('firewall_block', '异常登录尝试: '.$clientIP);
            return false;
        }

        return true;
    }

    // 更新请求拦截逻辑
    private function shouldBlockRequest($request) {
        $this->loadThreatIntel();
        
        // 原有模式匹配
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $request['body'])) {
                return true;
            }
        }

        // 新增行为分析
        return !$this->analyzeBehavior($request);
    }

    // 新增实时计数器更新方法
    private function updateRequestCounters() {
        $clientIP = $_SERVER['REMOTE_ADDR'];
        apcu_inc('req_count_'.$clientIP);
        apcu_entry('req_count_'.$clientIP, function() {
            apcu_store('req_count_'.$clientIP, 1, $this->behaviorPatterns['request_frequency']['window']);
            return 1;
        });
    }
    private $securityService;

    public function __construct() {
        $this->securityService = new SecurityService();
        $this->securityService->enableWAF(['mode' => 'L3']);
    }

    public function process($request) {
        $this->updateRequestCounters();
        // 请求特征提取
        $requestVector = $this->extractFeatures($request);

        // 多模态分析
        $analysisResult = $this->securityService->deepLearningAnalyze([
            'headers' => $requestVector['headers'],
            'payload' => $requestVector['body'],
            'meta' => $requestVector['meta']
        ]);

        // 动态防御决策
        if ($analysisResult['threat_level'] > 7.5) {
            $this->securityService->triggerAutoIsolation($request['ip']);
            throw new \Exception("AI防火墙拦截：潜在高级持续性威胁");
        }

        // 上下文感知学习
        DatabaseHelper::getInstance()->query(
            "INSERT INTO request_patterns (signature, risk_score) VALUES (?, ?)",
            [hash('sha256', serialize($request)), $analysisResult['threat_level']]
        );

        return $request;
    }

    private function extractFeatures($request) {
        return [
            'headers' => $this->normalizeHeaders($request['headers']),
            'body' => $this->tokenizePayload($request['body']),
            'meta' => [
                'ip_velocity' => $this->calculateIPVelocity($request['ip']),
                'session_entropy' => $this->calculateSessionEntropy($request['session'])
            ]
        ];
    }

    private function calculateIPVelocity($ip) {
        $db = DatabaseHelper::getInstance();
        $count = $db->query("SELECT COUNT(*) FROM requests WHERE ip=? AND timestamp > NOW() - INTERVAL 1 MINUTE", [$ip]);
        return $count[0]['COUNT(*)'];
    }
}