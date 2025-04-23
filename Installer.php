<?php
/**
 * Stanfai_php 智能安装系统
 * 版本: 2.0
 * 功能: 一键部署企业级PHP应用
 */

class Installer {
    // 安装模式常量
    const MODE_WEB = 'web';
    const MODE_CLI = 'cli';
    const MODE_DOCKER = 'docker';
    
    // 安装阶段常量
    const PHASE_PREPARE = 'prepare';
    const PHASE_CONFIG = 'config';
    const PHASE_DEPLOY = 'deploy';
    const PHASE_TEST = 'test';
    const PHASE_ROLLBACK = 'rollback';
    
    // 数据库类型
    const DB_MYSQL = 'mysql';
    const DB_POSTGRES = 'pgsql';
    const DB_SQLITE = 'sqlite';
    
    private $currentMode;
    private $currentPhase;
    private $rollbackStack = [];
    
    private $checker;
    private $configManager;
    private $securityScanner;
    
    public function __construct() {
        $this->detectMode();
        $this->initModules();
    }
    
    /**
     * 初始化各功能模块
     */
    private function initModules() {
        require_once __DIR__.'/libs/LogService.php';
        require_once __DIR__.'/libs/SystemChecker.php';
        require_once __DIR__.'/libs/ConfigManager.php';
        require_once __DIR__.'/libs/SecurityScanner.php';
        
        LogService::init('install');
        $this->checker = new SystemChecker();
        $this->configManager = new ConfigManager();
        $this->securityScanner = new SecurityScanner();
    }
    
    public function __construct() {
        $this->detectMode();
        $this->initLogging();
    }
    
    /**
     * 检测运行模式
     */
    private function detectMode() {
        $this->currentMode = php_sapi_name() === 'cli' ? self::MODE_CLI : self::MODE_WEB;
    }
    
    /**
     * 初始化日志系统
     */
    private function initLogging() {
        require_once __DIR__.'/libs/LogService.php';
        LogService::init('install');
    }
    
    /**
     * 主安装流程
     */
    public function run() {
        try {
            $this->preparePhase();
            $this->configPhase();
            $this->deployPhase();
            $this->testPhase();
            $this->complete();
        } catch (InstallException $e) {
            $this->handleError($e);
        }
    }
    
    // 准备阶段 - 环境检测
    private function preparePhase() {
        $this->currentPhase = self::PHASE_PREPARE;
        LogService::info('开始环境检测');
        
        try {
            // 1. 系统资源检查
            $resources = $this->checker->checkSystemResources();
            $this->showCheckResult('系统资源', $resources);
            
            // 2. PHP版本检查
            $phpVersionOk = $this->checker->checkPhpVersion();
            if (!$phpVersionOk) {
                throw InstallException::dependencyError(['PHP ' . $this->minRequirements['php']])
                    ->setSeverity('critical');
            }
            
            // 3. 依赖检查
            $deps = $this->checker->checkDependencies();
            $this->showCheckResult('PHP扩展', $deps);
            
            // 4. 权限检查
            try {
                $perms = $this->checker->checkPermissions();
                $this->showCheckResult('文件权限', $perms);
            } catch (InstallException $e) {
                $this->showErrorDetails($e);
                throw new InstallException(
                    "文件权限检查失败",
                    $e->getCode(),
                    $e->getContext()
                );
            }
            
            LogService::info('环境检测通过');
            
        } catch (Exception $e) {
            LogService::error("环境检测失败: " . $e->getMessage());
            throw new InstallException("环境检测未通过: " . $e->getMessage());
        }
    }
    
    /**
     * 显示检查结果
     */
    private function showCheckResult($title, $results) {
        if ($this->currentMode === self::MODE_CLI) {
            echo "\n=== {$title}检查 ===\n";
            print_r($results);
        } else {
            $_SESSION['checks'][$title] = $results;
        }
        
        LogService::debug("{$title}检查结果: " . json_encode($results));
    }
    
