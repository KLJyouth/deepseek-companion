<?php
namespace Libs;

/**
 * 应用主配置文件 - 重构版
 * 采用模块化分组配置，支持环境变量覆盖
 */

define('ROOT_PATH', realpath(__DIR__));

// ==================== 基础配置 ====================
// 环境检测 - 优先使用环境变量
$env = getenv('APP_ENV') ?: 'production';

// ==================== 路径配置 ====================
define('LOG_PATH', ROOT_PATH.'/logs');
define('SESSION_PATH', ROOT_PATH.'/sessions');
define('CACHE_PATH', ROOT_PATH.'/cache');

// ==================== 安全配置 ====================
// 加密配置
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: bin2hex(random_bytes(16)));
define('ENCRYPTION_IV', getenv('ENCRYPTION_IV') ?: bin2hex(random_bytes(8)));

// 统一会话安全配置
$sessionSecure = $env === 'production';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $sessionSecure ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1小时
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// ==================== 数据库配置 ====================
// 数据库配置必须通过环境变量设置
if (!getenv('DB_HOST') || !getenv('DB_USER') || !getenv('DB_PASS') || !getenv('DB_NAME')) {
    throw new \RuntimeException('数据库配置未设置，请通过环境变量配置');
}

define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS')); 
define('DB_NAME', getenv('DB_NAME'));
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL', filter_var(getenv('DB_SSL'), FILTER_VALIDATE_BOOLEAN));
define('DB_TABLE_PREFIX', getenv('DB_TABLE_PREFIX') ?: 'ac_');

// 数据库连接超时
define('DB_CONNECT_TIMEOUT', 5);
define('DB_READ_TIMEOUT', 30);

// ==================== 应用配置 ====================
// 安全设置
define('SIGNATURE_KEY', getenv('SIGNATURE_KEY') ?: bin2hex(random_bytes(32)));
define('SIGNATURE_TIMEOUT', 300); // 签名有效期(秒)


// 电子签约平台配置
define('FADADA_API_KEY', CryptoHelper::encrypt(getenv('FADADA_API_KEY')));
define('FADADA_API_SECRET', CryptoHelper::encrypt(getenv('FADADA_API_SECRET')));
define('FADADA_CALLBACK_SECRET', CryptoHelper::encrypt(getenv('FADADA_CALLBACK_SECRET')));

// API配置
if (!getenv('DEEPSEEK_API_KEY')) {
    throw new \RuntimeException('DeepSeek API密钥未设置，请通过环境变量配置');
}
define('DEEPSEEK_API_KEY', CryptoHelper::encrypt(getenv('DEEPSEEK_API_KEY')));
define('DEEPSEEK_API_BASE_URL', 'https://api.deepseek.com/v1');
define('DEEPSEEK_API_TIMEOUT', 30);
define('DEEPSEEK_API_MAX_RETRIES', 3);

// ==================== 目录权限 ====================
// 安全目录权限配置
define('REQUIRED_DIRS', [
    LOG_PATH => '0750',  // 仅允许所有者读写执行，组读执行
    SESSION_PATH => '0750',
    CACHE_PATH => '0750'
]);

// 确保目录所有者是web服务器用户
foreach (REQUIRED_DIRS as $dir => $perms) {
    if (!file_exists($dir)) {
        mkdir($dir, octdec($perms), true);
        chown($dir, 'www-data'); // 根据实际web服务器用户调整
    }
}

// ==================== 初始化加载 ====================
function require_lib($path) {
    require_once ROOT_PATH . '/' . ltrim($path, '/');
}

require_lib('libs/CryptoHelper.php');
require_lib('libs/DatabaseHelper.php');
require_lib('middlewares/AuthMiddleware.php');
// 移除中间常量定义

// 保留目录权限常量定义

// 使用新的初始化系统
require_once __DIR__ . '/libs/Bootstrap.php';
try {
// 问题在于命名空间重复，正确的类名引用应为 Libs\Bootstrap，当前代码已经正确引用，无需修改。
    Bootstrap::initialize();
} catch (\Exception $e) {
    die("系统初始化失败: ".$e->getMessage());
}








// 管理员跳过密码哈希(必须通过环境变量设置)
if (!getenv('ADMIN_BYPASS_PASSWORD')) {
    throw new \RuntimeException('管理员绕过密码未设置，请通过环境变量配置');
}
define('ADMIN_BYPASS_HASH', password_hash(getenv('ADMIN_BYPASS_PASSWORD'), PASSWORD_DEFAULT));

// 确保logs目录存在
if (!file_exists(__DIR__.'/logs')) {
    mkdir(__DIR__.'/logs', 0777, true);
}



// 已移动至文件顶部

// DeepSeek API配置 (符合官方文档规范)
// 设置API密钥

// DeepSeek API配置
define('DEEPSEEK_API_BASE_URL', 'https://api.deepseek.com/v1');
define('DEEPSEEK_API_CHAT_ENDPOINT', DEEPSEEK_API_BASE_URL.'/chat/completions');
define('DEEPSEEK_API_TIMEOUT', 30); // 请求超时(秒)
define('DEEPSEEK_API_MAX_RETRIES', 3); // 最大重试次数

// 标准请求头配置
define('DEEPSEEK_API_HEADERS', [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer '.CryptoHelper::decrypt(DEEPSEEK_API_KEY),
    'Accept' => 'application/json'
]);

// API响应格式标准
define('API_RESPONSE_FORMAT', [
    'success' => false,
    'code' => 0,
    'message' => '',
    'data' => null,
    'timestamp' => time()
]);

