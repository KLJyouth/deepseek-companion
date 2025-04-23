<?php
/**
 * 环境变量助手函数
 */

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        static $env = null;
        
        if ($env === null) {
            $env = loadEnvFile();
        }
        
        return $env[$key] ?? $default;
    }
}

function loadEnvFile(): array {
    $envPath = dirname(__DIR__) . '/.env';
    $env = [];
    
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // 跳过注释
            }
            
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }
    }
    
    // 默认值
    $env = array_merge([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306'
    ], $env);
    
    return $env;
}

// 初始化环境变量
if (!defined('ENV_INITIALIZED')) {
    define('ENV_INITIALIZED', true);
    $_ENV = array_merge($_ENV, loadEnvFile());
}