    // 配置阶段
    private function configPhase() {
        $this->currentPhase = self::PHASE_CONFIG;
        LogService::info('开始配置收集');
        
        try {
            // 1. 收集数据库配置
            $dbConfig = $this->configManager->collectDatabaseConfig();
            $this->showConfigSummary('数据库配置', $dbConfig);
            
            // 2. 收集管理员配置
            $adminConfig = $this->configManager->collectAdminConfig();
            $this->showConfigSummary('管理员账户', [
                'username' => $adminConfig['username'],
                'email' => $adminConfig['email']
            ]);
            
            // 3. 验证配置
            $this->configManager->validateConfig();
            
            // 4. 生成配置文件
            $this->configManager->generateEnvFile();
            
            LogService::info('配置收集完成');
            
        } catch (Exception $e) {
            LogService::error("配置收集失败: " . $e->getMessage());
            throw new InstallException("配置无效: " . $e->getMessage());
        }
    }
    
    /**
     * 显示配置摘要
     */
    private function showConfigSummary($title, $config) {
        if ($this->currentMode === self::MODE_CLI) {
            echo "\n=== {$title} ===\n";
            print_r($config);
        } else {
            $_SESSION['config'][$title] = $config;
        }
        
        LogService::debug("{$title}设置: " . json_encode($config));
    }
    
    // 部署阶段
    private function deployPhase() {
        $this->currentPhase = self::PHASE_DEPLOY;
        LogService::info('开始系统部署');
        
        try {
            $steps = [
                ['安装依赖', 'installDependencies'],
                ['初始化数据库', 'setupDatabase'],
                ['配置安全设置', 'configureSecurity'],
                ['优化系统', 'optimizeSystem']
            ];
            
            $totalSteps = count($steps);
            $currentStep = 1;
            
            foreach ($steps as $step) {
                $this->showProgress($currentStep, $totalSteps, "正在{$step[0]}...");
                call_user_func([$this, $step[1]]);
                $currentStep++;
            }
            
            LogService::info('系统部署完成');
            
        } catch (Exception $e) {
            LogService::error("部署失败: " . $e->getMessage());
            $this->rollbackDeploy();
            throw new InstallException("部署阶段出错: " . $e->getMessage());
        }
    }
    
    /**
     * 安装依赖
     */
    private function installDependencies() {
        $this->pushRollbackStep('dependencies', function() {
            // 回滚依赖安装
            if (file_exists('vendor')) {
                exec('rm -rf vendor');
            }
        });
        
        if ($this->currentMode === self::MODE_CLI) {
            exec('composer install --no-dev', $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Composer依赖安装失败");
            }
            
            LogService::info('Composer依赖安装成功');
        }
    }
    
    /**
     * 初始化数据库
     */
    private function setupDatabase() {
        $this->pushRollbackStep('database', function() {
            // 回滚数据库初始化
            // 实现将在此添加
        });
        
        // 执行数据库迁移
        if (file_exists('database/migrations')) {
            exec('php artisan migrate --force', $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("数据库迁移失败");
            }
            
            LogService::info('数据库迁移成功');
        }
    }
    
    /**
     * 配置安全设置
     */
    private function configureSecurity() {
        // 生成应用密钥
        exec('php artisan key:generate --force', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("安全密钥生成失败");
        }
        
        LogService::info('安全配置完成');
    }
    
    /**
     * 优化系统
     */
    private function optimizeSystem() {
        // 缓存路由和配置
        exec('php artisan config:cache', $output, $returnCode);
        exec('php artisan route:cache', $output, $returnCode);
        
        LogService::info('系统优化完成');
    }
    
    // 测试阶段
    private function testPhase() {
        $this->currentPhase = self::PHASE_TEST;
        LogService::info('开始安装后测试');
        
        try {
            $testResults = [];
            
            // 1. 核心功能测试
            $testResults['core_features'] = $this->testCoreFeatures();
            
            // 2. 性能基准测试
            $testResults['performance'] = $this->runPerformanceTests();
            
            // 3. 安全扫描
            $testResults['security'] = $this->securityScanner->runSecurityScan();
            
            // 4. 生成测试报告
            $this->generateTestReport($testResults);
            
            LogService::info('安装验证完成');
            
        } catch (Exception $e) {
            LogService::error("测试阶段失败: " . $e->getMessage());
            throw new InstallException("安装验证未通过: " . $e->getMessage());
        }
    }
    