// 标准错误码
define('API_ERROR_CODES', [
    // 系统级错误 1-999
    1 => '系统错误',
    2 => '服务不可用',
    3 => '参数错误',
    
    // 认证错误 1000-1999
    1000 => '未授权',
    1001 => '令牌过期',
    1002 => '权限不足',
    
    // 业务错误 2000-2999
    2000 => '业务逻辑错误',
    2001 => '数据不存在'
]);

// 统一错误处理配置
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0); // 始终不显示错误
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/app_errors.log');

// 自定义错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    $message = sprintf(
        "[%s] %s: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $errorType,
        $errstr,
        $errfile,
        $errline
    );
    
    error_log($message);
    
    // 严重错误时发送500响应
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        if ($env === 'development') {
            echo "<pre>$message</pre>";
        } else {
            readfile(ROOT_PATH . '/error_pages/500.html');
        }
        exit(1);
    }
    
    return true;
});

// 异常处理
set_exception_handler(function($e) {
    $message = sprintf(
        "[%s] Exception: %s in %s:%d\nStack Trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    
    error_log($message);
    
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    if ($env === 'development') {
        echo "<pre>$message</pre>";
    } else {
        readfile(ROOT_PATH . '/error_pages/500.html');
    }
    exit(1);
});
// 会话目录配置
$sessionPath = __DIR__.'/sessions';
if (!file_exists($sessionPath)) {
    if (!mkdir($sessionPath, 0777, true)) {
        die("无法创建会话目录: $sessionPath");
    }
}
if (!is_writable($sessionPath)) {
    die("会话目录不可写: $sessionPath");
}
ini_set('session.save_path', $sessionPath);
ini_set('session.save_handler', 'files');


ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 1440);
ini_set('syslog.facility', LOG_LOCAL0);
ini_set('syslog.ident', 'ai_companion');

// 创建所需目录
foreach (REQUIRED_DIRS as $dir => $mode) {
    if (!file_exists($dir)) {
        mkdir($dir, octdec($mode), true);
    }
}

// 创建安全数据库连接
try {
    initializeDatabaseConnection();
} catch (\Exception $e) {
    $errorMsg = date('[Y-m-d H:i:s] ') . '数据库错误: ' . $e->getMessage();
    error_log($errorMsg);
    file_put_contents(__DIR__ . '/logs/db_errors.log', $errorMsg . PHP_EOL, FILE_APPEND);
    
    header('HTTP/1.1 503 Service Unavailable');
    include __DIR__ . '/maintenance.html';
    exit;
}

// 数据库连接初始化函数


function initializeDatabaseConnection() {
    // 测试数据库连接参数
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        throw new \Exception('数据库配置不完整');
    }

    global $conn;
    // 连接超时设置
    $conn = new \mysqli();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    // 建立实际连接
    if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        throw new \Exception(sprintf(
            "数据库连接失败(主机:%s 用户:%s): %s",
            DB_HOST,
            DB_USER,
            $conn->connect_error
        ));
    }

    // 验证连接是否有效
    if (!$conn || $conn->connect_error) {
        throw new \Exception("数据库连接无效: " . ($conn->connect_error ?? '未知错误'));
    }

    // 设置字符集和SSL
    if (!$conn->set_charset(DB_CHARSET)) {
        throw new \Exception("设置字符集失败: " . $conn->error);
    }
    
    if (DB_SSL) {
        // 从环境变量获取SSL证书路径
        $sslConfig = [
            'ca' => getenv('DB_SSL_CA') ?: null,
            'cert' => getenv('DB_SSL_CERT') ?: null,
            'key' => getenv('DB_SSL_KEY') ?: null
        ];

        // 验证证书文件
        foreach ($sslConfig as $type => $path) {
            if ($path && !file_exists($path)) {
                throw new \Exception("SSL证书文件不存在: $type=$path");
            }
            if ($path && !is_readable($path)) {
                throw new \Exception("SSL证书文件不可读: $type=$path");
            }
        }

        // 设置SSL连接
        $conn->ssl_set(
            $sslConfig['key'],
            $sslConfig['cert'],
            $sslConfig['ca'],
            null,
            null
        );
    }
    
    // 建立实际连接
    if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        throw new \Exception(sprintf(
            "数据库连接失败(主机:%s 用户:%s): %s",
            DB_HOST,
            DB_USER,
            $conn->connect_error
        ));
    }

    // 初始化数据库助手
    $dbHelper = new DatabaseHelper($conn, DB_TABLE_PREFIX);

    // 测试数据库查询
    if (!$conn->query("SELECT 1")) {
        throw new \Exception("数据库查询测试失败: " . $conn->error);
    }
}
// ==================== 应用初始化 ====================
try {
    // 初始化数据库连接
    initializeDatabaseConnection();
    
    // 初始化会话
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => SESSION_TIMEOUT,
            'cookie_secure' => $env === 'production',
            'cookie_httponly' => true
        ]);
    }
    
    // 注册自动加载
    spl_autoload_register(function($class) {
        $file = ROOT_PATH . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
    
} catch (\Exception $e) {
    // 统一错误处理
    $errorMsg = sprintf(
        "[%s] %s: %s\n%s",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getTraceAsString()
    );
    
    error_log($errorMsg);
    file_put_contents(LOG_PATH . '/app_errors.log', $errorMsg, FILE_APPEND);
    
    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Unavailable');
    }
    include ROOT_PATH . '/maintenance.html';
    exit;
}

// ==================== 工具函数 ====================
/**
 * 输入净化
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    switch ($type) {
        case 'int':
            return (int) filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return (float) filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 安全重定向
 */
function redirect($url, $statusCode = 303) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!headers_sent()) {
        header("Location: $url", true, $statusCode);
        exit;
    }
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
    exit;
}

// ==================== 安全头设置 ====================
// 统一安全头设置
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; img-src \'self\' data:; font-src \'self\' cdn.jsdelivr.net');
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');

// CSRF保护
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRE', 3600);