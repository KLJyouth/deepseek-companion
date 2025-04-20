<?php
declare(strict_types=1);

namespace Libs;

require_once __DIR__ . '/CryptoHelper.php';
require_once __DIR__ . '/DatabaseHelper.php';

final class Bootstrap {
    const MODE_PRIMARY = 'primary';
    const MODE_FALLBACK = 'fallback';

    private static \Services\SystemMonitorService $monitor;
    private static array $initStages = [
        'preflight',
        'dependencyCheck',
        'configValidation',
        'dbConnection',
        'cryptoInit',
        'initSystemMonitor',
        'middlewares',
        'webSocket'
    ];

    /** @var mixed 应用实例（如Slim\App等） */
    private static $app = null;

    /**
     * 注入应用实例
     * @param mixed $app
     */
    public static function setApp($app): void {
        self::$app = $app;
    }

    public static function initialize(): void {
        try {
            self::runInitialization(self::MODE_PRIMARY);
        } catch (\Throwable $e) {
            error_log('主模式初始化失败: '.$e->getMessage());
            self::handleFallbackMode($e);
        }
    }

    /**
     * @param array{cert:string,key:string,ca?:string} $sslConfig
     */
    public static function validateSSLCertificates(array $sslConfig): bool {
        $missing = array_filter($sslConfig, fn($file) => !file_exists($file));
        
        if($missing) {
            error_log('缺失的SSL文件: ' . implode(', ', array_keys($missing)));
            return false;
        }
        
        return openssl_x509_check_private_key(
            file_get_contents($sslConfig['cert']),
            file_get_contents($sslConfig['key'])
        );
    }

    private static function runInitialization(string $mode): void {
        foreach (self::$initStages as $stage) {
            $method = 'stage'.ucfirst($stage);
            if (!self::$method($mode)) {
                throw new \Exception("初始化阶段 {$stage} 失败");
            }
        }
        self::cacheInitStatus();
    }

    private static function stagePreflight($mode) {
        $cachePath = __DIR__.'/../cache/init_status.json';
        
        if ($mode === self::MODE_FALLBACK) {
            return true; // 备用模式跳过缓存检查
        }

        if (file_exists($cachePath)) {
            $cache = json_decode(file_get_contents($cachePath), true);
            $valid = isset($cache['version']) 
                  && $cache['version'] === 1
                  && $cache['checksums']['config'] === md5_file(__DIR__.'/../config.php')
                  && $cache['checksums']['bootstrap'] === md5_file(__FILE__);

            if ($valid && (time() - $cache['last_init']) < 3600) {
                error_log('[PREFLIGHT] 使用缓存初始化状态');
                return true;
            }
        }
        
        return true; // 继续执行完整检查流程
    }

