<?php
namespace App\Services;

use Redis;

class CacheService {
    private $redis;
    private static $instance;
    private $prefix = 'deepseek:';
    
    private function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect(
            $_ENV['REDIS_HOST'] ?? 'localhost',
            $_ENV['REDIS_PORT'] ?? 6379
        );
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value ? json_decode($value, true) : null;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            json_encode($value)
        );
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function remember(string $key, callable $callback, int $ttl = 3600)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}