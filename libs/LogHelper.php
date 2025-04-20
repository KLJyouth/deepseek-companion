<?php
namespace Libs;

class LogHelper
{
    private static $instance;
    private $logFile;
    
    private function __construct()
    {
        $this->logFile = __DIR__ . '/../storage/logs/app.log';
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 记录信息日志
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * 记录错误日志
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * 写入日志
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = [])
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[{$date}] {$level}: {$message} {$contextStr}\n";
        
        error_log($logMessage, 3, $this->logFile);
    }
}
