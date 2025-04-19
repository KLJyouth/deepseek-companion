<?php
/**
 * stanfai-司单服Ai智能安全法务 一键安装部署页面
 * 自动检测环境、生成.env、初始化数据库、创建管理员账号
 * 适用于首次部署和开发环境快速初始化
 */

define('ROOT_PATH', __DIR__);
define('ENV_FILE', ROOT_PATH . '/.env');
define('SQL_FILE', ROOT_PATH . '/ai_companion_db.sql');
define('LOG_FILE', ROOT_PATH . '/logs/install.log');

function log_install($msg) {
    file_put_contents(LOG_FILE, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
}

function check_php_extensions($required = []) {
    $missing = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) $missing[] = $ext;
    }
    return $missing;
}

function check_dirs($dirs = []) {
    $results = [];
    foreach ($dirs as $dir => $perm) {
        if (!file_exists($dir)) {
            mkdir($dir, $perm, true);
        }
        $results[$dir] = is_writable($dir);
    }
    return $results;
}

function write_env_file($data) {
    // 读取已存在的.env变量，保留未被覆盖的内容
    $existing = [];
    if (file_exists(ENV_FILE)) {
        foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $existing[$k] = $v;
        }
    }
    // 合并新变量，优先使用新值
    $merged = array_merge($existing, $data);
    $lines = [];
    foreach ($merged as $k => $v) {
        $lines[] = "$k=$v";
    }
    file_put_contents(ENV_FILE, implode(PHP_EOL, $lines));
}

function import_sql($mysqli, $file) {
    $sql = file_get_contents($file);
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($queries as $query) {
        if ($query) $mysqli->query($query);
    }
}

function render_form($error = '', $success = '', $defaults = []) {
    $defaults = array_merge([
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'ai_companion',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '',
        'APP_URL' => 'http://localhost:8080',
        'ADMIN_EMAIL' => '',
        'ADMIN_PASSWORD' => '',
    ], $defaults);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>一键安装部署 - stanfai-司单服Ai智能安全法务</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f8f9fa; }
            .install-container { max-width: 600px; margin: 40px auto; }
        </style>
    </head>
    <body>
    <div class="install-container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4>一键安装部署</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="post">
                    <h6 class="mb-3">数据库配置</h6>
                    <div class="mb-2">
                        <label>主机</label>
                        <input type="text" name="DB_HOST" class="form-control" value="<?= htmlspecialchars($defaults['DB_HOST']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>端口</label>
                        <input type="text" name="DB_PORT" class="form-control" value="<?= htmlspecialchars($defaults['DB_PORT']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>数据库名</label>
                        <input type="text" name="DB_DATABASE" class="form-control" value="<?= htmlspecialchars($defaults['DB_DATABASE']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>用户名</label>
                        <input type="text" name="DB_USERNAME" class="form-control" value="<?= htmlspecialchars($defaults['DB_USERNAME']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>密码</label>
                        <input type="password" name="DB_PASSWORD" class="form-control" value="<?= htmlspecialchars($defaults['DB_PASSWORD']) ?>">
                    </div>
                    <h6 class="mt-4 mb-3">应用配置</h6>
                    <div class="mb-2">
                        <label>应用URL</label>
                        <input type="text" name="APP_URL" class="form-control" value="<?= htmlspecialchars($defaults['APP_URL']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>管理员邮箱</label>
                        <input type="email" name="ADMIN_EMAIL" class="form-control" value="<?= htmlspecialchars($defaults['ADMIN_EMAIL']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>管理员密码</label>
                        <input type="password" name="ADMIN_PASSWORD" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">开始安装</button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3 text-muted">
            <small>© <?= date('Y') ?> 广西港妙科技有限公司</small>
        </div>
    </div>
    </body>
    </html>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','APP_URL','ADMIN_EMAIL','ADMIN_PASSWORD'];
    foreach ($required as $k) {
        if (empty($_POST[$k])) {
            render_form('请填写所有必填项', '', $_POST);
            exit;
        }
    }
    $db_host = $_POST['DB_HOST'];
    $db_port = $_POST['DB_PORT'];
    $db_name = $_POST['DB_DATABASE'];
    $db_user = $_POST['DB_USERNAME'];
    $db_pass = $_POST['DB_PASSWORD'];
    $app_url = $_POST['APP_URL'];
    $admin_email = $_POST['ADMIN_EMAIL'];
    $admin_pass = $_POST['ADMIN_PASSWORD'];

    // 检查PHP扩展
    $missing_ext = check_php_extensions(['mysqli','openssl','json','mbstring']);
    if ($missing_ext) {
        render_form('缺少PHP扩展: '.implode(', ', $missing_ext), '', $_POST);
        exit;
    }

    // 检查目录权限
    $dirs = [
        ROOT_PATH.'/logs' => 0755,
        ROOT_PATH.'/cache' => 0755,
        ROOT_PATH.'/sessions' => 0755,
        ROOT_PATH.'/contracts' => 0755,
    ];
    $dir_check = check_dirs($dirs);
    foreach ($dir_check as $dir => $ok) {
        if (!$ok) {
            render_form("目录不可写: $dir", '', $_POST);
            exit;
        }
    }

    // 测试数据库连接
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, '', (int)$db_port);
    if ($mysqli->connect_error) {
        render_form('数据库连接失败: '.$mysqli->connect_error, '', $_POST);
        exit;
    }
    // 创建数据库
    $mysqli->query("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4");
    $mysqli->select_db($db_name);

    // 导入SQL
    if (!file_exists(SQL_FILE)) {
        render_form('找不到数据库初始化脚本: '.SQL_FILE, '', $_POST);
        exit;
    }
    import_sql($mysqli, SQL_FILE);

    // 写入.env
    $env_data = [
        'DB_HOST' => $db_host,
        'DB_PORT' => $db_port,
        'DB_DATABASE' => $db_name,
        'DB_USERNAME' => $db_user,
        'DB_PASSWORD' => $db_pass,
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => $app_url,
        'JWT_SECRET' => bin2hex(random_bytes(32)),
        'ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
        'ADMIN_EMAIL' => $admin_email,
    ];
    write_env_file($env_data);

    // 创建管理员账号
    $admin_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare("INSERT IGNORE INTO users (username, password, email, role, status) VALUES ('admin', ?, ?, 'admin', 1)");
    $stmt->bind_param('ss', $admin_hash, $admin_email);
    $stmt->execute();

    log_install("安装完成，管理员: $admin_email");

    render_form('', '安装成功！请删除 install.php 并刷新页面登录。', []);
    exit;
}

// 首次访问，检测环境
$missing_ext = check_php_extensions(['mysqli','openssl','json','mbstring']);
$dirs = [
    ROOT_PATH.'/logs' => 0755,
    ROOT_PATH.'/cache' => 0755,
    ROOT_PATH.'/sessions' => 0755,
    ROOT_PATH.'/contracts' => 0755,
];
$dir_check = check_dirs($dirs);

$error = '';
if ($missing_ext) $error .= '缺少PHP扩展: '.implode(', ', $missing_ext).'. ';
foreach ($dir_check as $dir => $ok) {
    if (!$ok) $error .= "目录不可写: $dir. ";
}
if (file_exists(ENV_FILE)) $error .= '.env已存在，如需重新安装请先删除.env。';

render_form($error, '', []);
