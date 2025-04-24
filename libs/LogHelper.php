<?php
namespace Libs;

class LogHelper {
    private static $instance = null;
    private $logFile;
    
    private function __construct() {
        $this->logFile = __DIR__ . '/../logs/app.log';
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }
    
    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }
    
    public static function logCritical(string $message, array $context = []): void {
        self::getInstance()->critical($message, $context);
    }
    
    public static function logError(string $message, array $context = []): void {
        self::getInstance()->error($message, $context);
    }
    
    private function log(string $level, string $message, array $context = []): void {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[$date] [$level] $message $contextStr\n";
        
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}