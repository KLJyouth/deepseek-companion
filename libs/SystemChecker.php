<?php
/**
 * 系统环境检查器
 */
class SystemChecker {
    private $minRequirements = [
        'php' => '8.0.0',
        'memory' => '128M',
        'storage' => '100MB'
    ];
    
    /**
     * 检查系统资源
     */
    public function checkSystemResources() {
        $results = [
            'memory' => $this->checkMemory(),
            'storage' => $this->checkStorage(),
            'cpu' => $this->checkCpuInfo()
        ];
        
        LogService::debug('系统资源检查结果: ' . json_encode($results));
        return $results;
    }
    
    /**
     * 检查PHP依赖
     */
    public function checkDependencies() {
        $requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'];
        $results = [];
        
        foreach ($requiredExts as $ext) {
            $results[$ext] = extension_loaded($ext);
        }
        
        LogService::debug('PHP扩展检查结果: ' . json_encode($results));
        return $results;
    }
    
    /**
     * 检查文件权限
     */
    public function checkPermissions() {
        $writableDirs = ['storage', 'bootstrap/cache'];
        $results = [];
        
        foreach ($writableDirs as $dir) {
            $path = __DIR__ . '/../' . $dir;
            $results[$dir] = is_writable($path);
            
            if (!$results[$dir]) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                throw new InstallException(
                    "目录不可写: $dir", 
                    InstallException::ERROR_FILE_PERMISSION,
                    [
                        'path' => $path,
                        'required' => '0755', 
                        'current' => $perms,
                        'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : 'unknown',
                        'group' => function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] : 'unknown'
                    ]
                );
            }
        }
        
        return $results;
    }
    
    /**
     * 检查PHP版本
     */
    public function checkPhpVersion() {
        $current = phpversion();
        $meetsRequirement = version_compare($current, $this->minRequirements['php'], '>=');
        
        if (!$meetsRequirement) {
            LogService::error("PHP版本不满足要求: 需要{$this->minRequirements['php']}+, 当前$current");
        }
        
        return $meetsRequirement;
    }
    
    // 私有方法实现具体检查逻辑...
    private function checkMemory() {
        return [
            'required' => $this->minRequirements['memory'],
            'current' => ini_get('memory_limit')
        ];
    }
    
    private function checkStorage() {
        $free = disk_free_space(__DIR__);
        return [
            'required' => $this->minRequirements['storage'],
            'current' => round($free / (1024 * 1024)) . 'MB'
        ];
    }
    
    private function checkCpuInfo() {
        if (PHP_OS === 'Linux') {
            return file_exists('/proc/cpuinfo') ? 
                count(preg_grep('/^processor/m', file('/proc/cpuinfo'))) : 1;
        }
        return 1; // 默认返回1个CPU核心
    }
}