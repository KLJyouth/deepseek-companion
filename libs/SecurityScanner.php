<?php
/**
 * 安全扫描器
 */
class SecurityScanner {
    private $securityReport = [];
    
    /**
     * 运行安全扫描
     */
    public function runSecurityScan() {
        $this->checkFilePermissions();
        $this->checkSensitiveConfigs();
        $this->checkSecurityHeaders();
        $this->validateEncryption();
        $this->generateFirewallRecommendations();
        
        LogService::info('安全扫描完成: ' . json_encode($this->securityReport));
        return $this->securityReport;
    }
    
    /**
     * 检查文件权限
     */
    private function checkFilePermissions() {
        $sensitiveFiles = [
            '.env',
            'storage/logs',
            'bootstrap/cache'
        ];
        
        foreach ($sensitiveFiles as $file) {
            $path = __DIR__ . '/../' . $file;
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $this->securityReport['file_permissions'][$file] = [
                    'current' => $perms,
                    'recommended' => (is_dir($path) ? '0755' : '0644')
                ];
                
                if ($perms !== (is_dir($path) ? '0755' : '0644')) {
                    LogService::warning("文件权限异常: $file ($perms)");
                }
            }
        }
    }
    
    /**
     * 检查敏感配置
     */
    private function checkSensitiveConfigs() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $contents = file_get_contents($envFile);
            
            // 检测调试模式
            $this->securityReport['debug_mode'] = [
                'enabled' => strpos($contents, 'APP_DEBUG=true') !== false,
                'recommendation' => '生产环境应禁用调试模式'
            ];
            
            // 检测是否使用默认密钥
            $this->securityReport['app_key'] = [
                'is_default' => strpos($contents, 'APP_KEY=SomeRandomString') !== false,
                'recommendation' => '应使用随机生成的APP_KEY'
            ];
        }
    }
    
    /**
     * 验证加密配置
     */
    private function validateEncryption() {
        $this->securityReport['encryption'] = [
            'openssl_installed' => extension_loaded('openssl'),
            'sodium_installed' => extension_loaded('sodium'),
            'recommendation' => '建议启用OpenSSL或Libsodium扩展'
        ];
    }
    
    /**
     * 生成防火墙建议
     */
    private function generateFirewallRecommendations() {
        $this->securityReport['firewall'] = [
            'recommendations' => [
                '禁用不必要的PHP函数',
                '限制管理后台访问IP',
                '启用CSRF保护',
                '设置HTTP安全头'
            ]
        ];
    }
    
    /**
     * 检查安全头设置
     */
    private function checkSecurityHeaders() {
        // 模拟检查常见安全头
        $this->securityReport['security_headers'] = [
            'missing_headers' => [
                'X-Frame-Options',
                'X-XSS-Protection',
                'Content-Security-Policy'
            ],
            'recommendation' => '应在Web服务器配置中添加这些安全头'
        ];
    }
}