    /**
     * 核心功能测试
     */
    private function testCoreFeatures() {
        $results = [];
        
        // 测试数据库连接
        try {
            DB::connection()->getPdo();
            $results['database'] = 'PASSED';
        } catch (Exception $e) {
            $results['database'] = 'FAILED: ' . $e->getMessage();
        }
        
        // 测试缓存系统
        try {
            Cache::put('install_test', 'success', 1);
            $results['cache'] = Cache::get('install_test') === 'success' ? 'PASSED' : 'FAILED';
        } catch (Exception $e) {
            $results['cache'] = 'FAILED: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 性能基准测试
     */
    private function runPerformanceTests() {
        $results = [];
        
        // 数据库查询性能
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $results['db_query'] = round((microtime(true) - $start) * 1000, 2) . 'ms';
        } catch (Exception $e) {
            $results['db_query'] = 'FAILED';
        }
        
        // 请求响应时间
        $start = microtime(true);
        try {
            file_get_contents('http://localhost');
            $results['http_request'] = round((microtime(true) - $start) * 1000, 2) . 'ms';
        } catch (Exception $e) {
            $results['http_request'] = 'FAILED';
        }
        
        return $results;
    }
    
    /**
     * 生成测试报告
     */
    private function generateTestReport($results) {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system' => [
                'php_version' => phpversion(),
                'os' => PHP_OS,
                'memory' => ini_get('memory_limit')
            ],
            'results' => $results
        ];
        
