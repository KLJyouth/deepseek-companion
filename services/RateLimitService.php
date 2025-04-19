<?php
namespace Services;

use Libs\CacheHelper;
use Exception;

class RateLimitService {
    private $cache;
    private $defaultLimits = [
        'ip' => [
            'limit' => 100, // 基础限制
            'window' => 60,
            'min_limit' => 50, // 最低限制
            'max_limit' => 200 // 最高限制
        ],
        'user' => [
            'limit' => 30,
            'window' => 60,
            'min_limit' => 15,
            'max_limit' => 60
        ]
    ];
    
    private $loadFactors = [
        'cpu' => 1.0,
        'memory' => 0.7,
        'concurrent' => 0.5
    ];
    
    public function __construct() {
        $this->cache = CacheHelper::getInstance();
    }
    
    /**
     * 检查请求速率限制
     */
    public function check(string $key, string $type = 'ip'): array {
        $limits = $this->getLimits($type);
        $cacheKey = "rate_limit:{$type}:{$key}";
        
        $current = $this->cache->get($cacheKey) ?: [
            'count' => 0,
            'reset' => time() + $limits['window']
        ];
        
        // 重置周期
        if (time() > $current['reset']) {
            $current = [
                'count' => 0,
                'reset' => time() + $limits['window']
            ];
        }
        
        // 检查限制
        $current['count']++;
        $this->cache->set($cacheKey, $current, $limits['window']);
        
        return [
            'limit' => $limits['limit'],
            'remaining' => max(0, $limits['limit'] - $current['count']),
            'reset' => $current['reset']
        ];
    }
    
    private function getLimits(string $type): array {
        $config = ConfigHelper::get("rate_limit.{$type}");
        $limits = array_merge($this->defaultLimits[$type], $config ?: []);
        
        // 根据系统负载动态调整限制
        if ($this->shouldAdjustLimits()) {
            $adjustment = $this->calculateLoadAdjustment();
            $limits['limit'] = min(
                $limits['max_limit'],
                max(
                    $limits['min_limit'],
                    round($limits['limit'] * $adjustment)
                )
            );
        }
        
        return $limits;
    }
    
    private function shouldAdjustLimits(): bool {
        return ConfigHelper::get('rate_limit.dynamic_adjustment', true);
    }
    
    private function calculateLoadAdjustment(): float {
        $load = $this->getSystemLoad();
        $adjustment = 1.0;
        
        foreach ($this->loadFactors as $metric => $factor) {
            if ($load[$metric] > 0.7) { // 高负载
                $adjustment *= (1 - ($load[$metric] - 0.7) * $factor);
            } else { // 低负载
                $adjustment *= (1 + (0.7 - $load[$metric]) * $factor * 0.5);
            }
        }
        
        return max(0.5, min(1.5, $adjustment)); // 限制调整范围
    }
    
    private function getSystemLoad(): array {
        // 获取系统负载指标
        return [
            'cpu' => sys_getloadavg()[0] / sysconf(_SC_NPROCESSORS_ONLN),
            'memory' => 1 - (memory_get_usage(true) / memory_get_usage(false)),
            'concurrent' => $this->getConcurrentRequests() / 100 // 假设100并发为基准
        ];
    }
    
    private $monitor;
    
    public function __construct() {
        $this->cache = CacheHelper::getInstance();
        $this->monitor = new SystemMonitorService();
    }
    
    private function getConcurrentRequests(): int {
        return $this->monitor->getCurrentLoad()['concurrent'];
    }
}