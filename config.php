<?php
namespace Libs;

// ========== 环境变量加载 ==========
define('ROOT_PATH', realpath(__DIR__));
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
        if (function_exists('putenv')) {
            @putenv("$k=$v");
        }
    }
}

// ========== 工具函数 ==========
function env($key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    if (defined($key)) return constant($key);
    return $default;
}
function define_env_constant($key, $default = null) {
    if (!defined($key)) {
        define($key, env($key, $default));
    }
}

// ========== 路径与目录 ==========
define('LOG_PATH', ROOT_PATH . '/logs');
define('SESSION_PATH', ROOT_PATH . '/sessions');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('CONTRACT_STORAGE_PATH', ROOT_PATH . '/contracts');
define('CONTRACT_ARCHIVE_PATH', ROOT_PATH . '/contracts/archive');

// ========== 目录权限与初始化 ==========
define('REQUIRED_DIRS', [
    LOG_PATH => '0750',
    SESSION_PATH => '0750',
    CACHE_PATH => '0750',
    CONTRACT_STORAGE_PATH => '0750',
    CONTRACT_ARCHIVE_PATH => '0750'
]);
foreach (REQUIRED_DIRS as $dir => $perms) {
    if (!file_exists($dir)) {
        mkdir($dir, octdec($perms), true);
        // chown($dir, 'www-data'); // 如需设置所有者请根据实际web用户调整
        //ADMIN_BYPASS_PASSWORD=your_secure_password_here
    }
    if (!is_writable($dir)) {
        chmod($dir, octdec($perms));
    }
}

// ========== 加密配置 ==========
require_once ROOT_PATH . '/libs/CryptoHelper.php';
define('ENCRYPTION_METHOD', env('ENCRYPTION_METHOD', 'quantum'));
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', bin2hex(random_bytes(16))));
define('ENCRYPTION_IV', env('ENCRYPTION_IV', bin2hex(random_bytes(8))));
define('QUANTUM_KEY_ROTATION', env('QUANTUM_KEY_ROTATION', 3600));
define('QUANTUM_MAX_KEY_VERSIONS', env('QUANTUM_MAX_KEY_VERSIONS', 3));
try {
    $key = ENCRYPTION_KEY;
    $iv = ENCRYPTION_IV;
    if (strlen($key) === 32 && (strlen($iv) === 12 || strlen($iv) === 16)) {
        \Libs\CryptoHelper::init($key, $iv);
    }
} catch (\Throwable $e) {
    error_log('加密组件初始化失败: ' . $e->getMessage());
}

// ========== JWT 配置 ==========
define('JWT_SECRET_KEY', env('JWT_SECRET_KEY', 'your-secure-key-here'));
define('JWT_EXPIRE_TIME', env('JWT_EXPIRE_TIME', 3600));

// ========== 会话安全配置 ==========
$sessionSecure = env('APP_ENV') === 'production';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $sessionSecure ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
$sessionPath = SESSION_PATH;
if (!file_exists($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
if (!is_writable($sessionPath)) {
    die("会话目录不可写: $sessionPath");
}
ini_set('session.save_path', $sessionPath);
ini_set('session.save_handler', 'files');
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600);
}

// ========== 数据库配置 ==========
$dbName = env('DB_NAME') ?: env('DB_DATABASE');
$dbUser = env('DB_USER') ?: env('DB_USERNAME');
$dbPass = env('DB_PASS') ?: env('DB_PASSWORD');
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
define('DB_CONNECT_TIMEOUT', 5);
define('DB_READ_TIMEOUT', 30);

// ========== 管理员绕过密码 ==========
if (!env('ADMIN_BYPASS_PASSWORD')) {
    throw new \RuntimeException('管理员绕过密码未设置，请通过环境变量配置');
}
define('ADMIN_BYPASS_HASH', password_hash(env('ADMIN_BYPASS_PASSWORD'), PASSWORD_DEFAULT));

// ========== 签名与API配置 ==========
define('SIGNATURE_KEY', env('SIGNATURE_KEY', bin2hex(random_bytes(32))));
define('SIGNATURE_TIMEOUT', 300);
if (!env('DEEPSEEK_API_KEY')) {
    throw new \RuntimeException('DeepSeek API密钥未设置，请通过环境变量配置');
}
define('DEEPSEEK_API_KEY', \Libs\CryptoHelper::encrypt(env('DEEPSEEK_API_KEY')));
define('DEEPSEEK_API_BASE_URL', 'https://api.deepseek.com/v1');
define('DEEPSEEK_API_CHAT_ENDPOINT', DEEPSEEK_API_BASE_URL . '/chat/completions');
define('DEEPSEEK_API_TIMEOUT', 30);
define('DEEPSEEK_API_MAX_RETRIES', 3);
define('DEEPSEEK_API_HEADERS', [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer ' . \Libs\CryptoHelper::decrypt(DEEPSEEK_API_KEY),
    'Accept' => 'application/json'
]);

// ========== 速率限制 ==========
define('RATE_LIMIT_CONFIG', [
    'ip' => ['limit' => 200, 'window' => 60],
    'user' => ['limit' => 50, 'window' => 60],
    'api_key' => ['limit' => 1000, 'window' => 60]
]);

