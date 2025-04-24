<?php
namespace Libs;

class CacheHelper {
    private static $instance = null;
    private $cache = [];
    
    private function __construct() {
        // 初始化缓存连接
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get(string $key, $default = null) {
        return $this->cache[$key] ?? $default;
    }
    
    public function set(string $key, $value, int $ttl = 3600): void {
        $this->cache[$key] = $value;
    }
    
    public function has(string $key): bool {
        return isset($this->cache[$key]);
    }
    
    public function delete(string $key): void {
        unset($this->cache[$key]);
    }
    
    public function remember(string $key, callable $callback, int $ttl = 3600) {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
    
    public function flush(): void {
        $this->cache = [];
    }
}