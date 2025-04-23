<?php
/**
 * Stanfai 增强版安装程序
 * 功能：完整环境检测、智能部署、性能优化和质量保障
 */

declare(strict_types=1);

class Installer {
    // 增强的环境检测配置
    const REQUIREMENTS = [
        'php' => '7.4.0',
        'extensions' => ['pdo', 'openssl', 'json', 'mbstring', 'curl'],
        'directories' => [
            'storage/' => ['perms' => 'rwx', 'check' => 'writable'],
            'cache/' => ['perms' => 'rw', 'check' => 'writable'],
            'logs/' => ['perms' => 'rw', 'check' => 'writable']
        ],
        'recommended' => [
            'opcache' => true,
            'redis' => false
        ]
    ];

    // 安装选项
    private $options = [
        'interactive' => true,
        'skip-db' => false,
        'optimize' => true,
        'with-monitoring' => true,
        'parallel' => 4
    ];

    // 运行时数据
    private $config = [];
    private $startTime;

    public function __construct(array $options = []) {
        $this->startTime = microtime(true);
        $this->options = array_merge($this->options, $options);
        $this->showHeader();
        
        if ($this->options['interactive']) {
            $this->runInteractiveSetup();
        } else {
            $this->loadConfig();
        }
    }

    public function run(): void {
        try {
            $this->checkEnvironment();
            $this->installDependencies();
            
            if (!$this->options['skip-db']) {
                $this->setupDatabase();
            }
            
            $this->registerCoreModules();
            
            if ($this->options['optimize']) {
                $this->optimizePerformance();
            }
            
            if ($this->options['with-monitoring']) {
                $this->setupQualityMonitoring();
            }
            
            $this->completeInstallation();
        } catch (RuntimeException $e) {
            $this->handleError($e);
        } finally {
            $this->cleanup();
        }
    }

    private function showHeader(): void {
        echo "========================================\n";
        echo " Stanfai 智能安装程序 (v2.0)\n";
        echo "========================================\n\n";
    }

    private function runInteractiveSetup(): void {
        echo "交互式配置向导\n";
        echo "----------------------------\n";
        
        $this->config['db'] = [
            'host' => $this->ask("数据库主机 [localhost]: ", 'localhost'),
            'name' => $this->ask("数据库名 [stanfai_prod]: ", 'stanfai_prod'),
            'user' => $this->ask("数据库用户: "),
            'pass' => $this->ask("数据库密码: ", '', true)
        ];

        $this->options['optimize'] = $this->confirm("启用性能优化? [Y/n]: ");
        $this->options['with-monitoring'] = $this->confirm("安装质量监控系统? [Y/n]: ");
        $this->options['parallel'] = (int)$this->ask("并行任务数 [4]: ", '4');

        $this->saveConfig();
    }

