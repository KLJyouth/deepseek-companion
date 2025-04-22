<?php
namespace Services;

use Libs\LogHelper;

class LockMonitorService {
    private Redis $redis;
    private const LOCK_PREFIX = 'lock:monitor:';

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 6379,
        private float $timeout = 2.5,
        private int $retryInterval = 100
    ) {
        $this->redis = new \Redis();

        try {
            if (!$this->redis->connect($host, $port, $timeout, null, $retryInterval)) {
                throw new RedisException('Redis连接失败');
            }
        } catch (\RedisException $e) {
            LogHelper::getInstance()->error('Redis监控连接异常: ' . $e->getMessage());
            throw $e;
        }
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