    private static function stageDbConnection($mode) {
        try {
            // 创建mysqli连接
            $conn = new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new \Exception("MySQL连接失败: " . $conn->connect_error);
            }
            
            // 设置字符集
            if (!$conn->set_charset(DB_CHARSET)) {
                throw new \Exception("设置字符集失败: " . $conn->error);
            }
            
            // 初始化数据库助手单例
            DatabaseHelper::getInstance();
            
            // 验证数据库版本和兼容性
            $version = $conn->server_version;
            $versionStr = $conn->get_server_info();
            $minVersion = '5.7.8';
            if (version_compare($versionStr, $minVersion, '<')) {
                throw new \Exception("不兼容的MySQL版本（需要{$minVersion}+，当前版本：{$versionStr}）");
            }
            
            // MySQL 8+ 特定优化
            $sqlMode = version_compare($versionStr, '8.0.0', '>=') 
                ? "STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
                : "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION";
            
            if (!$conn->query("SET SESSION sql_mode='{$sqlMode}'")) {
                error_log("[DB CONNECTION] 设置SQL模式失败: " . $conn->error);
            }
            
            error_log("[DB CONNECTION] 已连接MySQL服务器，版本：{$versionStr} (OpenSSL: ".OPENSSL_VERSION_TEXT.")");
            
            error_log('[DB CONNECTION] 数据库连接成功');
            return true;
            
        } catch (\Exception $e) {
            error_log('[DB CONNECTION] 连接失败: ' . $e->getMessage());
            throw $e;
        }
    }

    private static function stageCryptoInit($mode) {
        try {
            // 详细记录加密配置
            error_log('[CRYPTO INIT] 开始加密初始化');
            error_log('[CRYPTO INIT] 加密方法: '.(defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : '未定义'));

            if (defined('ENCRYPTION_METHOD') && ENCRYPTION_METHOD === 'quantum') {
                error_log('[CRYPTO INIT] 正在初始化量子加密...');
                try {
                    require_once __DIR__ . '/QuantumCryptoHelper.php';
                    QuantumCryptoHelper::init();
                    
                    // 量子加密健康检查
                    $testData = 'health_check_'.microtime(true);
                    $encrypted = QuantumCryptoHelper::encrypt($testData);
                    $decrypted = QuantumCryptoHelper::decrypt($encrypted);
                    
                    if ($decrypted !== $testData) {
                        throw new \Exception('量子加密验证失败');
                    }
                    
                    error_log('[CRYPTO INIT] 量子加密初始化成功');
                    return true;
                } catch (\Exception $e) {
                    error_log('[CRYPTO INIT] 量子加密初始化失败(尝试传统加密): '.$e->getMessage());
                    // 继续尝试传统加密
                }
            }

            // 传统加密初始化
            error_log('[CRYPTO INIT] 正在初始化传统加密...');
            if (!defined('ENCRYPTION_KEY') || strlen(ENCRYPTION_KEY) !== 32) {
                $msg = "ENCRYPTION_KEY必须为32字节，当前长度: ".(defined('ENCRYPTION_KEY') ? strlen(ENCRYPTION_KEY) : '未定义');
                error_log('[CRYPTO INIT] '.$msg);
                return true; // 降级运行
            }
            if (!defined('ENCRYPTION_IV') || strlen(ENCRYPTION_IV) !== 16) {
                $msg = "ENCRYPTION_IV必须为16字节，当前长度: ".(defined('ENCRYPTION_IV') ? strlen(ENCRYPTION_IV) : '未定义');
                error_log('[CRYPTO INIT] '.$msg);
                return true; // 降级运行
            }

            try {
                CryptoHelper::init(ENCRYPTION_KEY, ENCRYPTION_IV);
                
                // 传统加密健康检查
                $health = CryptoHelper::healthCheck();
                error_log('[CRYPTO INIT] 传统加密健康检查结果: '.print_r($health, true));
                
                if ($health['status'] !== 'healthy') {
                    error_log('[CRYPTO INIT] 传统加密健康检查失败: '.($health['error'] ?? '未知错误'));
                    return true; // 降级运行
                }
                
                error_log('[CRYPTO INIT] 传统加密初始化成功');
                return true;
            } catch (\Exception $e) {
                error_log('[CRYPTO INIT] 传统加密初始化失败(降级运行): ' . $e->getMessage());
                return true; // 降级运行
            }
        } catch (\Exception $e) {
            error_log('[CRYPTO INIT] 加密初始化失败(降级运行): ' . $e->getMessage());
            return true; // 降级运行
        }
    }



    private static function stageConfigValidation($mode) {
        // 修改SESSION_TIMEOUT检查
        if (!defined('SESSION_TIMEOUT')) {
            define('SESSION_TIMEOUT', 3600); // 设置默认值
        }
        
        if (SESSION_TIMEOUT < 300) {
            throw new \Exception("SESSION_TIMEOUT配置值不合法（最小值：300秒）");
        }
        
        // 增强的数据库主机验证逻辑
        $dbHost = DB_HOST;
        error_log('[CONFIG VALIDATION] 正在验证数据库主机: ' . $dbHost);
        
        // 特殊处理本地环境
        if (in_array(strtolower($dbHost), ['localhost', '127.0.0.1'])) {
            error_log('[CONFIG VALIDATION] 检测到本地环境主机地址');
            if (!extension_loaded('pdo_mysql')) {
                throw new \Exception("本地环境需要pdo_mysql扩展支持");
            }
            return true;
        }
        
        // 分步验证并记录失败原因
        $isValid = false;
        $failureReasons = [];
        
        if (filter_var($dbHost, FILTER_VALIDATE_IP)) {
            $isValid = true;
        } else {
            $failureReasons[] = '非有效IP地址';
            if (checkdnsrr($dbHost, 'A')) {
                $isValid = true;
            } else {
                $failureReasons[] = 'DNS解析失败';
            }
        }
        
        // 最终验证失败处理
        if (!$isValid) {
            $errorDetails = implode(', ', $failureReasons);
            error_log('[CONFIG VALIDATION] 数据库主机验证失败: ' . $errorDetails);
            throw new \Exception("数据库主机配置无效 ({$dbHost}): " . $errorDetails);
        }
        
        // 使用mysqli进行连接测试
        $testConn = @new \mysqli($dbHost, DB_USER, DB_PASS);
        if ($testConn->connect_error) {
            $errorMsg = '[CONFIG VALIDATION] 数据库连接测试失败: ' . $testConn->connect_error;
            error_log($errorMsg);
            throw new \Exception("数据库连接测试失败: " . $testConn->connect_error);
        }
        
        error_log('[CONFIG VALIDATION] 配置验证通过');
        return true;
    }

    private static function stageDependencyCheck($mode) {
        $score = 0;
        $totalChecks = 0;
    
        // 结构化依赖配置
        $dependencies = [
            'files' => [
                __DIR__.'/CryptoHelper.php',
                __DIR__.'/DatabaseHelper.php',
                __DIR__.'/../config.php'
            ],
            'dirs' => [
                'logs' => 0777,
                'sessions' => 0777,
                'cache' => 0755
            ],
            'extensions' => [
                'openssl',
                'pdo_mysql',
                'json'
            ],
            'constants' => [
                'DB_HOST', 'DB_USER', 'DB_PASS',
                'ENCRYPTION_KEY', 'ENCRYPTION_IV'
            ]
        ];
    
        // 文件检查
        foreach ($dependencies['files'] as $file) {
            $totalChecks++;
            if (file_exists($file)) {
                $score++;
                error_log("[DEP CHECK] 文件存在: {$file}");
            } else {
                throw new \Exception("关键文件缺失: {$file}");
            }
        }
    
        // 目录检查
        foreach ($dependencies['dirs'] as $dir => $perm) {
            $totalChecks++;
            $fullPath = __DIR__."/../{$dir}";
            
            if (!file_exists($fullPath)) {
                if (!mkdir($fullPath, $perm, true)) {
                    throw new \Exception("目录创建失败: {$dir}");
                }
                error_log("[DEP CHECK] 目录已创建: {$dir} 权限: ".decoct($perm));
            } else {
                $currentPerm = fileperms($fullPath) & 0777;
                if ($currentPerm !== $perm) {
                    if (chmod($fullPath, $perm)) {
                        error_log("[DEP CHECK] 权限修复成功: {$dir} {$currentPerm}=>{$perm}");
                    } else {
                        throw new \Exception("目录权限修复失败: {$dir} 当前权限: ".decoct($currentPerm));
                    }
                }
            }
    
            if (!is_writable($fullPath) || !is_executable($fullPath)) {
                $effectivePerm = is_dir($fullPath) ? $perm | 0111 : $perm;
                if (chmod($fullPath, $effectivePerm)) {
                    error_log("[DEP CHECK] 强制设置可写权限: {$dir} ".decoct($effectivePerm));
                } else {
                    throw new \Exception("目录不可写且修复失败: {$dir}");
                }
            }
            $score++;
            error_log("[DEP CHECK] 目录验证通过: {$dir} 最终权限: ".decoct(fileperms($fullPath) & 0777));
        }
    
        // 扩展检查
        foreach ($dependencies['extensions'] as $ext) {
            $totalChecks++;
            if (!extension_loaded($ext)) {
                throw new \Exception("缺少必要扩展: {$ext}");
            }
            $score++;
        }
    
        // 常量检查
        foreach ($dependencies['constants'] as $const) {
            $totalChecks++;
            if (!defined($const)) {
                throw new \Exception("未定义常量: {$const}");
            }
            $score++;
        }
    
        if ($score !== $totalChecks) {
            throw new \Exception("依赖检查未通过 ({$score}/{$totalChecks})");
        }
        return true;
    }

    private static function initSystemMonitor() {
        self::$monitor = new \Services\SystemMonitorService();
        return true;
    }
    
    private static function stageMiddlewares($mode) {
        try {
            // 加载核心中间件
            $middlewares = [
                new \Middlewares\AuthMiddleware(),
                new \Middlewares\RateLimitMiddleware(), // 添加速率限制
                new \Middlewares\SecurityMiddleware()
            ];
            if (!self::$app) {
                throw new \Exception('应用实例(app)未注入，无法加载中间件');
            }
            foreach ($middlewares as $middleware) {
                self::$app->addMiddleware($middleware);
            }
            error_log('[MIDDLEWARES] 中间件加载完成');
            return true;
        } catch (\Exception $e) {
            error_log('[MIDDLEWARES] 中间件加载失败: ' . $e->getMessage());
            return false;
        }
    }

    private static function cacheInitStatus() {
        $cacheData = [
            'version' => 1,
            'last_init' => time(),
            'checksums' => [
                'config' => md5_file(__DIR__.'/../config.php'),
                'bootstrap' => md5_file(__FILE__)
            ],
            'environment' => [
                'php_version' => PHP_VERSION,
                'extensions' => get_loaded_extensions()
            ],
            'mode' => self::MODE_PRIMARY
        ];

        $cachePath = __DIR__.'/../cache/init_status.json';
        $jsonData = json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (!is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0755, true);
        }
        
        $result = file_put_contents($cachePath, $jsonData, LOCK_EX);
        if ($result === false) {
            error_log('[CACHE] 初始化状态缓存失败，路径: ' . $cachePath);
            throw new \Exception("无法写入缓存文件");
        }
    }

    private static function handleFallbackMode($exception) {
        try {
            self::runInitialization(self::MODE_FALLBACK);
            error_log('已切换到备用初始化模式');
        } catch (\Exception $fbException) {
            self::logCriticalFailure($fbException);
            throw new \Exception("系统初始化完全失败: ".$fbException->getMessage());
        }
    }

    private static function stageWebSocket($mode) {
        if ($mode !== self::MODE_PRIMARY) {
            return true; // 备用模式不启动WebSocket
        }
        
        try {
            $wsPort = getenv('WS_PORT') ?: 8080;
            $wsService = new \Services\WebSocketService();
            $monitor = new \Services\SystemMonitorService(); // 修正：不传递参数
            
            $server = \Ratchet\Server\IoServer::factory(
                new \Ratchet\Http\HttpServer(
                    new \Ratchet\WebSocket\WsServer($wsService)
                ),
                $wsPort
            );
            
            // 每5秒广播一次监控数据
            $server->loop->addPeriodicTimer(5, function() use ($monitor) {
                $monitor->broadcastMetrics();
            });
            
            // 后台运行
            $server->run();
            return true;
        } catch (\Exception $e) {
            error_log('[WEBSOCKET] 启动失败: ' . $e->getMessage());
            return false;
        }
    }

    private static function logCriticalFailure($exception) {
        $logContent = date('[Y-m-d H:i:s] ')."CRITICAL: ".$exception->getMessage().PHP_EOL;
        file_put_contents(__DIR__.'/../logs/init_failures.log', $logContent, FILE_APPEND);
    }
}