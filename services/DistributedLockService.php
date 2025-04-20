<?php
namespace Services;

class DistributedLockService {
    private $redis;
    private const LOCK_PREFIX = 'dlock:';
    private const DEFAULT_TIMEOUT = 30000; // 30秒
    
    public function acquire(string $resource, int $timeout = self::DEFAULT_TIMEOUT): bool {
        $token = uniqid('', true);
        $lockKey = self::LOCK_PREFIX . $resource;
        
        $acquired = false;
        $startTime = microtime(true) * 1000;
        
        do {
            $acquired = $this->redis->set(
                $lockKey,
                $token,
                ['NX', 'PX' => $timeout]
            );
            
            if (!$acquired) {
                usleep(100000); // 等待100ms
            }
        } while (!$acquired && (microtime(true) * 1000 - $startTime < $timeout));
        
        if ($acquired) {
            $this->setLockContext($resource, $token);
            return true;
        }
        
        return false;
    }
    
    public function release(string $resource): bool {
        $lockKey = self::LOCK_PREFIX . $resource;
        $token = $this->getLockContext($resource);
        
        if (!$token) {
            return false;
        }
        
        // 使用Lua脚本保证原子性
        $script = <<<LUA
if redis.call('get',KEYS[1]) == ARGV[1] then
    return redis.call('del',KEYS[1])
else
    return 0
end
LUA;
        
        return (bool)$this->redis->eval($script, [$lockKey, $token], 1);
    }
}
