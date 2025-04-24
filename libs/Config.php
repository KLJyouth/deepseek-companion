<?php
namespace Libs;

class Config {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->loadConfigurations();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfigurations(): void {
        // 加载所有配置文件
        $configPath = dirname(__DIR__) . '/config';
        $files = glob($configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }
    
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
    
    public function set(string $key, $value): void {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($config[$key]) || !is_array($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }
        
        $config[array_shift($keys)] = $value;
    }
    
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }
}