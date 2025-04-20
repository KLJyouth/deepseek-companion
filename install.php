<?php
define('ROOT_PATH', dirname(__FILE__));

// 检查是否已安装
if (file_exists('.env')) {
    die('系统已安装，如需重新安装请删除.env文件');
}

// 安装处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = handle_installation($_POST);
        if ($result['success']) {
            display_success($result);
            exit;
        }
    } catch (Exception $e) {
        display_error($e->getMessage());
    }
}

// 显示安装表单
display_install_form();

/**
 * 处理安装请求
 */
function handle_installation(array $post): array
{
    $required = [
        'db_host', 'db_port', 'db_name', 'db_user', 'db_pass',
        'app_url', 'deepseek_key', 'mail_host', 'mail_port',
        'mail_user', 'mail_pass', 'mail_from'
    ];
    
    foreach ($required as $field) {
        if (empty($post[$field])) {
            throw new Exception("请填写所有必填字段");
        }
    }

    // 生成安全密钥
    $config = [
        'db_host' => $post['db_host'],
        'db_port' => $post['db_port'],
        'db_name' => $post['db_name'],
        'db_user' => $post['db_user'],
        'db_pass' => $post['db_pass'],
        'app_url' => rtrim($post['app_url'], '/'),
        'deepseek_key' => $post['deepseek_key'],
        'jwt_secret' => generate_secure_key(),
        'enc_key' => generate_secure_key(),
        'admin_pass' => generate_secure_key(16),
        'mail_host' => $post['mail_host'],
        'mail_port' => $post['mail_port'],
        'mail_user' => $post['mail_user'],
        'mail_pass' => $post['mail_pass'],
        'mail_from' => $post['mail_from']
    ];

    // 测试数据库连接
    test_db_connection($config);

    // 生成.env文件
    generate_env_file($config);

    return [
        'success' => true,
        'message' => '安装成功！',
        'admin_pass' => $config['admin_pass']
    ];
}

/**
 * 生成.env文件
 */
function generate_env_file(array $config): void
{
    $envContent = <<<EOL
# 数据库配置
DB_HOST={$config['db_host']}
DB_PORT={$config['db_port']}
DB_DATABASE={$config['db_name']}
DB_USERNAME={$config['db_user']}
DB_PASSWORD="{$config['db_pass']}"

# 应用配置
APP_ENV=production
APP_DEBUG=false
APP_URL={$config['app_url']}

# DeepSeek API配置
DEEPSEEK_API_KEY={$config['deepseek_key']}

# 安全配置
JWT_SECRET={$config['jwt_secret']}
ENCRYPTION_KEY={$config['enc_key']}
ADMIN_BYPASS_PASSWORD={$config['admin_pass']}

# WebSocket配置
WS_HOST=0.0.0.0
WS_PORT=9000

# 邮件配置
MAIL_HOST={$config['mail_host']}
MAIL_PORT={$config['mail_port']}
MAIL_USERNAME={$config['mail_user']}
MAIL_PASSWORD="{$config['mail_pass']}"
MAIL_FROM={$config['mail_from']}
MAIL_FROM_NAME="AI Companion"

EOL;

    if (!file_put_contents('.env', $envContent)) {
        throw new Exception('无法写入.env文件，请检查目录权限');
    }
    chmod('.env', 0640);
}

/**
 * 生成安全密钥
 */
function generate_secure_key(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * 测试数据库连接
 */
function test_db_connection(array $config): void
{
    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name'],
        $config['db_port']
    );
    
    if ($db->connect_error) {
        throw new Exception("数据库连接失败: " . $db->connect_error);
    }
    $db->close();
}

/**
 * 显示安装表单
 */
