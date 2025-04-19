<?php
/**
 * 系统初始化状态检查工具
 */

require_once 'config.php';

// 发送邮件通知
function send_error_email($subject, $message) {
    if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
        return false;
    }
    
    $headers = "From: system@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    
    return mail(ADMIN_EMAIL, $subject, $message, $headers);
}

// 初始化日志
function log_check($message) {
    $logFile = 'logs/system_check.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// 创建日志目录
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

// 定义控制台颜色
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[31m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");

header('Content-Type: text/plain; charset=utf-8');
echo COLOR_BLUE . "=== 系统初始化状态检查 ===" . COLOR_RESET . "\n\n";
log_check("=== 系统初始化状态检查开始 ===");

// 1. 检查数据库连接
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    echo COLOR_GREEN . "[√] 数据库连接成功" . COLOR_RESET . "\n";
    log_check("数据库连接成功");
    
    // 2. 检查核心表（包括登录系统相关表）
    $tables = [
        'users', 
        'user_settings', 
        'conversations', 
        'messages',
        'login_attempts',  // 登录尝试记录
        'remember_tokens', // 记住我token
        'audit_logs'       // 审计日志
    ];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        if ($result->num_rows == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo COLOR_GREEN . "[√] 核心表存在" . COLOR_RESET . "\n";
    } else {
        echo COLOR_RED . "[×] 缺失表: " . implode(', ', $missing_tables) . COLOR_RESET . "\n";
        log_check("缺失表: " . implode(', ', $missing_tables));
    }
    
    // 3. 检查管理员账号
    $result = $conn->query("SELECT id FROM users WHERE username='admin' LIMIT 1");
    if ($result->num_rows > 0) {
        echo COLOR_GREEN . "[√] 管理员账号存在" . COLOR_RESET . "\n";
        log_check("管理员账号存在");
    } else {
        echo COLOR_RED . "[×] 管理员账号不存在" . COLOR_RESET . "\n";
        log_check("管理员账号不存在");
    }
    
    // 4. 检查加密配置
    try {
        $test = CryptoHelper::encrypt('test');
        CryptoHelper::decrypt($test);
        echo COLOR_GREEN . "[√] 加密功能正常" . COLOR_RESET . "\n";
        log_check("加密功能正常");
    } catch (Exception $e) {
        echo COLOR_RED . "[×] 加密错误: " . $e->getMessage() . COLOR_RESET . "\n";
        log_check("加密错误: " . $e->getMessage());
    }
    
    // 3. 检查关键控制器文件
    $requiredFiles = [
        'controllers/LoginController.php',
        'login.php',
        'libs/AuthMiddleware.php'
    ];
    
    $missingFiles = [];
    foreach ($requiredFiles as $file) {
        if (!file_exists($file) || !is_readable($file)) {
            $missingFiles[] = $file;
        }
    }
    
    if (empty($missingFiles)) {
        echo COLOR_GREEN . "[√] 关键控制器文件存在" . COLOR_RESET . "\n";
        log_check("关键控制器文件存在");
    } else {
        echo COLOR_RED . "[×] 缺失关键文件: " . implode(', ', $missingFiles) . COLOR_RESET . "\n";
        log_check("缺失关键文件: " . implode(', ', $missingFiles));
    }
    
    // 4. 检查加密配置
    // 5. 检查环境配置
    echo COLOR_BLUE . "\n=== 环境配置检查 ===" . COLOR_RESET . "\n";
    echo "PHP版本: " . phpversion() . "\n";
    
    // 检查必需PHP扩展
    $required_extensions = ['mysqli', 'curl', 'openssl', 'json', 'mbstring'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        echo COLOR_GREEN . "[√] 必需PHP扩展已安装" . COLOR_RESET . "\n";
        log_check("必需PHP扩展已安装");
    } else {
        echo COLOR_RED . "[×] 缺失PHP扩展: " . implode(', ', $missing_extensions) . COLOR_RESET . "\n";
        log_check("缺失PHP扩展: " . implode(', ', $missing_extensions));
    }
    
    echo "会话保存路径: " . ini_get('session.save_path') . "\n";
    echo "会话状态: " . (session_status() === PHP_SESSION_ACTIVE ? "活跃" : "未启动") . "\n";
    
    // 6. 文件权限检查
    $required_writable = ['logs/', 'cache/'];
    foreach ($required_writable as $dir) {
        echo "目录 {$dir} 可写: " . 
             (is_writable($dir) ? COLOR_GREEN . "[√]" : COLOR_RED . "[×]") . 
             COLOR_RESET . "\n";
    }
    
    // 7. API连通性测试
    echo COLOR_BLUE . "\n=== API连通性测试 ===" . COLOR_RESET . "\n";
    try {
        $retryCount = 0;
        $maxRetries = defined('DEEPSEEK_API_MAX_RETRIES') ? DEEPSEEK_API_MAX_RETRIES : 3;
        $apiEndpoint = defined('DEEPSEEK_API_CHAT_ENDPOINT') ? DEEPSEEK_API_CHAT_ENDPOINT : DEEPSEEK_API_BASE_URL.'/chat/completions';
        
        while ($retryCount <= $maxRetries) {
            try {
                $ch = curl_init($apiEndpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, defined('DEEPSEEK_API_TIMEOUT') ? DEEPSEEK_API_TIMEOUT : 30);
                
                // 使用配置的标准请求头
                $headers = [];
                foreach (DEEPSEEK_API_HEADERS as $key => $value) {
                    $headers[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    throw new Exception("cURL错误: $error");
                }
                
                if ($httpCode >= 400) {
                    throw new Exception("API返回错误状态码: $httpCode");
                }
                
                echo "API端点连通性($apiEndpoint): " . 
                     COLOR_GREEN . "[√] 连接成功" . COLOR_RESET . "\n";
                log_check("API端点连通性测试成功: $apiEndpoint");
                break;
                
            } catch (Exception $e) {
                $retryCount++;
                log_check("API测试尝试 $retryCount/$maxRetries 失败: " . $e->getMessage());
                
                if ($retryCount > $maxRetries) {
                    echo "API端点连通性($apiEndpoint): " . 
                         COLOR_RED . "[×] 连接失败: " . $e->getMessage() . COLOR_RESET . "\n";
                    throw $e;
                }
                
                // 等待1秒后重试
                sleep(1);
            }
        }
    } catch (Exception $e) {
        echo COLOR_RED . "[×] API测试失败: " . $e->getMessage() . COLOR_RESET . "\n";
        log_check("API测试最终失败: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    echo COLOR_RED . "[×] 初始化检查失败: " . $e->getMessage() . COLOR_RESET . "\n";
    log_check("初始化检查失败: " . $e->getMessage());
}

echo COLOR_YELLOW . "\n=== 修复建议 ===" . COLOR_RESET . "\n";
if (!empty($missing_tables)) {
    echo "1. 请执行数据库初始化脚本: php ai_companion_db.sql\n";
}
if ($result->num_rows == 0) {
    echo "2. 管理员账号不存在，请检查users表或重新初始化\n";
}

echo COLOR_YELLOW . "\n=== 详细诊断报告 ===" . COLOR_RESET . "\n";
echo "请检查以下方面:\n";
echo "- 确保所有标记[×]的问题已解决\n";
echo "- 检查PHP错误日志: " . ini_get('error_log') . "\n";
echo "- 验证文件权限(特别是logs/和cache/目录)\n";
echo "- 检查API密钥有效性\n";
echo "- 确认会话配置正确\n";

// 收集错误信息
$errors = [];
if (!empty($missing_tables)) $errors[] = "缺失数据库表: " . implode(', ', $missing_tables);
if (!empty($missing_extensions)) $errors[] = "缺失PHP扩展: " . implode(', ', $missing_extensions);
if ($result->num_rows == 0) $errors[] = "管理员账号不存在";

echo "\n" . COLOR_BLUE . "检查完成。请将完整输出提供给管理员。" . COLOR_RESET . "\n";
log_check("=== 系统初始化状态检查完成 ===");

// 如果有严重错误，发送邮件通知
if (!empty($errors) && defined('ADMIN_EMAIL')) {
    $subject = "[紧急] 系统初始化检查发现问题";
    $message = "系统初始化检查发现以下问题:\n\n";
    $message .= implode("\n", $errors) . "\n\n";
    $message .= "请尽快处理这些问题以确保系统正常运行。";
    
    if (send_error_email($subject, $message)) {
        log_check("已发送错误通知邮件给管理员");
    } else {
        log_check("发送错误通知邮件失败");
    }
}
