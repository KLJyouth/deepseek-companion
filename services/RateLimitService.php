<?php
declare(strict_types=1);

namespace Services;

use Libs\CacheHelper;
use Services\SystemMonitorService;
use Libs\ConfigHelper;
use Exception;

class RateLimitService {
    private CacheHelper $cache;
    private SystemMonitorService $monitor;
    
    private array $defaultLimits = [
        'ip' => [
            'limit' => 100,
            'window' => 60,
            'min_limit' => 50,
            'max_limit' => 200,
            'policy' => '100;w=60;burst=50'
        ],
        'user' => [
            'limit' => 30,
            'window' => 60,
            'min_limit' => 15,
            'max_limit' => 60,
            'policy' => '30;w=60;burst=15'
        ],
        'session' => [
            'limit' => 60,
            'window' => 60,
            'min_limit' => 30,
            'max_limit' => 120,
            'policy' => '60;w=60;burst=30'
        ]
    ];
    
    public function __construct(
        CacheHelper $cache,
        SystemMonitorService $monitor
    ) {
        $this->cache = $cache;
        $this->monitor = $monitor;
    }
    
    public function check(string $key, string $type = 'ip'): array {
        if (!array_key_exists($type, $this->defaultLimits)) {
            throw new Exception("未知的速率限制类型: {$type}");
        }
        
        $limits = $this->getLimits($type);
        $cacheKey = "rate_limit:{$type}:{$key}";
        
        try {
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
            
            // 更新计数
            $current['count']++;
            $this->cache->set($cacheKey, $current, $limits['window']);
            
            return [
                'limit' => $limits['limit'],
                'remaining' => max(0, $limits['limit'] - $current['count']),
                'reset' => $current['reset'],
                'policy' => $limits['policy']
            ];
        } catch (Exception $e) {
            // 缓存失败时放宽限制
            return [
                'limit' => $limits['max_limit'],
                'remaining' => $limits['max_limit'],
                'reset' => time() + $limits['window'],
                'policy' => $limits['policy']
            ];
        }
    }
    
    private function getLimits(string $type): array {
        $config = ConfigHelper::get("rate_limit.{$type}", []);
        $limits = array_merge($this->defaultLimits[$type], $config);
        
        if ($this->shouldAdjustLimits()) {
            $limits = $this->adjustLimitsByLoad($limits);
        }
        
        return $limits;
    }
    
    private function shouldAdjustLimits(): bool {
        return ConfigHelper::get('rate_limit.dynamic_adjustment', true);
    }
    
    private function adjustLimitsByLoad(array $limits): array {
        $load = $this->monitor->getSystemLoad();
        $adjustment = $this->calculateLoadAdjustment($load);
        
        $limits['limit'] = min(
            $limits['max_limit'],
            max(
                $limits['min_limit'],
                round($limits['limit'] * $adjustment)
            )
        );
        
        return $limits;
    }
    
    private function calculateLoadAdjustment(array $load): float {
        $cpuAdjustment = $this->calculateCpuAdjustment($load['cpu']);
        $memoryAdjustment = $this->calculateMemoryAdjustment($load['memory']);
        
        return ($cpuAdjustment + $memoryAdjustment) / 2;
    }
    
    private function calculateCpuAdjustment(float $cpuUsage): float {
        if ($cpuUsage > 0.8) return 0.7; // 高负载时降低30%
        if ($cpuUsage > 0.6) return 0.85; // 中等负载时降低15%
        if ($cpuUsage < 0.3) return 1.2; // 低负载时增加20%
        return 1.0; // 正常负载
    }
    
    private function calculateMemoryAdjustment(float $memoryUsage): float {
        if ($memoryUsage > 0.8) return 0.6; // 高内存使用降低40%
        if ($memoryUsage > 0.6) return 0.8; // 中等内存使用降低20%
        if ($memoryUsage < 0.3) return 1.1; // 低内存使用增加10%
        return 1.0; // 正常内存使用
    }
}