function display_install_form(): void
{
    $defaults = [
        'db_host' => 'localhost',
        'db_port' => '3306',
        'app_url' => 'http://localhost',
        'mail_port' => '465',
        'mail_from' => 'no-reply@example.com'
    ];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>AI Companion 安装向导</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; }
            .section { margin-bottom: 30px; padding: 15px; background: #f5f5f5; border-radius: 5px; }
            .section-title { margin-top: 0; color: #333; }
            button { background: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
            button:hover { background: #45a049; }
            .error { color: red; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <h1>AI Companion 安装向导</h1>
        
        <form method="post">
            <div class="section">
                <h2 class="section-title">数据库配置</h2>
                
                <div class="form-group">
                    <label for="db_host">数据库主机</label>
                    <input type="text" id="db_host" name="db_host" value="<?= $defaults['db_host'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_port">数据库端口</label>
                    <input type="text" id="db_port" name="db_port" value="<?= $defaults['db_port'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">数据库名称</label>
                    <input type="text" id="db_name" name="db_name" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">数据库用户名</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">数据库密码</label>
                    <input type="password" id="db_pass" name="db_pass" required>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">应用配置</h2>
                
                <div class="form-group">
                    <label for="app_url">应用URL</label>
                    <input type="text" id="app_url" name="app_url" value="<?= $defaults['app_url'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="deepseek_key">DeepSeek API Key</label>
                    <input type="password" id="deepseek_key" name="deepseek_key" required>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">邮件配置</h2>
                
                <div class="form-group">
                    <label for="mail_host">SMTP主机</label>
                    <input type="text" id="mail_host" name="mail_host" required>
                </div>
                
                <div class="form-group">
                    <label for="mail_port">SMTP端口</label>
                    <input type="text" id="mail_port" name="mail_port" value="<?= $defaults['mail_port'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="mail_user">SMTP用户名</label>
                    <input type="text" id="mail_user" name="mail_user" required>
                </div>
                
                <div class="form-group">
                    <label for="mail_pass">SMTP密码</label>
                    <input type="password" id="mail_pass" name="mail_pass" required>
                </div>
                
                <div class="form-group">
                    <label for="mail_from">发件人邮箱</label>
                    <input type="text" id="mail_from" name="mail_from" value="<?= $defaults['mail_from'] ?>" required>
                </div>
            </div>
            
            <button type="submit">开始安装</button>
        </form>
    </body>
    </html>
    <?php
}

/**
 * 显示错误信息
 */
function display_error(string $message): void
{
    echo '<div class="error">错误: ' . htmlspecialchars($message) . '</div>';
}

/**
 * 显示成功信息
 */
function display_success(array $result): void
{
    // 执行安装后处理
    try {
        post_install_setup();
        $verification = verify_installation();
    } catch (Exception $e) {
        display_error('安装后处理失败: ' . $e->getMessage());
        return;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>安装成功</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
            .success { color: green; margin-bottom: 15px; }
            .important { background: #fff8e1; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            .verification { margin: 20px 0; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; }
            .verification ul { margin: 10px 0; padding-left: 20px; }
            .verification li { margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <h1>安装成功</h1>
        
        <div class="success"><?= $result['message'] ?></div>
        
        <div class="important">
            <h3>重要信息</h3>
            <p>请保存以下管理员紧急密码（仅在系统维护时使用）：</p>
            <p><strong><?= $result['admin_pass'] ?></strong></p>
            <p>此密码仅显示一次，请妥善保存！</p>
        </div>

        <div class="verification">
            <h3>安装验证结果</h3>
            <ul>
                <?php foreach ($verification as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <p><a href="<?= $_POST['app_url'] ?>">访问应用</a></p>
    </body>
    </html>
    <?php
}

/**
 * 安装后安全处理
 */
function post_install_setup(): void
{
    // 设置.env文件权限
    if (file_exists('.env')) {
        chmod('.env', 0640);
    } else {
        throw new Exception('.env文件不存在');
    }

    // 重命名安装脚本
    if (file_exists('install.php')) {
        if (!rename('install.php', 'install.php.bak')) {
            throw new Exception('无法重命名安装脚本');
        }
    }

    // 更新.gitignore
    $gitignore = file_exists('.gitignore') ? file_get_contents('.gitignore') : '';
    $patterns = ['/.env', '/install.php.bak'];
    
    foreach ($patterns as $pattern) {
        if (strpos($gitignore, $pattern) === false) {
            $gitignore .= PHP_EOL . $pattern;
        }
    }
    
    file_put_contents('.gitignore', $gitignore);

    // 部署安全功能
    $securityScript = __DIR__.'/scripts/deploy_security.sh';
    if (file_exists($securityScript)) {
        chmod($securityScript, 0700);
        $output = [];
        exec("sudo $securityScript 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new Exception('安全部署失败: '.implode("\n", $output));
        }
    } else {
        throw new Exception('安全部署脚本不存在');
    }
}

/**
 * 验证安装结果
 */
function verify_installation(): array
{
    $results = [];
    
    // 检查.env文件
    if (!file_exists('.env')) {
        throw new Exception('.env文件未创建');
    }
    $results[] = '✓ 配置文件(.env)已生成';

    // 检查文件权限
    if (substr(sprintf('%o', fileperms('.env')), -4) != '0640') {
        $results[] = '⚠ .env文件权限未正确设置';
    } else {
        $results[] = '✓ .env文件权限已设置为0640';
    }

    // 检查安装脚本是否已重命名
    if (file_exists('install.php')) {
        $results[] = '⚠ 安装脚本未重命名(install.php)';
    } else {
        $results[] = '✓ 安装脚本已重命名为install.php.bak';
    }

    return $results;
}