<?php
namespace Libs;

/**
 * 应用主配置文件 - 重构版
 * 采用模块化分组配置，支持环境变量覆盖
 */

define('ROOT_PATH', realpath(__DIR__));

// ========== 加载.env支持 ==========
if (\file_exists(__DIR__ . '/.env')) {
    $lines = \file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = \trim($line);
        if ($line === '' || \strpos($line, '#') === 0 || \strpos($line, '=') === false) continue;
        [$k, $v] = \explode('=', $line, 2);
        $k = \trim($k);
        $v = \trim($v);
        // 设置到 $_ENV、$_SERVER，并尝试 putenv
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
        if (function_exists('putenv')) {
            @putenv("$k=$v");
        }
    }
}

// 新增env函数，优先顺序：getenv > $_ENV > $_SERVER > 常量 > 默认值
function env($key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    if (defined($key)) return constant($key);
    return $default;
}

// 工具函数：将环境变量同步为常量（如未定义）
function define_env_constant($key, $default = null) {
    if (!defined($key)) {
        define($key, env($key, $default));
    }
}

// ==================== 基础配置 ====================
// 环境检测 - 优先使用环境变量
// $env variable removed, using env('APP_ENV') directly

// ==================== 路径配置 ====================
define('LOG_PATH', ROOT_PATH.'/logs');
define('SESSION_PATH', ROOT_PATH.'/sessions');
define('CACHE_PATH', ROOT_PATH.'/cache');

// ========== 必须先加载加密库 ==========
require_once ROOT_PATH . '/libs/CryptoHelper.php';

// ==================== 安全配置 ====================
// 加密配置
// Supported values: 'AES-256-CBC', 'quantum'
define('ENCRYPTION_METHOD', env('ENCRYPTION_METHOD', 'quantum'));
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', bin2hex(random_bytes(16)))); // For legacy encryption
define('ENCRYPTION_IV', env('ENCRYPTION_IV', bin2hex(random_bytes(8)))); // For legacy encryption

// 初始化加密组件，避免未初始化报错
try {
    // ENCRYPTION_KEY 32字节，ENCRYPTION_IV 12字节（GCM）或16字节（CBC），此处按GCM优先
    $key = ENCRYPTION_KEY;
    $iv = ENCRYPTION_IV;
    if (strlen($key) === 32 && (strlen($iv) === 12 || strlen($iv) === 16)) {
        \Libs\CryptoHelper::init($key, $iv);
    }
} catch (\Throwable $e) {
    error_log('加密组件初始化失败: ' . $e->getMessage());
    // 可选：降级或终止
}

// Quantum encryption settings
define('QUANTUM_KEY_ROTATION', env('QUANTUM_KEY_ROTATION', 3600)); // Rotation interval in seconds
define('QUANTUM_MAX_KEY_VERSIONS', env('QUANTUM_MAX_KEY_VERSIONS', 3)); // Number of old keys to retain

// 统一会话安全配置
$sessionSecure = env('APP_ENV') === 'production';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $sessionSecure ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1小时
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// ==================== 数据库配置 ====================
// 兼容 .env 中 DB_DATABASE 和 DB_NAME
$dbName = env('DB_NAME');
if (!$dbName) {
    $dbName = env('DB_DATABASE');
}
// 兼容 .env 中 DB_USER 和 DB_USERNAME
$dbUser = env('DB_USER');
if (!$dbUser) {
    $dbUser = env('DB_USERNAME');
}
// 兼容 .env 中 DB_PASS 和 DB_PASSWORD
$dbPass = env('DB_PASS');
if (!$dbPass) {
    $dbPass = env('DB_PASSWORD');
}

// 数据库配置必须通过环境变量设置
if (!env('DB_HOST') || !$dbUser || !$dbPass || !$dbName) {
    throw new \RuntimeException('数据库配置未设置，请通过环境变量配置');
}

define('DB_HOST', env('DB_HOST'));
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL', filter_var(env('DB_SSL'), FILTER_VALIDATE_BOOLEAN));
define('DB_TABLE_PREFIX', env('DB_TABLE_PREFIX', 'ac_'));

// 数据库连接超时
define('DB_CONNECT_TIMEOUT', 5);
define('DB_READ_TIMEOUT', 30);

