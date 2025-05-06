<?php
header('Content-Type: application/json');

// 确保是AJAX请求
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['error' => '非法访问']));
}

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => '非法请求方法']));
}

// 获取输入数据
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(['error' => '无效的JSON数据']));
}

// 验证必需字段
$requiredFields = ['dbHost', 'dbName', 'dbUser', 'adminUser', 'adminPass', 'adminEmail'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        die(json_encode(['error' => "缺少必需字段: $field"]));
    }
}

// 数据库配置
$dbHost = $input['dbHost'];
$dbName = $input['dbName'];
$dbUser = $input['dbUser'];
$dbPass = $input['dbPass'] ?? '';
$adminUser = $input['adminUser'];
$adminPass = $input['adminPass'];
$adminEmail = $input['adminEmail'];

// 初始化响应
$response = [
    'progress' => 0,
    'message' => '开始安装...',
    'success' => false
];

// 模拟安装步骤
$steps = [
    ['progress' => 10, 'message' => '正在创建数据库...', 'action' => 'createDatabase'],
    ['progress' => 30, 'message' => '正在导入初始数据...', 'action' => 'importData'],
    ['progress' => 50, 'message' => '正在写入配置文件...', 'action' => 'writeConfig'],
    ['progress' => 70, 'message' => '正在设置管理员账户...', 'action' => 'createAdmin'],
    ['progress' => 90, 'message' => '正在进行最终检查...', 'action' => 'finalCheck'],
    ['progress' => 100, 'message' => '安装完成!', 'action' => 'complete']
];

// 执行安装步骤
try {
    foreach ($steps as $step) {
        $response['progress'] = $step['progress'];
        $response['message'] = $step['message'];
        
        // 在实际应用中，这里会调用相应的函数执行操作
        // 例如: $this->{$step['action']}($input);
        
        // 模拟延迟
        usleep(500000);
        
        // 返回当前进度
        echo json_encode($response);
        flush();
        
        // 如果是最后一个步骤，设置成功标志
        if ($step['progress'] === 100) {
            $response['success'] = true;
            echo json_encode($response);
            exit;
        }
    }
} catch (Exception $e) {
    $response['message'] = '安装失败: ' . $e->getMessage();
    $response['success'] = false;
    echo json_encode($response);
    exit;
}

// 数据库创建函数 (示例)
function createDatabase($dbHost, $dbUser, $dbPass, $dbName) {
    try {
        // 创建主数据库连接
        $conn = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建数据库
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // 创建用户并授权 (简化版，实际应根据需要设置精确权限)
        $conn->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'$dbHost' IDENTIFIED BY '$dbPass'");
        $conn->exec("FLUSH PRIVILEGES");
        
        return true;
    } catch (PDOException $e) {
        throw new Exception("数据库操作失败: " . $e->getMessage());
    }
}

// 配置文件模板路径
$configTemplatePath = dirname(__DIR__) . '/config.template.php';
?>