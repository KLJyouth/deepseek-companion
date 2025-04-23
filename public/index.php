<?php
// 引入配置文件
require_once __DIR__ . '/../config.php';

// 初始化系统
try {
    // 设置错误处理
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // 设置安全headers
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    
    // 启动会话
    session_start();
    
    // 载入路由
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // 路由分发
    switch ($requestPath) {
        case '/':
        case '/index.php':
            require '../app/Http/Controllers/HomeController.php';
            break;
            
        case '/login':
            require '../app/Http/Controllers/LoginController.php';
            break;
            
        default:
            http_response_code(404);
            require '../views/errors/404.php';
            break;
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    require '../views/errors/500.php';
}
