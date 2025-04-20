<?php

define('ROOT_PATH', __DIR__);
define('ENV_FILE', ROOT_PATH . '/.env');
define('SQL_FILE', ROOT_PATH . '/ai_companion_db.sql');
define('LOG_FILE', ROOT_PATH . '/logs/install.log');

// 安装处理器函数
function handle_installation($postData)
{
    $response = ['success' => false];

    try {
        // 验证必填字段
        $required = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];
        foreach ($required as $field) {
            if (empty($postData[$field])) {
                throw new Exception("请填写所有必填项");
            }
        }

        // 数据库连接测试
        $mysqli = @new mysqli(
            $postData['DB_HOST'],
            $postData['DB_USERNAME'],
            $postData['DB_PASSWORD'],
            '',
            (int) $postData['DB_PORT']
        );

        if ($mysqli->connect_error) {
            throw new Exception("数据库连接失败: " . $mysqli->connect_error);
        }

        // 检查目录权限
        $dirs = [
            ROOT_PATH . '/logs' => 0755,
            ROOT_PATH . '/cache' => 0755,
            ROOT_PATH . '/sessions' => 0755,
        ];
        $dirCheck = check_dirs($dirs);
        foreach ($dirCheck as $dir => $isWritable) {
            if (!$isWritable) {
                throw new Exception("目录不可写: $dir");
            }
        }

        // 导入SQL文件
        if (!file_exists(SQL_FILE)) {
            throw new Exception("找不到数据库初始化脚本: " . SQL_FILE);
        }
        import_sql($mysqli, SQL_FILE);

        $response['success'] = true;
        $response['nextStep'] = 'db_create';

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    die(json_encode($response));
}

// 日志记录函数
function log_install($msg)
{
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// 检查目录权限
function check_dirs($dirs = [])
{
    $results = [];
    foreach ($dirs as $dir => $perm) {
        if (!file_exists($dir)) {
            mkdir($dir, $perm, true);
        }
        $results[$dir] = is_writable($dir);
    }
    return $results;
}

// 引入DatabaseHelper
require_once ROOT_PATH . '/libs/DatabaseHelper.php';
require_once ROOT_PATH . '/install.php';

// ...existing helper functions like check_php_extensions, check_dirs, write_env_file...

// 仅当通过AJAX请求时执行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    handle_installation($_POST);
}