// ========== API响应与错误码 ==========
define('API_RESPONSE_FORMAT', [
    'success' => false,
    'code' => 0,
    'message' => '',
    'data' => null,
    'timestamp' => time()
]);
define('API_ERROR_CODES', [
    1 => '系统错误',
    2 => '服务不可用',
    3 => '参数错误',
    1000 => '未授权',
    1001 => '令牌过期',
    1002 => '权限不足',
    2000 => '业务逻辑错误',
    2001 => '数据不存在'
]);

// ========== CSRF保护 ==========
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRE', 3600);

// ========== 错误与异常处理 ==========
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/app_errors.log');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Error', E_WARNING => 'Warning', E_PARSE => 'Parse Error', E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error', E_CORE_WARNING => 'Core Warning', E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning', E_USER_ERROR => 'User Error', E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice', E_RECOVERABLE_ERROR => 'Recoverable Error', E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    $message = sprintf("[%s] %s: %s in %s on line %d", date('Y-m-d H:i:s'), $errorType, $errstr, $errfile, $errline);
    error_log($message);
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
        if (env('APP_ENV') === 'development') echo "<pre>$message</pre>";
        else readfile(ROOT_PATH . '/error_pages/500.html');
        exit(1);
    }
    return true;
});
set_exception_handler(function($e) {
    $message = sprintf("[%s] Exception: %s in %s:%d\nStack Trace:\n%s",
        date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    error_log($message);
    if (!headers_sent()) header('HTTP/1.1 500 Internal Server Error');
    if (env('APP_ENV') === 'development') echo "<pre>$message</pre>";
    else readfile(ROOT_PATH . '/error_pages/500.html');
    exit(1);
});

// ========== 自动加载 ==========
spl_autoload_register(function($class) {
    $file = ROOT_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require $file;
});

// ========== 工具函数 ==========
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) return array_map('sanitizeInput', $data);
    $data = trim($data);
    switch ($type) {
        case 'int': return (int) filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float': return (float) filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'email': return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url': return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default: return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
function redirect($url, $statusCode = 303) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!headers_sent()) {
        header("Location: $url", true, $statusCode);
        exit;
    }
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
    exit;
}

// ========== 安全头设置 ==========
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; img-src \'self\' data:; font-src \'self\' cdn.jsdelivr.net');
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');

// ========== 安全配置增强 ==========
define('SECURITY_CONFIG', [
    'password_min_length' => 12,
    'password_require_special' => true,
    'password_require_numbers' => true,
    'password_require_uppercase' => true,
    'max_login_attempts' => 5,
    'lockout_time' => 1800,
    'session_regenerate_time' => 300,
    'audit_log_retention_days' => 90,
    'backup_retention_days' => 30,
    'api_key_rotation_days' => 30,
]);

// 增加密钥轮换配置
define('KEY_ROTATION', [
    'enabled' => true,
    'interval' => 86400,  // 24小时
    'versions_to_keep' => 3,
    'algorithm' => 'aes-256-gcm'
]);

// ========== 数据库连接初始化 ==========
function initializeDatabaseConnection() {
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        throw new \Exception('数据库配置不完整');
    }
    global $conn;
    $conn = new \mysqli();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        throw new \Exception(sprintf("数据库连接失败(主机:%s 用户:%s): %s", DB_HOST, DB_USER, $conn->connect_error));
    }
    if (!$conn || $conn->connect_error) {
        throw new \Exception("数据库连接无效: " . ($conn->connect_error ?? '未知错误'));
    }
    if (!$conn->set_charset(DB_CHARSET)) {
        throw new \Exception("设置字符集失败: " . $conn->error);
    }
    if (DB_SSL) {
        $sslConfig = [
            'ca' => env('DB_SSL_CA') ?: null,
            'cert' => env('DB_SSL_CERT') ?: null,
            'key' => env('DB_SSL_KEY') ?: null
        ];
        foreach ($sslConfig as $type => $path) {
            if ($path && !file_exists($path)) throw new \Exception("SSL证书文件不存在: $type=$path");
            if ($path && !is_readable($path)) throw new \Exception("SSL证书文件不可读: $type=$path");
        }
        $conn->ssl_set($sslConfig['key'], $sslConfig['cert'], $sslConfig['ca'], null, null);
    }
    if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
        throw new \Exception(sprintf("数据库连接失败(主机:%s 用户:%s): %s", DB_HOST, DB_USER, $conn->connect_error));
    }
    $dbHelper = new \Libs\DatabaseHelper(DB_TABLE_PREFIX);
    if (!$conn->query("SELECT 1")) {
        throw new \Exception("数据库查询测试失败: " . $conn->error);
    }
}

// ========== 应用初始化 ==========
try {
    initializeDatabaseConnection();
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => SESSION_TIMEOUT,
            'cookie_secure' => env('APP_ENV') === 'production',
            'cookie_httponly' => true
        ]);
    }
} catch (\Exception $e) {
    $errorMsg = sprintf("[%s] %s: %s\n%s", date('Y-m-d H:i:s'), get_class($e), $e->getMessage(), $e->getTraceAsString());
    error_log($errorMsg);
    file_put_contents(LOG_PATH . '/app_errors.log', $errorMsg, FILE_APPEND);
    if (!headers_sent()) header('HTTP/1.1 503 Service Unavailable');
    include ROOT_PATH . '/maintenance.html';
    exit;
}

// 增加会话安全配置
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
ini_set('session.cache_limiter', 'nocache');