<?php
namespace Services;

class EnhancedDistributedLock {
    private const LOCK_PREFIX = 'lock:';
    private const MONITOR_PREFIX = 'lock_monitor:';
    
    private $redis;
    private $resource;
    private $options;
    private $token;
    
    public function __construct(string $resource, array $options = []) {
        $this->redis = new \Redis();
        $this->resource = $resource;
        $this->options = array_merge([
            'expire' => 30000,
            'retry' => ['times' => 3, 'delay' => 100],
            'monitor' => false
        ], $options);
        $this->token = uniqid('', true);
    }
    
    public function acquire(): bool {
        $lockKey = self::LOCK_PREFIX . $this->resource;
        $monitorKey = self::MONITOR_PREFIX . $this->resource;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $this->options['retry']['times']; $i++) {
            // 尝试获取锁
            $acquired = $this->redis->set(
                $lockKey,
                $this->token,
                ['NX', 'PX' => $this->options['expire']]
            );
            
            if ($acquired) {
                if ($this->options['monitor']) {
                    $this->recordLockMetrics($monitorKey, [
                        'acquired_at' => microtime(true),
                        'wait_time' => microtime(true) - $startTime,
                        'retries' => $i
                    ]);
                }
                return true;
            }
            
            // 未获取到锁，进行竞争分析
            if ($this->options['monitor']) {
                $this->analyzeLockContention($monitorKey);
            }
            
            usleep($this->options['retry']['delay'] * 1000);
        }
        
        return false;
    }
    
    private function analyzeLockContention(string $monitorKey): void {
        $metrics = $this->redis->hGetAll($monitorKey);
        
        // 检测锁竞争程度
        if ($metrics['contention_level'] > 0.8) {
            $this->alertHighContention($this->resource, $metrics);
        }
        
        // 更新竞争指标
        $this->redis->hIncrBy($monitorKey, 'contention_count', 1);
    }
}
