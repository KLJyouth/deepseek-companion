<?php
class MySQLConnectionCheck {
    private $config = [
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'root',
        'password' => ''
    ];

    public function __construct(array $config = []) {
        $this->config = array_merge($this->config, $config);
    }

    public function diagnose(): void {
        echo "\nMySQL连接诊断工具\n";
        echo "=================\n\n";

        // 检查MySQL服务
        $this->checkMySQLService();

        // 检查端口
        $this->checkPort();

        // 尝试连接
        $this->tryConnection();

        // 提供建议
        $this->provideRecommendations();
    }

    private function checkMySQLService(): void {
        echo "检查MySQL服务状态:\n";
        
        if (PHP_OS_FAMILY === 'Windows') {
            exec('sc query MySQL', $output, $returnVar);
            
            if ($returnVar === 0 && strpos(implode("\n", $output), 'RUNNING') !== false) {
                echo "  ✓ MySQL服务正在运行\n";
            } else {
                echo "  ✗ MySQL服务未运行\n";
                echo "  建议:\n";
                echo "  1. 以管理员身份运行命令提示符\n";
                echo "  2. 执行: net start MySQL\n";
                echo "  或者通过服务管理器启动MySQL服务\n";
            }
        } else {
            exec('systemctl status mysql', $output, $returnVar);
            
            if ($returnVar === 0 && strpos(implode("\n", $output), 'active (running)') !== false) {
                echo "  ✓ MySQL服务正在运行\n";
            } else {
                echo "  ✗ MySQL服务未运行\n";
                echo "  建议: sudo systemctl start mysql\n";
            }
        }
        echo "\n";
    }

    private function checkPort(): void {
        echo "检查端口 {$this->config['port']}:\n";
        
        $connection = @fsockopen(
            $this->config['host'],
            $this->config['port'],
            $errno,
            $errstr,
            5
        );

        if ($connection) {
            echo "  ✓ 端口可访问\n";
            fclose($connection);
        } else {
            echo "  ✗ 端口无法访问 (错误: $errstr)\n";
            echo "  建议:\n";
            echo "  1. 确认MySQL配置文件中的端口设置\n";
            echo "  2. 检查防火墙设置\n";
            echo "  3. 确认MySQL服务正在运行\n";
        }
        echo "\n";
    }

    private function tryConnection(): void {
        echo "尝试数据库连接:\n";
        
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']}";
            $pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password']
            );
            
            echo "  ✓ 连接成功\n";
            
            // 获取MySQL版本信息
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo "  MySQL版本: $version\n";
            
        } catch (PDOException $e) {
            echo "  ✗ 连接失败: " . $e->getMessage() . "\n";
            
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                echo "  可能是用户名或密码错误\n";
            } elseif (strpos($e->getMessage(), 'refused') !== false) {
                echo "  MySQL服务可能未启动或端口配置错误\n";
            }
        }
        echo "\n";
    }

    private function provideRecommendations(): void {
        echo "常见解决方案:\n\n";
        
        echo "1. 启动MySQL服务\n";
        if (PHP_OS_FAMILY === 'Windows') {
            echo "   - 使用服务管理器(services.msc)\n";
            echo "   - 或命令行: net start MySQL\n";
        } else {
            echo "   - 命令行: sudo systemctl start mysql\n";
        }
        
        echo "\n2. 检查MySQL配置\n";
        echo "   - 默认配置文件位置:\n";
        if (PHP_OS_FAMILY === 'Windows') {
            echo "     C:\\ProgramData\\MySQL\\MySQL Server 8.0\\my.ini\n";
        } else {
            echo "     /etc/mysql/my.cnf\n";
        }
        
        echo "\n3. 验证用户权限\n";
        echo "   - 使用MySQL命令行工具验证登录:\n";
        echo "     mysql -u {$this->config['username']} -p\n";
        
        echo "\n4. 检查防火墙设置\n";
        if (PHP_OS_FAMILY === 'Windows') {
            echo "   - 检查Windows防火墙入站规则\n";
            echo "   - 允许MySQL端口(通常是3306)\n";
        } else {
            echo "   - 检查iptables规则\n";
            echo "   - 命令: sudo iptables -L\n";
        }
        
        echo "\n5. 验证网络连接\n";
        echo "   - 使用telnet测试端口:\n";
        echo "     telnet {$this->config['host']} {$this->config['port']}\n";
        
        echo "\n如需更多帮助，请查看MySQL官方文档或社区支持。\n";
    }
}

// 运行诊断
if (php_sapi_name() === 'cli') {
    $checker = new MySQLConnectionCheck();
    $checker->diagnose();
}