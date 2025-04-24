<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/tools/system_check.php';

class Installer {
    private $config;
    private $pdo;
    
    public function __construct() {
        $this->checkEnvironment();
    }
    
    public function install(): void {
        echo "开始安装 Stanfai PHP 项目...\n\n";
        
        // 运行系统检查
        $this->runSystemCheck();
        
        // 创建配置文件
        $this->createConfigFile();
        
        // 创建数据库
        $this->createDatabase();
        
        // 创建必要的目录
        $this->createDirectories();
        
        // 设置权限
        $this->setPermissions();
        
        echo "\n安装完成！\n";
        echo "请按照README.md中的说明运行项目。\n";
    }
    
    private function checkEnvironment(): void {
        if (php_sapi_name() !== 'cli') {
            die("此脚本只能在命令行中运行！\n");
        }
    }
    
    private function runSystemCheck(): void {
        echo "运行系统检查...\n";
        $checker = new SystemCheck();
        $results = $checker->check();
        $checker->displayResults();
        
        if ($this->hasFailedChecks($results)) {
            die("\n请解决以上问题后重试安装。\n");
        }
    }
    
    private function hasFailedChecks(array $results): bool {
        return !$results['php_version']['status'] ||
               in_array(false, array_column($results['extensions'], 'loaded')) ||
               in_array(false, array_column($results['directories'], 'writable')) ||
               !$results['database']['status'];
    }
    
    private function createConfigFile(): void {
        echo "\n配置数据库连接...\n";
        
        $host = $this->prompt("数据库主机 [localhost]: ", "localhost");
        $port = $this->prompt("数据库端口 [3306]: ", "3306");
        $database = $this->prompt("数据库名 [stanfai]: ", "stanfai");
        $username = $this->prompt("数据库用户名 [root]: ", "root");
        $password = $this->prompt("数据库密码: ");
        
        $envContent = <<<EOT
DB_HOST=$host
DB_PORT=$port
DB_DATABASE=$database
DB_USERNAME=$username
DB_PASSWORD=$password

APP_ENV=development
APP_DEBUG=true

ENCRYPTION_KEY=base64:$(base64_encode(random_bytes(32)))
ENCRYPTION_IV=base64:$(base64_encode(random_bytes(16)))
EOT;
        
        file_put_contents(__DIR__ . '/.env', $envContent);
        echo "配置文件已创建。\n";
    }
    
    private function createDatabase(): void {
        echo "\n创建数据库...\n";
        
        try {
            $config = parse_ini_file(__DIR__ . '/.env');
            $this->pdo = new PDO(
                "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']}",
                $config['DB_USERNAME'],
                $config['DB_PASSWORD']
            );
            
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['DB_DATABASE']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "数据库创建成功。\n";
            
            // 创建测试数据库
            $testDatabase = $config['DB_DATABASE'] . '_test';
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "测试数据库创建成功。\n";
            
        } catch (PDOException $e) {
            die("数据库创建失败: " . $e->getMessage() . "\n");
        }
    }
    
    private function createDirectories(): void {
        echo "\n创建必要的目录...\n";
        
        $directories = [
            'logs',
            'cache',
            'storage',
            'storage/uploads',
            'storage/cache',
            'storage/sessions'
        ];
        
        foreach ($directories as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
                echo "创建目录: {$dir}\n";
            }
        }
    }
    
    private function setPermissions(): void {
        echo "\n设置目录权限...\n";
        
        $directories = [
            'logs',
            'cache',
            'storage'
        ];
        
        foreach ($directories as $dir) {
            $path = __DIR__ . '/' . $dir;
            chmod($path, 0777);
            echo "设置权限: {$dir}\n";
        }
    }
    
    private function prompt(string $message, string $default = ''): string {
        echo $message;
        $input = trim(fgets(STDIN));
        return empty($input) ? $default : $input;
    }
}

// 运行安装程序
$installer = new Installer();
$installer->install();