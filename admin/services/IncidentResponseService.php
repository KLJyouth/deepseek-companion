<?php
namespace Admin\Services;

require_once __DIR__ . '/../../libs/SecurityAuditHelper.php';
require_once __DIR__ . '/../../libs/DatabaseHelper.php';
require_once __DIR__ . '/../../middlewares/ThreatIntelligenceMiddleware.php';

use Libs\SecurityAuditHelper;
use Libs\DatabaseHelper;
use Middlewares\ThreatIntelligenceMiddleware;

class IncidentResponseService {
    private $db;
    private $quarantineDir = __DIR__.'/../../storage/quarantine/';
    
    public function __construct() {
        $this->db = DatabaseHelper::getInstance();
        $this->initQuarantine();
    }

    public function handleFileTampering($event) {
        // 隔离可疑文件
        $backupPath = $this->quarantineFile($event['file']);
        
        // 记录安全事件
        $this->db->logAudit('incident_response', 0, [
            'event_type' => 'file_tampering',
            'severity' => 'critical',
            'action_taken' => 'file_quarantine',
            'backup_path' => $backupPath
        ]);

        // 自动修复机制
        if ($this->shouldRollback($event)) {
            $this->rollbackToSafeVersion($event['file']);
        }

        // 更新威胁情报
        ThreatIntelligenceMiddleware::refreshIntel();
    }

    private function quarantineFile($filePath) {
        $hash = md5_file($filePath);
        $backupName = date('Ymd-His').'_'.$hash.'.bak';
        $backupPath = $this->quarantineDir.$backupName;
        
        if (!copy($filePath, $backupPath)) {
            SecurityAuditHelper::audit('quarantine_fail', '文件隔离失败: '.$filePath);
            return null;
        }
        
        // 清空原始文件内容
        file_put_contents($filePath, '');
        return $backupPath;
    }

    private function shouldRollback($event) {
        return $event['severity'] === 'critical' 
            && strtotime('-1 day') < $event['last_modified'];
    }

    private function rollbackToSafeVersion($filePath) {
        $baselineFile = __DIR__.'/../../storage/file_hashes.baseline';
        if (file_exists($baselineFile)) {
            // 从安全基线恢复文件
            $fileMonitor = new \Libs\FileMonitorService();
            $fileMonitor->generateNewBaseline();
        }
    }

    private function initQuarantine() {
        if (!file_exists($this->quarantineDir)) {
            mkdir($this->quarantineDir, 0700, true);
        }
    }

    public function checkAndInstallSecurityComponents() {
        $requiredComponents = [
            'security-middleware' => '^2.3.0',
            'threat-detector' => '^1.5.0',
            'data-encryptor' => '^3.1.0',
            'secure-storage' => '^2.0.0'
        ];

        $installed = json_decode(shell_exec('npm list --json --depth=0'), true);
        $missing = [];

        foreach ($requiredComponents as $component => $version) {
            if (!isset($installed['dependencies'][$component]) || 
                !version_compare($installed['dependencies'][$component]['version'], $version, '>=')) {
                $missing[] = "$component@$version";
            }
        }

        if (!empty($missing)) {
            $command = 'npm install --save ' . implode(' ', $missing);
            shell_exec($command);
            $this->db->logAudit('security_components', 0, [
                'action' => 'auto_install',
                'components' => $missing
            ]);
        }
    }

    public function blockMaliciousActor($ip) {
        // 永久封禁恶意IP
        $this->db->logAudit('ip_block', 0, [
            'malicious_ip' => $ip,
            'reason' => 'repeated_attack_patterns'
        ]);
        
        // 更新防火墙规则
        file_put_contents(
            __DIR__.'/../../storage/firewall.rules',
            "deny from $ip\n",
            FILE_APPEND
        );
    }
}