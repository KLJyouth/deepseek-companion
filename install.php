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

        $response['success'] = true;
        $response['nextStep'] = 'db_create';

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    die(json_encode($response));
}

// 仅当通过AJAX请求时执行
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) {
    handle_installation($_POST);
}

function log_install($msg)
{
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
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
    $db = DatabaseHelper::getInstance();
    
    return $db->transaction(function($conn) use ($file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new Exception("无法读取SQL文件: $file");
        }

        // 预处理SQL语句
        $sql = preg_replace_callback(
            '/CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS)/i',
            function($matches) {
                return 'CREATE TABLE IF NOT EXISTS ';
            },
            $sql
        );
        $sql = preg_replace('/CREATE\s+VIEW\s+/i', 'CREATE OR REPLACE VIEW ', $sql);
        
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        $successCount = 0;
        
        foreach ($queries as $query) {
            if (empty($query)) continue;
            
            if (!$conn->query($query)) {
                throw new Exception("SQL执行失败: {$conn->error}\nSQL: {$query}");
            }
            $successCount++;
        }
        
        log_install("成功执行 {$successCount} 条SQL语句");
        return true;
    });
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
        <title>stanfai-司单服Ai智能安全法务 - 智能安装</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #0d1020 60%, #1a2233 100%);
                min-height: 100vh;
                font-family: 'Segoe UI', 'Roboto', 'Arial', sans-serif;
                color: #e3eafc;
                overflow-x: hidden;
            }
            .install-container {
                max-width: 480px;
                margin: 7vh auto 0 auto;
                background: rgba(30,40,60,0.93);
                border-radius: 18px;
                box-shadow: 0 8px 32px 0 rgba(31,38,135,0.37);
                padding: 2.5rem 2rem 2rem 2rem;
                position: relative;
                z-index: 2;
            }
            .install-title {
                font-size: 2.3rem;
                font-weight: 700;
                letter-spacing: 2px;
                text-align: center;
                margin-bottom: 1.5rem;
                color: #fff;
                text-shadow: 0 2px 12px #43cea2;
            }
            .form-label { color: #b0c7f9; }
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
                background: #050a18;
                justify-content: center;
                align-items: center;
                flex-direction: column;
            }
            #space-bg-canvas {
                position: absolute;
                top: 0; left: 0; width: 100vw; height: 100vh;
                z-index: 0;
                pointer-events: none;
            }
            #sbLogoAnim {
                width: 160px;
                height: 160px;
                border-radius: 50%;
                box-shadow: 0 0 32px #43cea2, 0 0 8px #1e88e5;
                margin-bottom: 2rem;
                background: #000;
                z-index: 2;
            }
            .install-progress {
                color: #b0c7f9;
                font-size: 1.2rem;
                margin-top: 1.5rem;
                text-align: center;
                letter-spacing: 1px;
                z-index: 2;
            }
            .install-error {
                color: #ff6b6b;
                font-weight: bold;
                margin-top: 1.5rem;
                text-align: center;
                z-index: 2;
            }
            @media (max-width: 600px) {
                .install-container { padding: 1.2rem 0.5rem; }
                #sbLogoAnim { width: 100px; height: 100px; }
            }
        </style>
    </head>
    <body>
        <div class="install-container" id="installFormContainer">
            <div class="install-title">stanfai-司单服Ai智能安全法务 智能安装</div>
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
            <canvas id="space-bg-canvas"></canvas>
            <video id="sbLogoAnim" src="src/sb-logo.mp4" autoplay loop muted playsinline></video>
            <div class="install-progress" id="installProgressText">正在安装，请稍候...</div>
            <div class="install-error" id="installErrorText" style="display:none;"></div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <script>
        // 太空舱俯瞰地球动画
        function initSpaceGlobe() {
            const canvas = document.getElementById('space-bg-canvas');
            if (!canvas) return;
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
            renderer.setClearColor(0x050a18, 1);
            renderer.setSize(window.innerWidth, window.innerHeight);

            const scene = new THREE.Scene();

            // 星空背景
            const starGeometry = new THREE.BufferGeometry();
            const starCount = 1200;
            const starVertices = [];
            for (let i = 0; i < starCount; i++) {
                const r = 100 + Math.random() * 200;
                const theta = Math.random() * 2 * Math.PI;
                const phi = Math.acos(2 * Math.random() - 1);
                starVertices.push(
                    r * Math.sin(phi) * Math.cos(theta),
                    r * Math.sin(phi) * Math.sin(theta),
                    r * Math.cos(phi)
                );
            }
            starGeometry.setAttribute('position', new THREE.Float32BufferAttribute(starVertices, 3));
            const starMaterial = new THREE.PointsMaterial({ color: 0xffffff, size: 0.7, transparent: true, opacity: 0.8 });
            const stars = new THREE.Points(starGeometry, starMaterial);
            scene.add(stars);

            // 地球体
            const earthGeometry = new THREE.SphereGeometry(1, 64, 64);
            const earthTexture = new THREE.TextureLoader().load('https://cdn.jsdelivr.net/gh/StanFai/earth-assets/earth-night.jpg');
            const earthMaterial = new THREE.MeshPhongMaterial({
                map: earthTexture,
                shininess: 40,
                specular: 0x43cea2,
                emissive: 0x1e88e5,
                emissiveIntensity: 0.18
            });
            const earth = new THREE.Mesh(earthGeometry, earthMaterial);
            earth.position.set(0, -0.2, 0);
            scene.add(earth);

            // 太空舱窗户（圆形遮罩）
            const windowGeometry = new THREE.CircleGeometry(2.1, 128);
            const windowMaterial = new THREE.MeshBasicMaterial({ color: 0x111a2a, opacity: 0.6, transparent: true, side: THREE.DoubleSide });
            const windowMesh = new THREE.Mesh(windowGeometry, windowMaterial);
            windowMesh.position.set(0, 0, 1.5);
            scene.add(windowMesh);

            // 光源
            const light = new THREE.PointLight(0xffffff, 1.2, 100);
            light.position.set(5, 3, 5);
            scene.add(light);
            scene.add(new THREE.AmbientLight(0x1e88e5, 0.5));

            // 相机设置为俯瞰视角
            const camera = new THREE.PerspectiveCamera(60, window.innerWidth/window.innerHeight, 0.1, 1000);
            camera.position.set(0, 2.5, 3.5);
            camera.lookAt(0, 0, 0);

            // 动画
            function animate() {
                earth.rotation.y += 0.003; // 地球自转
                stars.rotation.y += 0.0005;
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
            var errorText = document.getElementById('installErrorText');
            var video = document.getElementById('sbLogoAnim');

            installForm.addEventListener('submit', function(e) {
                e.preventDefault();
                formContainer.style.display = 'none';
                mask.style.display = 'flex';
                progressText.innerHTML = "正在安装，请稍候...";
                errorText.style.display = 'none';
                video.currentTime = 0;
                video.play();
                initSpaceGlobe();

                // 提交安装请求
                var formData = new FormData(installForm);
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data && data.success) {
                        progressText.innerHTML = "安装完成，正在跳转...";
                        setTimeout(function() {
                            window.location.href = 'install_result.php';
                        }, 1200);
                    } else {
                        progressText.innerHTML = "";
                        errorText.innerHTML = "安装出现错误，请重试";
                        errorText.style.display = 'block';
                        setTimeout(function() {
                            mask.style.display = 'none';
                            formContainer.style.display = '';
                        }, 2500);
                    }
                })
                .catch(() => {
                    progressText.innerHTML = "";
                    errorText.innerHTML = "安装出现错误，请重试";
                    errorText.style.display = 'block';
                    setTimeout(function() {
                        mask.style.display = 'none';
                        formContainer.style.display = '';
                    }, 2500);
                });
            });
        });
        </script>
    </body>
    </html>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检测AJAX请求
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $required = ['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','APP_URL','ADMIN_EMAIL','ADMIN_PASSWORD'];
    foreach ($required as $k) {
        if (empty($_POST[$k])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'error' => '请填写所有必填项']));
            }
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
    
    // 检查数据库是否存在及可访问
    if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        render_form('数据库创建失败: '.$mysqli->error, '', $_POST);
        exit;
    }
    
    if (!$mysqli->select_db($db_name)) {
        render_form('无法选择数据库: '.$mysqli->error, '', $_POST);
        exit;
    }
    
    // 验证表权限
    if (!$mysqli->query("CREATE TABLE IF NOT EXISTS __install_test (id INT)")) {
        render_form('数据库表创建权限不足: '.$mysqli->error, '', $_POST);
        exit;
    }
    $mysqli->query("DROP TABLE IF EXISTS __install_test");

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