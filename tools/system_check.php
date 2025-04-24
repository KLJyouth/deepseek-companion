<?php
class SystemCheck {
    private $requirements = [
        'extensions' => [
            'pdo' => 'PDO扩展用于数据库连接',
            'pdo_mysql' => 'PDO MySQL驱动用于MySQL数据库支持',
            'mbstring' => 'Mbstring扩展用于字符串处理',
            'json' => 'JSON扩展用于数据序列化'
        ],
        'php_version' => '8.1.0',
        'writable_dirs' => [
            'logs',
            'cache',
            'storage'
        ]
    ];

    private $results = [];

    public function check(): array {
        $this->checkPHPVersion();
        $this->checkExtensions();
        $this->checkDirectories();
        $this->checkDatabaseConnection();
        return $this->results;
    }

    private function checkPHPVersion(): void {
        $current = PHP_VERSION;
        $required = $this->requirements['php_version'];
        $this->results['php_version'] = [
            'required' => $required,
            'current' => $current,
            'status' => version_compare($current, $required, '>='),
            'message' => version_compare($current, $required, '>=') 
                ? "PHP版本检查通过" 
                : "需要PHP {$required}或更高版本"
        ];
    }

    private function checkExtensions(): void {
        foreach ($this->requirements['extensions'] as $ext => $description) {
            $loaded = extension_loaded($ext);
            $this->results['extensions'][$ext] = [
                'required' => true,
                'loaded' => $loaded,
                'description' => $description,
                'message' => $loaded 
                    ? "{$ext}扩展已加载" 
                    : "{$ext}扩展未加载 - {$description}"
            ];
        }
    }

    private function checkDirectories(): void {
        foreach ($this->requirements['writable_dirs'] as $dir) {
            $path = dirname(__DIR__) . '/' . $dir;
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);
            
            if (!$exists) {
                @mkdir($path, 0777, true);
            }
            
            $this->results['directories'][$dir] = [
                'path' => $path,
                'exists' => file_exists($path),
                'writable' => $writable,
                'message' => $writable 
                    ? "{$dir}目录可写" 
                    : "{$dir}目录不可写或不存在"
            ];
        }
    }

    private function checkDatabaseConnection(): void {
        try {
            if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
                throw new RuntimeException("PDO或PDO_MYSQL扩展未安装");
            }

            $dsn = "mysql:host=localhost;port=3306";
            $conn = new PDO($dsn, 'root', '');
            $this->results['database'] = [
                'status' => true,
                'message' => "数据库连接成功"
            ];
        } catch (Exception $e) {
            $this->results['database'] = [
                'status' => false,
                'message' => "数据库连接失败: " . $e->getMessage()
            ];
        }
    }

    public function displayResults(): void {
        echo "\n系统需求检查结果:\n";
        echo "==================\n\n";

        // PHP版本检查
        echo "PHP版本:\n";
        echo "  需求: {$this->results['php_version']['required']}\n";
        echo "  当前: {$this->results['php_version']['current']}\n";
        echo "  状态: " . ($this->results['php_version']['status'] ? "✓" : "✗") . "\n\n";

        // 扩展检查
        echo "PHP扩展:\n";
        foreach ($this->results['extensions'] as $ext => $result) {
            echo "  {$ext}: " . ($result['loaded'] ? "✓" : "✗") . "\n";
            if (!$result['loaded']) {
                echo "    - {$result['description']}\n";
            }
        }
        echo "\n";

        // 目录检查
        echo "目录权限:\n";
        foreach ($this->results['directories'] as $dir => $result) {
            echo "  {$dir}: " . ($result['writable'] ? "✓" : "✗") . "\n";
            if (!$result['writable']) {
                echo "    - {$result['message']}\n";
            }
        }
        echo "\n";

        // 数据库连接
        echo "数据库连接:\n";
        echo "  状态: " . ($this->results['database']['status'] ? "✓" : "✗") . "\n";
        echo "  信息: {$this->results['database']['message']}\n\n";

        // 总结
        $allPassed = $this->results['php_version']['status'] &&
                    !in_array(false, array_column($this->results['extensions'], 'loaded')) &&
                    !in_array(false, array_column($this->results['directories'], 'writable')) &&
                    $this->results['database']['status'];

        echo "总体状态: " . ($allPassed ? "✓ 所有检查通过" : "✗ 存在未通过的检查项") . "\n";
        
        if (!$allPassed) {
            echo "\n请解决上述问题后再继续。\n";
        }
    }
}

// 运行检查
if (php_sapi_name() === 'cli') {
    $checker = new SystemCheck();
    $checker->check();
    $checker->displayResults();
}