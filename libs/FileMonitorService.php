<?php
namespace Libs;

require_once __DIR__ . '/SecurityAuditHelper.php';
require_once __DIR__ . '/CryptoHelper.php';

use Libs\SecurityAuditHelper;
use Libs\CryptoHelper;
use \Exception;

class FileMonitorService {
    private static $instance = null;
    private $baselineHashes = [];
    private $monitorPaths = [__DIR__.'/../'];
    private $excludePatterns = ['/vendor/', '/node_modules/','\.log$'];
    private $hashAlgo = 'sha256';
    
    private function __construct() {
        $this->loadBaseline();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadBaseline() {
        $baselineFile = __DIR__.'/../storage/file_hashes.baseline';
        if (file_exists($baselineFile)) {
            $encryptedData = file_get_contents($baselineFile);
            $this->baselineHashes = json_decode(
                CryptoHelper::decrypt($encryptedData),
                true
            );
        }
    }

    public function startRealTimeMonitoring() {
        $this->verifyCurrentState();
        
        // 初始化inotify监控
        $notifier = inotify_init();
        foreach ($this->monitorPaths as $path) {
            $this->addWatchRecursive($notifier, $path);
        }

        // 事件循环
        while (true) {
            $events = inotify_read($notifier);
            foreach ($events as $event) {
                $filePath = $event['name'];
                if ($this->shouldMonitor($filePath)) {
                    $this->processFileChange($filePath);
                }
            }
            sleep(5); // 5秒轮询间隔
        }
    }

    private function addWatchRecursive($notifier, $path) {
        if (!is_dir($path)) return;

        inotify_add_watch($notifier, $path, IN_MODIFY | IN_CREATE | IN_DELETE);
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                inotify_add_watch($notifier, $file->getPathname(), IN_MODIFY | IN_CREATE | IN_DELETE);
            }
        }
    }

    private function shouldMonitor($filePath) {
        foreach ($this->excludePatterns as $pattern) {
            if (preg_match("#$pattern#i", $filePath)) {
                return false;
            }
        }
        return true;
    }

    private function processFileChange($filePath) {
        $currentHash = $this->calculateFileHash($filePath);
        $baselineHash = $this->baselineHashes[$filePath] ?? null;

        if ($currentHash !== $baselineHash) {
            $this->handleAnomaly($filePath, $baselineHash, $currentHash);
        }
    }

    public function verifyCurrentState() {
        $currentHashes = [];
        foreach ($this->monitorPaths as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $this->shouldMonitor($file->getPathname())) {
                    $currentHashes[$file->getPathname()] = $this->calculateFileHash($file);
                }
            }
        }

        $anomalies = array_diff_assoc($currentHashes, $this->baselineHashes);
        foreach ($anomalies as $file => $hash) {
            $this->handleAnomaly($file, $this->baselineHashes[$file] ?? 'new_file', $hash);
        }

        return count($anomalies) === 0;
    }

    private function calculateFileHash($filePath) {
        if (!file_exists($filePath)) return 'deleted';
        
        $context = hash_init($this->hashAlgo);
        hash_update_file($context, $filePath);
        return hash_final($context);
    }

    private function handleAnomaly($filePath, $expectedHash, $actualHash) {
        $eventDetails = [
            'file' => $filePath,
            'expected_hash' => $expectedHash,
            'actual_hash' => $actualHash,
            'last_modified' => filemtime($filePath),
            'severity' => $expectedHash === null ? 'warning' : 'critical'
        ];

        SecurityAuditHelper::audit(
            'file_integrity',
            '文件完整性异常：' . json_encode($eventDetails)
        );

        // 触发自动修复或告警
        if ($eventDetails['severity'] === 'critical') {
            $this->triggerIncidentResponse($eventDetails);
        }
    }

    private function triggerIncidentResponse($eventDetails) {
        // 调用安全事件响应服务
        $responseService = new \Admin\Services\IncidentResponseService();
        $responseService->handleFileTampering($eventDetails);
    }

    public function generateNewBaseline() {
        $this->verifyCurrentState();
        $baselineFile = __DIR__.'/../storage/file_hashes.baseline';
        $encryptedData = CryptoHelper::encrypt(json_encode($this->baselineHashes));
        file_put_contents($baselineFile, $encryptedData);
    }
}