// ==================== 应用配置 ====================
// 安全设置
define('SIGNATURE_KEY', env('SIGNATURE_KEY', bin2hex(random_bytes(32))));
define('SIGNATURE_TIMEOUT', 300); // 签名有效期(秒)


// 电子签约平台配置
// define('FADADA_API_KEY', CryptoHelper::encrypt(env('FADADA_API_KEY')));
// define('FADADA_API_SECRET', CryptoHelper::encrypt(env('FADADA_API_SECRET')));
// define('FADADA_CALLBACK_SECRET', CryptoHelper::encrypt(env('FADADA_CALLBACK_SECRET')));
// 替换为本地自研电子签约与法务模块配置
define('CONTRACT_STORAGE_PATH', ROOT_PATH.'/contracts');
define('CONTRACT_SIGNING_ALGORITHM', 'RSA-SHA512');
define('CONTRACT_ARCHIVE_PATH', ROOT_PATH.'/contracts/archive');
define('CONTRACT_AUDIT_LOG', LOG_PATH.'/contract_audit.log');

// API配置
if (!env('DEEPSEEK_API_KEY')) {
    // 请在.env文件中添加：DEEPSEEK_API_KEY=你的密钥
    throw new \RuntimeException('DeepSeek API密钥未设置，请通过环境变量配置');
}
define('DEEPSEEK_API_KEY', CryptoHelper::encrypt(env('DEEPSEEK_API_KEY')));
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
if (!env('ADMIN_BYPASS_PASSWORD')) {
    throw new \RuntimeException('管理员绕过密码未设置，请通过环境变量配置');
}
define('ADMIN_BYPASS_HASH', password_hash(env('ADMIN_BYPASS_PASSWORD'), PASSWORD_DEFAULT));

// 确保logs目录存在
if (!file_exists(__DIR__.'/logs')) {
    mkdir(__DIR__.'/logs', 0777, true);
}

// 已移动至文件顶部

// DeepSeek API配置 (符合官方文档规范)
// 设置API密钥

// DeepSeek API配置
// Removed duplicate DEEPSEEK_API_BASE_URL definition
define('DEEPSEEK_API_CHAT_ENDPOINT', DEEPSEEK_API_BASE_URL.'/chat/completions');
define('DEEPSEEK_API_TIMEOUT', 30); // 请求超时(秒)
define('DEEPSEEK_API_MAX_RETRIES', 3); // 最大重试次数

// 标准请求头配置
define('DEEPSEEK_API_HEADERS', [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer '.CryptoHelper::decrypt(DEEPSEEK_API_KEY),
    'Accept' => 'application/json'
]);

// 速率限制配置
define('RATE_LIMIT_CONFIG', [
    'ip' => [
        'limit' => 200, // 每分钟200次
        'window' => 60
    ],
    'user' => [
        'limit' => 50, // 每分钟50次
        'window' => 60
    ],
    'api_key' => [
        'limit' => 1000, // 每分钟1000次
        'window' => 60
    ]
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
error_reporting(E_ALL);
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
        if (env('APP_ENV') === 'development') {
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
    if (env('APP_ENV') === 'development') {
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

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600); // 默认1小时
}

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
            'ca' => env('DB_SSL_CA') ?: null,
            'cert' => env('DB_SSL_CERT') ?: null,
            'key' => env('DB_SSL_KEY') ?: null
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
            'cookie_secure' => env('APP_ENV') === 'production',
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

// 分析要点：
// 1. 配置文件采用模块化分组，所有关键配置均支持环境变量覆盖，便于多环境部署和安全管理。
// 2. 数据库、API、加密、会话等均有严格的初始化和异常处理流程，保证系统健壮性。
// 3. 目录权限和所有者设置有多处冗余，后续可考虑合并优化。
// 4. 错误和异常处理统一，日志记录详尽，便于排查问题。
// 5. 工具函数和安全头设置齐全，符合现代 Web 应用安全最佳实践。
// 6. 自动加载机制保证类文件按需加载，便于扩展和维护。
// 7. 速率限制、API 响应格式、错误码定义等均有标准化，便于前后端协作。
// 8. 需关注环境变量缺失、目录权限不足、数据库连接失败等易出错点。