    private function checkEnvironment(): void {
        $this->step("系统环境检测");
        
        // PHP版本检测
        if (version_compare(PHP_VERSION, self::REQUIREMENTS['php'], '<')) {
            throw new RuntimeException(sprintf(
                "需要PHP %s+, 当前版本: %s", 
                self::REQUIREMENTS['php'], 
                PHP_VERSION
            ));
        }

        // 扩展检测
        $missing = [];
        foreach (self::REQUIREMENTS['extensions'] as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if ($missing) {
            throw new RuntimeException("缺少必要扩展: " . implode(', ', $missing));
        }

        // 目录权限检测
        foreach (self::REQUIREMENTS['directories'] as $dir => $opts) {
            if ($opts['check'] === 'writable' && !is_writable($dir)) {
                throw new RuntimeException("目录不可写: $dir (需要权限: {$opts['perms']})");
            }
        }

        // 推荐配置检测
        foreach (self::REQUIREMENTS['recommended'] as $ext => $required) {
            if (!extension_loaded($ext)) {
                $msg = $required ? "警告" : "建议";
                echo "[$msg] 扩展未加载: $ext\n";
            }
        }
    }

    private function installDependencies(): void {
        $this->step("安装系统依赖");
        
        // Composer依赖
        $this->execWithProgress(
            'composer install --optimize-autoloader --no-dev',
            'Composer依赖安装'
        );

        // NPM依赖
        $this->execWithProgress(
            'npm install && npm run build',
            '前端资源构建'
        );
    }

    private function setupDatabase(): void {
        $this->step("数据库初始化");
        
        $pdo = $this->getPDO();
        $tables = $this->loadSchema();

        foreach ($tables as $name => $schema) {
            $this->info("创建表: $name");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$name` ($schema)");
            
            // 添加示例数据
            if ($name === 'users') {
                $this->seedUsers($pdo);
            }
        }
    }

    private function registerCoreModules(): void {
        $this->step("核心模块注册");
        
        $modules = [
            'Translation' => [
                'parallel' => $this->options['parallel'],
                'cache' => true
            ],
            'QualityMonitor' => [
                'enabled' => $this->options['with-monitoring'],
                'alert_threshold' => 90
            ]
        ];
        
        $this->saveConfigFile('config/modules.php', $modules);
    }

    private function optimizePerformance(): void {
        $this->step("性能优化配置");
        
        // OPcache配置
        if (extension_loaded('opcache')) {
            $this->configureOPcache();
        }

        // 并行任务配置
        $this->configureParallelTasks();

        // 预加载配置
        $this->configurePreloading();
    }

    private function setupQualityMonitoring(): void {
        $this->step("质量监控设置");
        
        $config = [
            'terminology' => [
                'command' => 'npm run report:terminology',
                'schedule' => '0 3 * * *', // 每天3点运行
                'threshold' => 95
            ],
            'realtime' => [
                'command' => 'npm run monitor:quality --live',
                'port' => 8081,
                'alert_channels' => ['slack', 'email']
            ]
        ];
        
        $this->saveConfigFile('config/monitoring.php', $config);
    }

    private function completeInstallation(): void {
        $duration = round(microtime(true) - $this->startTime, 2);
        $this->success("安装成功完成! (耗时: {$duration}s)");
        
        // 生成安装标记
        file_put_contents('storage/installed', date('Y-m-d H:i:s'));
        
        // 显示后续步骤
        echo "\n后续步骤:\n";
        echo "1. 配置Web服务器指向 /public 目录\n";
        echo "2. 访问 http://localhost/admin 完成设置\n";
        echo "3. 运行 cronjob: php cron.php\n\n";
    }

    /* 辅助方法 */
    private function step(string $message): void {
        echo "\n\033[1;34m>>> {$message}\033[0m\n";
    }

    private function info(string $message): void {
        echo "  \033[0;36m• {$message}\033[0m\n";
    }

    private function success(string $message): void {
        echo "\n\033[1;32m✓ {$message}\033[0m\n";
    }

    private function ask(string $question, string $default = '', bool $hidden = false): string {
        echo $question;
        
        if ($hidden && extension_loaded('readline')) {
            readline_callback_handler_install('', function() {});
            $answer = '';
            while (!feof(STDIN)) {
                $char = stream_get_contents(STDIN, 1);
                if ($char === "\n") break;
                $answer .= $char;
                echo '*';
            }
            echo "\n";
            return $answer ?: $default;
        }
        
        $answer = trim(fgets(STDIN));
        return $answer !== '' ? $answer : $default;
    }

    private function confirm(string $question, bool $default = true): bool {
        $answer = $this->ask($question, $default ? 'y' : 'n');
        return strtolower($answer) === 'y';
    }

    private function execWithProgress(string $command, string $label): void {
        $this->info($label);
        $descriptors = [
            0 => STDIN,
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("执行失败: $command");
        }
        
        // 简单进度显示
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) break;
            echo ".";
            sleep(1);
        }
        
        $exitCode = proc_close($process);
        echo "\n";
        
        if ($exitCode !== 0) {
            throw new RuntimeException("{$label}失败 (代码: {$exitCode})");
        }
    }

    private function getPDO(): PDO {
        static $pdo;
        
        if (!$pdo) {
            $dsn = sprintf('mysql:host=%s;dbname=%s',
                $this->config['db']['host'],
                $this->config['db']['name']
            );
            
            $pdo = new PDO($dsn, $this->config['db']['user'], $this->config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
        
        return $pdo;
    }

    private function loadSchema(): array {
        return [
            'users' => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ',
            'translations' => '
                id INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(255) NOT NULL,
                value TEXT NOT NULL,
                lang CHAR(2) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            '
        ];
    }

    private function seedUsers(PDO $pdo): void {
        $stmt = $pdo->prepare('SELECT id FROM users LIMIT 1');
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $pdo->exec("
                INSERT INTO users (username, email, password) VALUES 
                ('admin', 'admin@stanfai.org', '" . password_hash('admin123', PASSWORD_BCRYPT) . "')
            ");
            $this->info("添加默认管理员账户: admin/admin123");
        }
    }

    private function configureOPcache(): void {
        $config = [
            'opcache.enable' => 1,
            'opcache.memory_consumption' => 128,
            'opcache.interned_strings_buffer' => 8,
            'opcache.max_accelerated_files' => 10000,
            'opcache.revalidate_freq' => 60
        ];
        
        foreach ($config as $key => $value) {
            ini_set($key, (string)$value);
        }
        
        $this->info("OPcache 已配置 (内存: 128MB)");
    }

    private function configureParallelTasks(): void {
        $package = json_decode(file_get_contents('package.json'), true) ?: [];
        
        $package['scripts'] = array_merge($package['scripts'] ?? [], [
            'translate' => "node i18n/translate.js --parallel --workers={$this->options['parallel']}",
            'report:terminology' => 'node i18n/quality-check.js --terminology --report=storage/terminology.json',
            'monitor:quality' => 'node i18n/monitor.js --live --threshold=90'
        ]);
        
        file_put_contents('package.json', json_encode($package, JSON_PRETTY_PRINT));
        $this->info("配置并行任务 (workers: {$this->options['parallel']})");
    }

    private function configurePreloading(): void {
        $preload = <<<'PHP'
<?php
// 预加载核心类
$classes = [
    'App\\Core\\Application',
    'App\\Database\\Connection',
    'App\\Translation\\Manager',
    'App\\Security\\Auth'
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        require __DIR__ . "/src/" . str_replace('\\', '/', $class) . ".php";
    }
}
PHP;
        
        file_put_contents('preload.php', $preload);
        ini_set('opcache.preload', 'preload.php');
        $this->info("预加载配置完成 (已加载 " . count(explode("\n", $preload)) . " 个类)");
    }

    private function saveConfigFile(string $path, array $data): void {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($path, '<?php return ' . var_export($data, true) . ';');
    }

    private function saveConfig(): void {
        $this->saveConfigFile('config/install.php', [
            'db' => $this->config['db'],
            'options' => $this->options
        ]);
    }

    private function loadConfig(): void {
        if (!file_exists('config/install.php')) {
            throw new RuntimeException("缺少配置文件: config/install.php");
        }
        
        $config = require 'config/install.php';
        $this->config = $config['db'] ?? [];
        $this->options = array_merge($this->options, $config['options'] ?? []);
    }

    private function handleError(Throwable $e): void {
        $error = sprintf(
            "[%s] %s (%s:%d)",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        
        file_put_contents('storage/install.log', $error . PHP_EOL, FILE_APPEND);
        echo "\n\033[1;31m✗ 安装失败: {$e->getMessage()}\033[0m\n";
        
        if ($this->options['interactive']) {
            echo "查看日志: storage/install.log\n";
            echo "修复问题后重新运行安装程序\n";
        }
    }

    private function cleanup(): void {
        // 清理临时文件等
        if (file_exists('preload.php.tmp')) {
            unlink('preload.php.tmp');
        }
    }
}

// 命令行参数解析
$options = [
    'interactive' => !in_array('--no-interactive', $argv),
    'skip-db' => in_array('--skip-db', $argv),
    'optimize' => !in_array('--no-optimize', $argv),
    'with-monitoring' => !in_array('--no-monitoring', $argv),
    'parallel' => (int)($argv[array_search('--parallel', $argv) + 1] ?? 4)
];

// 执行安装
try {
    (new Installer($options))->run();
} catch (Throwable $e) {
    echo "\n\033[1;31m安装程序异常: {$e->getMessage()}\033[0m\n";
    exit(1);
}