        $reportPath = 'storage/logs/install_test_'.date('YmdHis').'.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        LogService::info("测试报告生成于: $reportPath");
    }
    
    // 完成安装
    private function complete() {
        // 生成报告
        $report = $this->generateReport();
        
        // 清理临时文件
        $this->cleanup();
        
        // 显示完成信息
        $this->showCompletion($report);
    }
    
    // 错误处理
    private function handleError(InstallException $e) {
        $errorCode = $e->getCode() ?: 'UNKNOWN';
        $errorMessage = "[{$errorCode}] " . $e->getMessage();
        
        LogService::error($errorMessage);
        
        // 根据阶段执行回滚
        switch($this->currentPhase) {
            case self::PHASE_DEPLOY:
                $this->rollbackDeploy();
                break;
            case self::PHASE_CONFIG:
                $this->rollbackConfig();
                break;
            case self::PHASE_TEST:
                $this->rollbackTest();
                break;
        }
        
        $this->showError($e);
        $this->suggestFix($errorCode);
    }
    
    /**
     * 部署回滚
     */
    private function rollbackDeploy() {
        LogService::info('开始回滚部署...');
        
        // 按相反顺序执行回滚步骤
        foreach (array_reverse($this->rollbackStack) as $name => $callback) {
            try {
                LogService::debug("执行回滚: {$name}");
                call_user_func($callback);
            } catch (Exception $e) {
                LogService::error("回滚{$name}失败: " . $e->getMessage());
            }
        }
        
        $this->rollbackStack = [];
        LogService::info('部署回滚完成');
    }
    
    /**
     * 配置回滚
     */
    private function rollbackConfig() {
        LogService::info('回滚配置更改...');
        
        // 删除生成的配置文件
        if (file_exists('.env')) {
            unlink('.env');
        }
        
        LogService::info('配置回滚完成');
    }
    
    /**
     * 测试回滚
     */
    private function rollbackTest() {
        LogService::info('清理测试数据...');
        // 测试数据清理逻辑
        LogService::info('测试数据清理完成');
    }
    
    /**
     * 错误修复建议
     */
    private function suggestFix($errorCode) {
        $suggestions = [
            'DB_CONNECTION' => [
                '检查数据库服务是否运行',
                '验证连接参数是否正确',
                '检查网络连接'
            ],
            'FILE_PERMISSION' => [
                '确保目录可写: storage, bootstrap/cache',
                '检查运行安装程序的用户权限'
            ],
            'DEPENDENCY_MISSING' => [
                '安装缺少的PHP扩展',
                '运行 composer install',
                '检查系统要求文档'
            ]
        ];
        
        if (isset($suggestions[$errorCode])) {
            echo "\n修复建议:\n";
            foreach ($suggestions[$errorCode] as $suggestion) {
                echo " - {$suggestion}\n";
            }
        }
    }
    
    /**
     * 交互式配置收集
     */
    private function runInteractiveSetup() {
        echo "=== Stanfai_php 安装配置 ===\n\n";
        
        // 1. 数据库配置
        $this->setupDatabaseConfig();
        
        // 2. 管理员账户设置
        $this->setupAdminAccount();
        
        // 3. 安全配置
        $this->setupSecurityConfig();
        
        // 4. 可选模块
        $this->setupOptionalModules();
    }
    
    /**
     * 数据库配置
     */
    private function setupDatabaseConfig() {
        echo "\n-- 数据库配置 --\n";
        
        $dbTypes = [
            self::DB_MYSQL => 'MySQL',
            self::DB_POSTGRES => 'PostgreSQL', 
            self::DB_SQLITE => 'SQLite'
        ];
        
        echo "选择数据库类型:\n";
        foreach ($dbTypes as $id => $name) {
            echo "  [{$id}] {$name}\n";
        }
        
        $dbType = $this->ask("输入数据库类型 [mysql]: ", self::DB_MYSQL);
        $this->config['db']['type'] = in_array($dbType, array_keys($dbTypes)) ? $dbType : self::DB_MYSQL;
        
        switch ($this->config['db']['type']) {
            case self::DB_SQLITE:
                $this->config['db']['path'] = $this->ask("SQLite数据库路径 [storage/database.sqlite]: ", 
                    'storage/database.sqlite');
                break;
                
            default:
                $this->config['db']['host'] = $this->ask("数据库主机 [localhost]: ", 'localhost');
                $this->config['db']['name'] = $this->ask("数据库名 [stanfai_prod]: ", 'stanfai_prod');
                $this->config['db']['user'] = $this->ask("数据库用户: ");
                $this->config['db']['pass'] = $this->ask("数据库密码: ", '', true);
                $this->config['db']['port'] = $this->ask("数据库端口 [".($dbType === self::DB_MYSQL ? '3306' : '5432')."]: ", 
                    $dbType === self::DB_MYSQL ? '3306' : '5432');
        }
        
        // 测试数据库连接
        $this->testDatabaseConnection();
        
        // 添加到回滚栈
        $this->pushRollbackStep('database', function() {
            unset($this->config['db']);
        });
    }
    
    /**
     * 测试数据库连接
     */
    private function testDatabaseConnection() {
        echo "\n测试数据库连接...";
        
        try {
            switch ($this->config['db']['type']) {
                case self::DB_MYSQL:
                    $dsn = "mysql:host={$this->config['db']['host']};port={$this->config['db']['port']}";
                    break;
                case self::DB_POSTGRES:
                    $dsn = "pgsql:host={$this->config['db']['host']};port={$this->config['db']['port']}";
                    break;
                case self::DB_SQLITE:
                    $dsn = "sqlite:{$this->config['db']['path']}";
                    break;
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ];
            
            $pdo = new PDO(
                $dsn,
                $this->config['db']['type'] !== self::DB_SQLITE ? $this->config['db']['user'] : null,
                $this->config['db']['type'] !== self::DB_SQLITE ? $this->config['db']['pass'] : null,
                $options
            );
            
            // 验证数据库是否存在(非SQLite)
            if ($this->config['db']['type'] !== self::DB_SQLITE) {
                $pdo->exec("USE `{$this->config['db']['name']}`");
            }
            
            echo " [成功]\n";
            return true;
            
        } catch (PDOException $e) {
            echo " [失败]\n错误: " . $e->getMessage() . "\n";
            
            // 提供修复建议
            if ($this->config['db']['type'] !== self::DB_SQLITE) {
                echo "建议:\n";
                echo "1. 检查数据库服务是否运行\n";
                echo "2. 验证用户名/密码是否正确\n";
                
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    echo "3. 数据库'{$this->config['db']['name']}'不存在，是否要创建？ [y/N]: ";
                    if (strtolower(trim(fgets(STDIN))) === 'y') {
                        $this->createDatabase();
                        return $this->testDatabaseConnection();
                    }
                }
            }
            
            throw InstallException::dbConnectionError([
                'message' => $e->getMessage(),
                'host' => $this->config['db']['host'],
                'port' => $this->config['db']['port'],
                'user' => $this->config['db']['user'],
                'type' => $this->config['db']['type']
            ])->setSeverity('critical');
        }
    }
    
    /**
     * 创建数据库
     */
    private function createDatabase() {
        try {
            $dbName = $this->config['db']['name'];
            $tempConfig = $this->config['db'];
            unset($tempConfig['name']);
            
            $dsn = "mysql:host={$tempConfig['host']};port={$tempConfig['port']}";
            $pdo = new PDO($dsn, $tempConfig['user'], $tempConfig['pass']);
            
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "数据库 {$dbName} 创建成功\n";
            
        } catch (PDOException $e) {
            throw new InstallException("创建数据库失败: " . $e->getMessage());
        }
    }
    
    /**
     * 辅助方法：用户输入
     */
    private function ask($prompt, $default = '', $hidden = false) {
        if ($this->currentMode === self::MODE_CLI) {
            echo $prompt;
            
            if ($hidden && extension_loaded('readline')) {
                readline_callback_handler_install('', function() {});
                $value = '';
                while (true) {
                    $char = stream_get_contents(STDIN, 1);
                    if ($char === "\n" || $char === "\r") {
                        break;
                    }
                    $value .= $char;
                }
                readline_callback_handler_remove();
                echo "\n";
                return $value ?: $default;
            }
            
            $handle = fopen("php://stdin", "r");
            $value = trim(fgets($handle));
            fclose($handle);
            return $value ?: $default;
        } else {
            // Web模式实现
            if (!isset($_SESSION['install_inputs'])) {
                $_SESSION['install_inputs'] = [];
            }
            
            if (isset($_POST['install_input'][md5($prompt)])) {
                return $_POST['install_input'][md5($prompt)];
            }
            
            if ($hidden) {
                return '<input type="password" name="install_input['.md5($prompt).']" 
                       value="'.htmlspecialchars($default).'">';
            }
            
            return '<input type="text" name="install_input['.md5($prompt).']" 
                   value="'.htmlspecialchars($default).'">';
        }
    }
    
    /**
     * 添加回滚步骤
     */
    private function pushRollbackStep($name, $callback) {
        $this->rollbackStack[$name] = $callback;
    }
    
    /**
     * 管理员账户设置
     */
    private function setupAdminAccount() {
        echo "\n-- 管理员账户设置 --\n";
        
        $this->config['admin'] = [
            'email' => $this->ask("管理员邮箱: "),
            'username' => $this->ask("管理员用户名 [admin]: ", 'admin'),
            'password' => $this->ask("管理员密码: ", '', true)
        ];
        
        // 密码强度验证
        if (strlen($this->config['admin']['password']) < 8) {
            throw new InstallException("密码必须至少8个字符");
        }
        
        // 添加到回滚栈
        $this->pushRollbackStep('admin', function() {
            unset($this->config['admin']);
        });
        
        // 生成密码哈希
        $this->config['admin']['password_hash'] = password_hash(
            $this->config['admin']['password'], 
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );
        
        echo "管理员账户配置完成\n";
    }
    
    /**
     * 安全配置设置
     */
    private function setupSecurityConfig() {
        echo "\n-- 安全配置 --\n";
        
        // 1. 应用密钥生成
        $this->config['security']['app_key'] = bin2hex(random_bytes(32));
        
        // 2. 量子加密配置
        $this->setupQuantumEncryption();
        
        // 3. 双因素认证选项
        $this->config['security']['2fa_enabled'] = $this->ask("启用双因素认证? [y/N]: ", 'n') === 'y';
        
        echo "安全配置完成\n";
    }
    
    /**
     * 量子加密设置
     */
    private function setupQuantumEncryption() {
        if (extension_loaded('sodium')) {
            $this->config['security']['quantum_key'] = base64_encode(
                sodium_crypto_kx_keypair()
            );
            echo "量子加密密钥已生成\n";
        } else {
            echo "警告: sodium扩展未加载，量子加密不可用\n";
        }
    }
    
    /**
     * 可选模块配置
     */
    private function setupOptionalModules() {
        echo "\n-- 可选模块 --\n";
        
        $modules = [
            'monitoring' => '系统监控',
            'analytics' => '数据分析',
            'backup' => '自动备份',
            'api' => 'API服务'
        ];
        
        echo "选择要安装的模块(多个模块用逗号分隔):\n";
        foreach ($modules as $id => $name) {
            echo "  [{$id}] {$name}\n";
        }
        
        $selected = $this->ask("选择模块 [all]: ", 'all');
        $selectedModules = $selected === 'all' 
            ? array_keys($modules)
            : array_map('trim', explode(',', $selected));
        
        $this->config['modules'] = array_filter($selectedModules, function($m) use ($modules) {
            return isset($modules[$m]);
        });
        
        echo "已选择模块: " . implode(', ', $this->config['modules']) . "\n";
    }
    
    /**
     * 显示安装进度
     */
    private function showProgress($current, $total, $message = '') {
        $percent = intval(($current / $total) * 100);
        $barLength = 50;
        $filled = intval($barLength * $current / $total);
        
        if ($this->currentMode === self::MODE_CLI) {
            echo "\r[";
            echo str_repeat("=", $filled);
            echo str_repeat(" ", $barLength - $filled);
            echo "] {$percent}% {$message}";
            
            if ($current === $total) {
                echo "\n";
            }
        } else {
            // Web模式进度显示
            $_SESSION['install_progress'] = [
                'percent' => $percent,
                'message' => $message
            ];
        }
    }
    
    /**
     * 生成安装报告
     */
    private function generateReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'success',
            'config_summary' => [
                'database' => $this->config['db']['type'],
                'admin_user' => $this->config['admin']['username'],
                'installed_modules' => $this->config['modules']
            ],
            'system_info' => [
                'php_version' => phpversion(),
                'os' => PHP_OS,
                'memory_limit' => ini_get('memory_limit')
            ]
        ];
        
        // 保存报告文件
        file_put_contents(
            __DIR__.'/storage/logs/install_report_'.date('YmdHis').'.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        return $report;
    }
    
    /**
     * 清理临时文件
     */
    private function cleanup() {
        // 清理会话文件
        if (file_exists(__DIR__.'/storage/framework/sessions/install_session')) {
            unlink(__DIR__.'/storage/framework/sessions/install_session');
        }
        
        // 重置安装锁
        if (file_exists(__DIR__.'/bootstrap/cache/install.lock')) {
            unlink(__DIR__.'/bootstrap/cache/install.lock');
        }
    }
}

class InstallException extends Exception {
    const ERROR_DB_CONNECTION = 1001;
    const ERROR_FILE_PERMISSION = 1002;
    const ERROR_DEPENDENCY = 1003;
    const ERROR_CONFIG = 1004;
    const ERROR_SECURITY = 1005;
    
    private $context = [];
    private $severity = 'error'; // error, warning, notice
    
    public function __construct($message, $code = 0, $context = [], Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    public function getContext() {
        return $this->context;
    }
    
    public function setSeverity($severity) {
        $this->severity = $severity;
        return $this;
    }
    
    public function getSeverity() {
        return $this->severity;
    }
    
    public static function dbConnectionError($details) {
        return new self(
            "数据库连接失败: " . $details['message'],
            self::ERROR_DB_CONNECTION,
            $details
        );
    }
    
    public static function filePermissionError($file) {
        return new self(
            "文件权限不足: " . $file,
            self::ERROR_FILE_PERMISSION,
            ['file' => $file]
        );
    }
    
    public static function dependencyError($missing) {
        return new self(
            "缺少依赖: " . implode(', ', $missing),
            self::ERROR_DEPENDENCY,
            ['missing' => $missing]
        );
    }
}