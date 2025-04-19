<?php
// 确保核心库文件先加载
require_once __DIR__.'/libs/DatabaseHelper.php';
require_once __DIR__.'/libs/RedirectHelper.php';
require_once __DIR__.'/controllers/LoginController.php';

use Libs\DatabaseHelper;
use Controllers\LoginController;
use Libs\CryptoHelper;
use Libs\RedirectHelper;

/**
 * 登录系统初始化器
 */
class LoginInitializer {
    private static $instance = null;
    private $dbHelper;
    private $loginController;
    
    private function __construct() {
        $this->initialize();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initialize() {
        try {
            // 1. 基础检查
            $this->checkRequirements();
            
            // 2. 初始化数据库
            $this->initDatabase();
            
            // 3. 初始化控制器
            $this->initController();
            
            // 4. 初始化会话
            $this->initSession();
            
        } catch (Exception $e) {
            error_log("登录初始化失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function checkRequirements() {
        $requiredFiles = [
            'config.php',
            'libs/CryptoHelper.php',
            'libs/DatabaseHelper.php',
            'libs/SanitizeHelper.php',
            'controllers/LoginController.php'
        ];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                throw new Exception("必要文件缺失: $file");
            }
            require_once $file;
        }
    }
    
    private function initDatabase() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }
        $conn->set_charset(DB_CHARSET);
        
        $this->dbHelper = new DatabaseHelper($conn, 'ac_');
        
        if (!$this->dbHelper->testConnection()) {
            throw new Exception("数据库连接测试失败");
        }
    }
    
    private function initController() {
        $this->loginController = new LoginController($this->dbHelper);
        
        // 验证加密功能
        if (CryptoHelper::encrypt('test') === false) {
            throw new Exception("加密功能初始化失败");
        }
    }
    
    private function initSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
    
    public function getDbHelper() {
        return $this->dbHelper;
    }
    
    public function getLoginController() {
        return $this->loginController;
    }
}

// 初始化登录系统
try {
    $initializer = LoginInitializer::getInstance();
    $dbHelper = $initializer->getDbHelper();
    $loginController = $initializer->getLoginController();
    
    // 如果已登录则跳转到首页
    if (!empty($_SESSION['user_id'])) {
// 引入 RedirectHelper 类，该类应包含重定向功能
require_once __DIR__.'/libs/RedirectHelper.php';
// 使用 RedirectHelper 类的静态方法进行重定向
require_once __DIR__.'/libs/RedirectHelper.php';
\Libs\RedirectHelper::redirect('admin.php');
    }
    
    // 处理登录请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // 验证CSRF令牌
            if (empty($_POST['csrf_token']) || !CryptoHelper::validateCsrfToken($_POST['csrf_token'])) {
                throw new Exception("安全验证失败，请刷新页面重试");
            }
            
            // 验证输入
            $username = isset($_POST['username']) ? Libs\SanitizeHelper::sanitize($_POST['username'], 'string') : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $remember = isset($_POST['remember']);
            $totpCode = isset($_POST['totp_code']) ? $_POST['totp_code'] : null;
            
            if (empty($username) || empty($password)) {
                throw new Exception("用户名和密码不能为空");
            }

            // 调用登录控制器
            $result = $loginController->login($username, $password, $remember, $totpCode);
            
            // 处理2FA要求
            if (isset($result['requires_2fa']) && $result['requires_2fa']) {
                $_SESSION['2fa_pending'] = true;
                $_SESSION['2fa_user_id'] = $result['user_id'];
                Libs\RedirectHelper::redirect('2fa.php');
                exit;
            }

            // 登录成功，重定向
            $returnUrl = isset($_GET['return_url']) && !empty($_GET['return_url']) ? 
                filter_var($_GET['return_url'], FILTER_SANITIZE_URL) : 'admin.php';
                
            if (parse_url($returnUrl, PHP_URL_HOST)) {
                $returnUrl = 'admin.php'; // 如果包含主机名则重置为默认页
            }
            
            Libs\RedirectHelper::redirect($returnUrl);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // 只生成一次CSRF令牌
    if (empty($_SESSION['csrf_token'])) {
        $csrfToken = CryptoHelper::generateCsrfToken();
    } else {
        $csrfToken = $_SESSION['csrf_token'];
    }
    
} catch (Exception $e) {
    error_log("系统初始化错误: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('<div class="alert alert-danger">系统初始化失败，请联系管理员</div>');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI伴侣 - 登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <!-- 品牌Logo -->
                        <div class="text-center mb-4">
                            <i class="bi bi-robot text-primary" style="font-size: 3rem;"></i>
                            <h2 class="mt-2">AI伴侣管理平台</h2>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 登录表单 -->
                        <form method="POST" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <?php if (defined('ADMIN_BYPASS_HASH')): ?>
                            <div class="mb-3 admin-bypass-field" style="display:none;">
                                <label for="admin_bypass" class="form-label text-danger">
                                    <i class="bi bi-shield-lock"></i> 管理员紧急访问密码
                                </label>
                                <input type="password" class="form-control" id="admin_bypass" name="admin_bypass">
                                <small class="text-muted">仅限授权人员使用</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">密码</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="8" maxlength="64" required>
                                </div>
                                <div class="form-text">
                                    密码必须包含:
                                    <span id="length" class="text-muted">至少8位</span>,
                                    <span id="letter" class="text-muted">大小写字母</span>,
                                    <span id="number" class="text-muted">和数字</span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                        <label class="form-check-label" for="remember">记住我</label>
                                    </div>
                                </div>
                                <?php if (defined('ADMIN_BYPASS_HASH')): ?>
                                <div class="col text-end">
                                    <a href="#" class="text-decoration-none small" id="toggleAdmin">
                                        <i class="bi bi-shield"></i> 管理员入口
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> 登录
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <a href="forgot_password.php" class="text-decoration-none">忘记密码?</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 页脚 -->
                    <div class="card-footer text-center py-3">
                        <small class="text-muted">© <?php echo date('Y'); ?> AI伴侣 版权所有</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密码强度实时验证
        const password = document.getElementById('password');
        const length = document.getElementById('length');
        const letter = document.getElementById('letter');
        const number = document.getElementById('number');
        
        password.addEventListener('input', function() {
            const value = this.value;
            // 验证长度
            if(value.length >= 8) {
                length.classList.remove('text-muted');
                length.classList.add('text-success');
            } else {
                length.classList.remove('text-success');
                length.classList.add('text-muted');
            }
            
            // 验证字母
            if(/[A-Z]/.test(value) && /[a-z]/.test(value)) {
                letter.classList.remove('text-muted');
                letter.classList.add('text-success');
            } else {
                letter.classList.remove('text-success');
                letter.classList.add('text-muted');
            }
            
            // 验证数字
            if(/\d/.test(value)) {
                number.classList.remove('text-muted');
                number.classList.add('text-success');
            } else {
                number.classList.remove('text-success');
                number.classList.add('text-muted');
            }
        });
        
        <?php if (defined('ADMIN_BYPASS_HASH')): ?>
        // 管理员入口切换
        document.getElementById('toggleAdmin').addEventListener('click', function(e) {
            e.preventDefault();
            const field = document.querySelector('.admin-bypass-field');
            field.style.display = field.style.display === 'none' ? 'block' : 'none';
        });
        <?php endif; ?>
    </script>
</body>
</html>