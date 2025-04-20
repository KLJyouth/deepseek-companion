<?php
namespace Services;

class LockMonitorService {
    private $redis;
    private const LOCK_PREFIX = 'lock:monitor:';
    
    public function __construct() {
        $this->redis = new \Redis();
    }
    
    public function recordLockMetrics(string $resource, array $metrics): void {
        $key = self::LOCK_PREFIX . $resource;
        $this->redis->hMSet($key, array_merge($metrics, [
            'last_updated' => time(),
            'total_attempts' => $this->redis->hIncrBy($key, 'total_attempts', 1)
        ]));
    }

    public function getLockStatistics(): array {
        $locks = $this->redis->keys(self::LOCK_PREFIX . '*');
        $stats = [];
        
        foreach ($locks as $lock) {
            $stats[str_replace(self::LOCK_PREFIX, '', $lock)] = $this->redis->hGetAll($lock);
        }
        
        return $stats;
    }
}
