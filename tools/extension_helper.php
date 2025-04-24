<?php
class ExtensionHelper {
    private $phpIniPath;
    private $extensionDir;
    private $requiredExtensions = [
        'pdo' => 'PDO基础扩展',
        'pdo_mysql' => 'PDO MySQL驱动',
        'mbstring' => 'MultiByte String处理',
        'json' => 'JSON支持'
    ];

    public function __construct() {
        $this->detectPhpIni();
        $this->detectExtensionDir();
    }

    public function check(): void {
        echo "\nPHP扩展检查工具\n";
        echo "================\n\n";

        echo "PHP信息:\n";
        echo "  版本: " . PHP_VERSION . "\n";
        echo "  配置文件: " . $this->phpIniPath . "\n";
        echo "  扩展目录: " . $this->extensionDir . "\n\n";

        echo "扩展状态:\n";
        foreach ($this->requiredExtensions as $ext => $desc) {
            $loaded = extension_loaded($ext);
            echo "  {$ext}: " . ($loaded ? "✓" : "✗") . " - {$desc}\n";
            
            if (!$loaded) {
                $this->provideFix($ext);
            }
        }

        echo "\n配置建议:\n";
        $this->checkPhpIni();
    }

    private function detectPhpIni(): void {
        $this->phpIniPath = php_ini_loaded_file();
        if (!$this->phpIniPath) {
            die("错误: 无法找到php.ini文件！\n");
        }
    }

    private function detectExtensionDir(): void {
        $this->extensionDir = ini_get('extension_dir');
        if (!$this->extensionDir) {
            die("错误: 无法找到PHP扩展目录！\n");
        }
    }

    private function checkPhpIni(): void {
        $iniContent = file_get_contents($this->phpIniPath);
        
        foreach ($this->requiredExtensions as $ext => $desc) {
            $pattern = "/^;?\s*extension\s*=\s*{$ext}\b/m";
            if (preg_match($pattern, $iniContent, $matches)) {
                if (strpos($matches[0], ';') === 0) {
                    echo "  - {$ext}: 在php.ini中已存在但被注释，需要取消注释\n";
                    $this->showEnableStep($ext);
                }
            } else {
                echo "  - {$ext}: 在php.ini中未找到配置，需要添加\n";
                $this->showAddStep($ext);
            }
        }
    }

    private function provideFix(string $extension): void {
        echo "    修复建议:\n";
        
        // 检查扩展文件是否存在
        $dllFile = "php_{$extension}.dll";
        $dllPath = $this->extensionDir . DIRECTORY_SEPARATOR . $dllFile;
        
        if (!file_exists($dllPath)) {
            echo "    - 扩展文件 {$dllFile} 不存在\n";
            echo "    - 请从PHP官网下载对应版本的扩展\n";
            echo "    - 下载后将文件复制到: {$this->extensionDir}\n";
        }
        
        echo "    - 在php.ini中添加: extension={$extension}\n";
        echo "    - 重启Web服务器\n";
    }

    private function showEnableStep(string $extension): void {
        echo "    修改步骤:\n";
        echo "    1. 打开文件: {$this->phpIniPath}\n";
        echo "    2. 找到行: ;extension={$extension}\n";
        echo "    3. 删除分号注释符\n";
        echo "    4. 保存文件并重启Web服务器\n";
    }

    private function showAddStep(string $extension): void {
        echo "    添加步骤:\n";
        echo "    1. 打开文件: {$this->phpIniPath}\n";
        echo "    2. 在[PHP]部分添加行: extension={$extension}\n";
        echo "    3. 保存文件并重启Web服务器\n";
    }

    public function tryAutoFix(): void {
        echo "\n尝试自动修复配置...\n";
        
        if (!is_writable($this->phpIniPath)) {
            echo "错误: php.ini文件不可写入。请手动修改或以管理员权限运行。\n";
            return;
        }

        $content = file_get_contents($this->phpIniPath);
        $modified = false;

        foreach ($this->requiredExtensions as $ext => $desc) {
            // 检查是否已启用
            if (extension_loaded($ext)) {
                continue;
            }

            $pattern = "/^;?\s*extension\s*=\s*{$ext}\b/m";
            if (preg_match($pattern, $content)) {
                // 取消注释
                $content = preg_replace($pattern, "extension={$ext}", $content);
                $modified = true;
                echo "已启用 {$ext} 扩展\n";
            } else {
                // 添加新配置
                $content .= "\nextension={$ext}\n";
                $modified = true;
                echo "已添加 {$ext} 扩展配置\n";
            }
        }

        if ($modified) {
            if (file_put_contents($this->phpIniPath, $content)) {
                echo "\n配置已更新。请重启Web服务器使更改生效。\n";
            } else {
                echo "\n错误: 无法写入配置文件。\n";
            }
        } else {
            echo "\n无需修改配置。\n";
        }
    }
}

// 运行检查
if (php_sapi_name() === 'cli') {
    $helper = new ExtensionHelper();
    $helper->check();
    
    echo "\n是否尝试自动修复？(y/n) ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) === 'y') {
        $helper->tryAutoFix();
    }
}