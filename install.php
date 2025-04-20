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
    // 只为没有 IF NOT EXISTS 的 CREATE TABLE 添加 IF NOT EXISTS
    $sql = preg_replace_callback(
        '/CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i',
        function($matches) {
            return 'CREATE TABLE IF NOT EXISTS ';
        },
        $sql
    );
    // 将所有 CREATE VIEW 替换为 CREATE OR REPLACE VIEW，避免视图已存在报错
    $sql = preg_replace('/CREATE\s+VIEW\s+/i', 'CREATE OR REPLACE VIEW ', $sql);
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AI伴侣 - 一键安装</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #0f2027, #2c5364 80%);
                min-height: 100vh;
                font-family: 'Segoe UI', 'Roboto', 'Arial', sans-serif;
                color: #e3eafc;
                overflow-x: hidden;
            }
            .install-container {
                max-width: 480px;
                margin: 6vh auto 0 auto;
                background: rgba(30,40,60,0.92);
                border-radius: 18px;
                box-shadow: 0 8px 32px 0 rgba(31,38,135,0.37);
                padding: 2.5rem 2rem 2rem 2rem;
                position: relative;
                z-index: 2;
            }
            .install-title {
                font-size: 2.2rem;
                font-weight: 700;
                letter-spacing: 2px;
                text-align: center;
                margin-bottom: 1.5rem;
                color: #fff;
                text-shadow: 0 2px 8px #1e88e5;
            }
            .form-label {
                color: #b0c7f9;
            }
            .btn-install {
                background: linear-gradient(90deg, #1e88e5 60%, #43cea2 100%);
                border: none;
                color: #fff;
                font-weight: 600;
                font-size: 1.1rem;
                border-radius: 8px;
                box-shadow: 0 2px 8px #1e88e5;
                transition: background 0.3s, box-shadow 0.3s;
            }
            .btn-install:hover {
                background: linear-gradient(90deg, #43cea2 20%, #1e88e5 100%);
                box-shadow: 0 4px 16px #43cea2;
            }
            .install-anim-mask {
                display: none;
                position: fixed;
                z-index: 9999;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(10,20,40,0.98);
                justify-content: center;
                align-items: center;
                flex-direction: column;
            }
            #globe-bg {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                width: 100vw; height: 100vh;
                z-index: 1;
                pointer-events: none;
            }
            #sbLogoAnim {
                width: 180px;
                height: 180px;
                border-radius: 50%;
                box-shadow: 0 0 32px #43cea2, 0 0 8px #1e88e5;
                margin-bottom: 2rem;
                background: #000;
            }
            .install-progress {
                color: #b0c7f9;
                font-size: 1.2rem;
                margin-top: 1.5rem;
                text-align: center;
                letter-spacing: 1px;
            }
            @media (max-width: 600px) {
                .install-container { padding: 1.2rem 0.5rem; }
                #sbLogoAnim { width: 120px; height: 120px; }
            }
        </style>
    </head>
    <body>
        <div id="globe-bg"></div>
        <div class="install-container" id="installFormContainer">
            <div class="install-title">AI伴侣 - 一键安装</div>
            <form id="installForm" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">数据库主机</label>
                    <input type="text" class="form-control" name="db_host" required value="localhost">
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库用户名</label>
                    <input type="text" class="form-control" name="db_user" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库密码</label>
                    <input type="password" class="form-control" name="db_pass" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">数据库名称</label>
                    <input type="text" class="form-control" name="db_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">管理员邮箱</label>
                    <input type="email" class="form-control" name="admin_email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">管理员密码</label>
                    <input type="password" class="form-control" name="admin_pass" required minlength="8">
                </div>
                <button type="submit" class="btn btn-install w-100 py-2 mt-3">立即安装</button>
            </form>
        </div>
        <div class="install-anim-mask" id="install-anim-mask">
            <canvas id="globe-canvas" style="position:absolute;top:0;left:0;width:100vw;height:100vh;z-index:0;"></canvas>
            <video id="sbLogoAnim" src="src/sb-logo.mp4" autoplay loop muted playsinline></video>
            <div class="install-progress" id="installProgressText">正在安装，请稍候...</div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <script>
        // 3D地球动画
        function initGlobe() {
            const canvas = document.getElementById('globe-canvas');
            if (!canvas) return;
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
            renderer.setClearColor(0x0f2027, 0.0);
            renderer.setSize(window.innerWidth, window.innerHeight);

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(60, window.innerWidth/window.innerHeight, 0.1, 1000);
            camera.position.z = 3.2;

            // 地球体
            const geometry = new THREE.SphereGeometry(1, 64, 64);
            const texture = new THREE.TextureLoader().load('https://cdn.jsdelivr.net/gh/StanFai/earth-assets/earth-night.jpg');
            const material = new THREE.MeshPhongMaterial({
                map: texture,
                shininess: 30,
                specular: 0x43cea2,
                emissive: 0x1e88e5,
                emissiveIntensity: 0.15
            });
            const earth = new THREE.Mesh(geometry, material);
            scene.add(earth);

            // 光源
            const light = new THREE.PointLight(0x43cea2, 1.2, 100);
            light.position.set(5, 3, 5);
            scene.add(light);
            scene.add(new THREE.AmbientLight(0x1e88e5, 0.5));

            // 动画
            function animate() {
                earth.rotation.y += 0.002;
                renderer.render(scene, camera);
                requestAnimationFrame(animate);
            }
            animate();

            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        }

        // 安装流程
        document.addEventListener('DOMContentLoaded', function() {
            var installForm = document.getElementById('installForm');
            var formContainer = document.getElementById('installFormContainer');
            var mask = document.getElementById('install-anim-mask');
            var progressText = document.getElementById('installProgressText');
            var video = document.getElementById('sbLogoAnim');

            installForm.addEventListener('submit', function(e) {
                e.preventDefault();
                formContainer.style.display = 'none';
                mask.style.display = 'flex';
                video.currentTime = 0;
                video.play();
                initGlobe();

                // 提交安装请求
                var formData = new FormData(installForm);
                fetch('install_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data && data.success) {
                        progressText.innerHTML = "安装完成，正在跳转...";
                        setTimeout(function() {
                            window.location.href = 'install_result.php';
                        }, 1200);
                    } else {
                        progressText.innerHTML = "安装失败：" + (data && data.error ? data.error : "未知错误");
                        setTimeout(function() {
                            mask.style.display = 'none';
                            formContainer.style.display = '';
                        }, 2500);
                    }
                })
                .catch(() => {
                    progressText.innerHTML = "安装过程中发生错误，请重试。";
                    setTimeout(function() {
                        mask.style.display = 'none';
                        formContainer.style.display = '';
                    }, 2500);
                });

                // 轮询进度（如有install_status.php可用）
                // function checkInstallStatus() {
                //     fetch('install_status.php')
                //         .then(resp => resp.json())
                //         .then(data => {
                //             if (data && data.status === 'done') {
                //                 mask.style.display = 'none';
                //                 window.location.href = 'install_result.php';
                //             } else {
                //                 setTimeout(checkInstallStatus, 1500);
                //             }
                //         })
                //         .catch(() => setTimeout(checkInstallStatus, 2000));
                // }
                // checkInstallStatus();
            });
        });
        </script>
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
?>
