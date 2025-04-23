<?php
namespace Security\Audit;

use Security\Blockchain\BlockchainService;
use Security\Quantum\QuantumKeyManager;
use Models\AuditLog;

class PathAccessAuditor {
    private $blockchainService;
    private $keyManager;
    private $sensitivePatterns = [
        '/\.env$/i',
        '/config\.php$/i',
        '/database\.php$/i',
        '/\/admin\//i'
    ];

    public function __construct() {
        $this->blockchainService = new BlockchainService();
        $this->keyManager = new QuantumKeyManager();
    }

    /**
     * 记录路径访问事件
     */
    public function logAccess(string $encryptedPath, string $originalPath, string $action): void {
        $isSensitive = $this->isSensitivePath($originalPath);
        $keyVersion = $this->keyManager->getCurrentKeyVersion();

        // 本地数据库记录
        AuditLog::create([
            'event_type' => 'PATH_ACCESS',
            'encrypted_path' => $encryptedPath,
            'original_path' => $originalPath,
            'action' => $action,
            'is_sensitive' => $isSensitive,
            'key_version' => $keyVersion,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // 区块链存证（仅敏感路径）
        if ($isSensitive) {
            $this->blockchainService->logOperation(
                'SENSITIVE_PATH_ACCESS',
                $encryptedPath,
                json_encode([
                    'action' => $action,
                    'key_version' => $keyVersion,
                    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'timestamp' => microtime(true)
                ])
            );
        }
    }

    /**
     * 生成路径访问报告
     */
    public function generateReport(\DateTime $startDate, \DateTime $endDate): array {
        $report = [
            'total_accesses' => 0,
            'sensitive_accesses' => 0,
            'by_type' => [],
            'suspicious_activities' => []
        ];

        // 获取基础审计数据
        $logs = AuditLog::whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', 'PATH_ACCESS')
            ->get();

        foreach ($logs as $log) {
            $report['total_accesses']++;
            
            if ($log->is_sensitive) {
                $report['sensitive_accesses']++;
            }

            // 按类型统计
            $report['by_type'][$log->action] = ($report['by_type'][$log->action] ?? 0) + 1;
        }

        // 检测可疑活动
        $report['suspicious_activities'] = $this->detectSuspiciousActivities($logs);

        return $report;
    }

    private function isSensitivePath(string $path): bool {
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    private function detectSuspiciousActivities($logs): array {
        $suspicious = [];
        $accessCounts = [];
        $userAccessMap = [];

        // 分析访问模式
        foreach ($logs as $log) {
            $clientId = $log->client_id ?? $log->ip_address;
            
            // 高频访问检测
            $accessCounts[$clientId] = ($accessCounts[$clientId] ?? 0) + 1;
            
            // 敏感路径访问记录
            if ($log->is_sensitive) {
                $userAccessMap[$clientId]['sensitive'][] = [
                    'path' => $log->original_path,
                    'time' => $log->created_at
                ];
            }
        }

        // 标记可疑客户端
        foreach ($accessCounts as $clientId => $count) {
            if ($count > 100) { // 超过100次访问
                $suspicious[$clientId] = [
                    'reason' => 'HIGH_FREQUENCY_ACCESS',
                    'access_count' => $count,
                    'sensitive_accesses' => count($userAccessMap[$clientId]['sensitive'] ?? [])
                ];
            }
        }

        return $suspicious;
    }
}