<?php
namespace Services;

class IntrusionDetectionService {
    private $rules;
    private $redis;
    private $logger;
    
    public function __construct() {
        $this->rules = require __DIR__ . '/../config/waf_rules.php';
        $this->redis = new \Redis();
        $this->logger = new \Libs\LogHelper();
    }
    
    public function analyzeRequest(array $request): array {
        $score = 0;
        $matches = [];
        
        foreach ($this->rules['rules'] as $type => $rule) {
            if ($this->matchesRule($request, $rule['pattern'])) {
                $score += $rule['score'];
                $matches[] = $type;
            }
        }
        
        $action = $this->determineAction($score);
        $this->logDetection($score, $matches, $action);
        
        return [
            'action' => $action,
            'score' => $score,
            'matches' => $matches
        ];
    }
    
    private function determineAction(int $score): string {
        if ($score >= $this->rules['thresholds']['block_score']) {
            return 'block';
        }
        if ($score >= $this->rules['thresholds']['alert_score']) {
            return 'alert';
        }
        return 'allow';
    }
    
    private function logDetection(int $score, array $matches, string $action): void {
        $this->logger->alert('Intrusion Detection', [
            'score' => $score,
            'matches' => $matches,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'timestamp' => time()
        ]);
    }
}
