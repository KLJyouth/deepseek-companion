<?php
/**
 * 安全服务类
 * 版权所有 广西港妙科技有限公司
 */

namespace Services;

use Exception;
use RuntimeException;
use Libs\LogHelper;

class SecurityService
{
    public function __construct(
        private readonly string $backupDir = '/var/backups/env_files',
        private readonly string $accessLog = '/var/log/install_bak_access.log',
        private readonly string $gpgRecipientFile = '/etc/gpg_recipient'
    ) {}

    // 检查备份状态
    /**
     * 检查备份状态
     * @return array{healthy:bool,message:string} 备份状态信息
     * @throws RuntimeException 当备份检查失败时
     */
    public function checkBackups(): array
    {
        $status = ['healthy' => true, 'message' => '备份正常'];
        
        try {
            // 检查备份目录是否存在
            if (!is_dir($this->backupDir)) {
                throw new Exception('备份目录不存在');
            }

            // 检查目录权限
            if (substr(sprintf('%o', fileperms($this->backupDir)), -4) != '0700') {
                throw new Exception('备份目录权限不安全');
            }

            $backups = glob("{$this->backupDir}/env_backup_*.gpg");
            if (empty($backups)) {
                throw new Exception('未找到备份文件');
            }

            $latest = max(array_map('filemtime', $backups));
            if (time() - $latest > 86400) {
                throw new Exception('最近24小时无新备份');
            }

            // 验证备份文件完整性和可读性
            foreach ($backups as $backup) {
                if (filesize($backup) < 100) {
                    throw new Exception('发现无效备份文件: '.basename($backup));
                }
                
                // 测试解密前100字节
                $tempFile = tempnam(sys_get_temp_dir(), 'gpg_test');
                try {
                    exec("gpg --decrypt --batch --output {$tempFile} {$backup} 2>&1", $output, $returnCode);
                    if ($returnCode !== 0) {
                        throw new Exception('备份文件解密失败: '.basename($backup));
                    }
                } finally {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
        } catch (Exception $e) {
            $status = ['healthy' => false, 'message' => $e->getMessage()];
        }

        return $status;
    }

    // 获取备份文件列表
    /**
     * 获取备份文件列表
     * @return array<array{name:string,size:int,modified:string,path:string}> 备份文件列表
     */
    public function getBackupFiles(): array
    {
        $backups = [];
        $files = glob("{$this->backupDir}/env_backup_*.gpg");
        
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'path' => $file
            ];
        }

        usort($backups, fn($a, $b) => $b['modified'] <=> $a['modified']);
        return $backups;
    }

    // 检查日志异常
    /**
     * 检查日志异常
     * @return array<string> 异常日志列表
     */
    public function checkLogAlerts(): array
    {
        $alerts = [];
        
        if (!file_exists($this->accessLog)) {
            return $alerts;
        }

        $lines = file($this->accessLog, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '异常访问') !== false) {
                $alerts[] = $line;
            }
        }

        return $alerts;
    }

    // 获取访问日志
    /**
     * 获取访问日志
     * @return array<string> 访问日志列表
     */
    public function getAccessLogs(): array
    {
        if (!file_exists($this->accessLog)) {
            return [];
        }

        return array_reverse(file($this->accessLog, FILE_IGNORE_NEW_LINES));
    }

    // 检查GPG密钥状态
    /**
     * 检查GPG密钥状态
     * @return array{healthy:bool,message:string} GPG密钥状态
     */
    public function checkGpgKey(): array
    {
        $status = ['healthy' => true, 'message' => 'GPG密钥配置正常'];
        
        try {
            if (!file_exists($this->gpgRecipientFile)) {
                throw new Exception('未配置GPG收件人');
            }

            $email = trim(file_get_contents($this->gpgRecipientFile));
            exec("gpg --list-keys {$email} 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('GPG密钥不存在或无效');
            }
        } catch (Exception $e) {
            $status = ['healthy' => false, 'message' => $e->getMessage()];
        }

        return $status;
    }

    // 获取当前GPG密钥
    /**
     * 获取当前GPG密钥
     * @return string 当前配置的GPG密钥邮箱
     */
    public function getCurrentGpgKey(): string
    {
        return file_exists($this->gpgRecipientFile) 
            ? file_get_contents($this->gpgRecipientFile)
            : '未配置';
    }

    // 更新GPG密钥
    /**
     * 更新GPG密钥
     * @param string $email GPG密钥邮箱
     * @return array{success:bool,message:string} 更新结果
     * @throws RuntimeException 当更新失败时
     */
    public function updateGpgKey(string $email): array
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('无效的邮箱格式');
            }

            // 验证密钥是否存在
            exec("gpg --list-keys {$email} 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new Exception('系统中不存在此邮箱的GPG密钥');
            }

            file_put_contents($this->gpgRecipientFile, $email);

            // 审计日志
            \Libs\LogHelper::getInstance()->audit('update_gpg_key', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'email' => $email
            ]);

            return ['success' => true, 'message' => 'GPG收件人更新成功'];
        } catch (Exception $e) {
            \Libs\LogHelper::getInstance()->error('GPG密钥更新失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}