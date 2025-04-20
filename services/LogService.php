<?php
namespace Services;

class LogService
{
    private string $logPath;
    private static ?self $instance = null;

    private function __construct()
    {
        $this->logPath = dirname(__DIR__) . '/storage/logs/';
        $this->ensureLogDirectory();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void 
    {
        $this->log('ERROR', $message, $context);
    }

    public function audit(string $action, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$date}] AUDIT: {$action} {$contextJson}\n";
        $logFile = $this->logPath . 'audit_' . date('Y-m-d') . '.log';
        error_log($logMessage, 3, $logFile);
    }

    private function log(string $level, string $message, array $context): void
    {
        $date = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context) : '';
        $logMessage = "[{$date}] {$level}: {$message} {$contextJson}\n";
        
        $logFile = $this->logPath . date('Y-m-d') . '.log';
        error_log($logMessage, 3, $logFile);
    }

    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
}
