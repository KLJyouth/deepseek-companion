<?php
// 引入配置文件
require_once __DIR__ . '/../config.php';

// 检查系统维护状态
function checkMaintenanceMode() {
    $maintenanceFile = __DIR__ . '/../storage/framework/maintenance.php';
    if (file_exists($maintenanceFile)) {
        $data = @include $maintenanceFile;
        return [
            'is_down' => true,
            'message' => $data['message'] ?? '系统维护中',
            'retry_after' => $data['retry_after'] ?? 1800,
            'start_time' => $data['start_time'] ?? time()
        ];
    }
    return ['is_down' => false];
}

// 系统初始化前检查关键服务
function checkSystemServices() {
    $errors = [];
    
    // 检查数据库连接
    try {
        $db = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_TIMEOUT => 5) // 5秒超时
        );
    } catch (PDOException $e) {
        $errors[] = "数据库连接失败: " . $e->getMessage();
    }
    
    // 检查Redis
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 2.0); // 2秒超时
        } catch (Exception $e) {
            $errors[] = "Redis连接失败: " . $e->getMessage();
        }
    }
    
    return empty($errors) ? true : $errors;
}

try {
    // 检查维护状态
    $maintenance = checkMaintenanceMode();
    if ($maintenance['is_down']) {
        header('Retry-After: ' . $maintenance['retry_after']);
        require '../views/errors/503.php';
        exit(503);
    }
    
    // 检查系统服务
    $services = checkSystemServices();
    if ($services !== true) {
        error_log("系统服务检查失败: " . implode(", ", $services));
        require '../views/errors/503.php';
        exit(503);
    }
    
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
    error_log("系统错误: " . $e->getMessage());
    http_response_code(503);
    require '../views/errors/503.php';
    exit(503);
}
