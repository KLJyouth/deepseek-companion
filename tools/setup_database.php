<?php
class DatabaseSetup {
    private $config = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'stanfai',
        'username' => 'root',
        'password' => ''
    ];

    private $testConfig = [
        'database' => 'stanfai_test'
    ];

    public function run(): void {
        echo "\n数据库配置向导\n";
        echo "==============\n\n";

        $this->detectMySQLInstallation();
        $this->gatherConfiguration();
        $this->testConnection();
        $this->createDatabases();
        $this->saveConfiguration();
    }

    private function detectMySQLInstallation(): void {
        echo "检测MySQL安装...\n";

        // 检查MySQL服务
        if (PHP_OS_FAMILY === 'Windows') {
            exec('sc query MySQL', $output, $returnVar);
            if ($returnVar !== 0) {
                $this->showMySQLInstallGuide();
                exit(1);
            }
        }

        // 检查PDO扩展
        if (!extension_loaded('pdo_mysql')) {
            echo "错误: 未安装PDO MySQL扩展\n";
            echo "请先运行 php tools/extension_helper.php 安装必要的扩展\n";
            exit(1);
        }

        echo "✓ MySQL检测通过\n\n";
    }

    private function showMySQLInstallGuide(): void {
        echo "\n未检测到MySQL服务！\n";
        echo "请按照以下步骤安装MySQL：\n\n";
        echo "1. 下载MySQL安装程序：\n";
        echo "   https://dev.mysql.com/downloads/installer/\n\n";
        echo "2. 运行安装程序，选择'Server only'安装类型\n\n";
        echo "3. 按照安装向导设置root密码\n\n";
        echo "4. 确保MySQL服务已启动：\n";
        echo "   - 打开服务管理器(services.msc)\n";
        echo "   - 找到MySQL服务并启动\n\n";
        echo "5. 安装完成后重新运行此向导\n\n";
    }

    private function gatherConfiguration(): void {
        echo "配置数据库连接：\n";
        
        $this->config['host'] = $this->prompt("数据库主机", $this->config['host']);
        $this->config['port'] = (int)$this->prompt("数据库端口", $this->config['port']);
        $this->config['username'] = $this->prompt("数据库用户名", $this->config['username']);
        $this->config['password'] = $this->prompt("数据库密码");
        $this->config['database'] = $this->prompt("数据库名称", $this->config['database']);
        
        echo "\n";
    }

    private function testConnection(): void {
        echo "测试数据库连接...\n";

        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']}";
            $pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            echo "✓ 连接成功\n\n";
        } catch (PDOException $e) {
            echo "连接失败: " . $e->getMessage() . "\n";
            echo "请检查配置并重试\n";
            exit(1);
        }
    }

    private function createDatabases(): void {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']}";
            $pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 创建主数据库
            echo "创建数据库 {$this->config['database']}...\n";
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // 创建测试数据库
            echo "创建测试数据库 {$this->testConfig['database']}...\n";
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->testConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            echo "✓ 数据库创建完成\n\n";
        } catch (PDOException $e) {
            echo "创建数据库失败: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function saveConfiguration(): void {
        echo "保存数据库配置...\n";

        // 更新.env文件
        $envFile = dirname(__DIR__) . '/.env';
        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
        
        $envContent = $this->updateEnvValue($envContent, 'DB_HOST', $this->config['host']);
        $envContent = $this->updateEnvValue($envContent, 'DB_PORT', $this->config['port']);
        $envContent = $this->updateEnvValue($envContent, 'DB_DATABASE', $this->config['database']);
        $envContent = $this->updateEnvValue($envContent, 'DB_USERNAME', $this->config['username']);
        $envContent = $this->updateEnvValue($envContent, 'DB_PASSWORD', $this->config['password']);
        
        // 添加测试数据库配置
        $envContent = $this->updateEnvValue($envContent, 'DB_TEST_DATABASE', $this->testConfig['database']);
        
        file_put_contents($envFile, $envContent);
        
        echo "✓ 配置已保存到 .env 文件\n\n";
        
        echo "数据库配置完成！\n";
        echo "下一步：运行数据库迁移\n";
        echo "php database/migrate.php run\n\n";
    }

    private function prompt(string $message, string $default = ''): string {
        $defaultText = $default ? " [{$default}]" : '';
        echo "{$message}{$defaultText}: ";
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }

    private function updateEnvValue(string $envContent, string $key, string $value): string {
        $pattern = "/^{$key}=.*/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $replacement, $envContent);
        }
        
        return $envContent . "\n{$replacement}";
    }
}

// 运行配置向导
if (php_sapi_name() === 'cli') {
    $setup = new DatabaseSetup();
    $setup->run();
}