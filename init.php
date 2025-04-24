<?php
// 确保vendor自动加载可用
require_once __DIR__ . '/vendor/autoload.php';

// 手动添加libs目录到包含路径
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/libs');

// 初始化基础服务
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// 忽略Redis扩展警告
if (!extension_loaded('redis')) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_WARNING);
}

// 配置加密密钥
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'default_encryption_key_32_chars_long');
define('ENCRYPTION_IV', getenv('ENCRYPTION_IV') ?: 'default_initialization_vector');

// 初始化服务
$db = new Libs\DatabaseHelper();
$logger = new Libs\LogHelper();
$crypto = new Libs\CryptoHelper();

// 初始化模型层
Models\BaseModel::init($db, $logger);

// 设置错误处理
// ... [保留原有错误处理代码]
?>