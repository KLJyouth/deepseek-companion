<?php
/**
 * 安装日志服务
 */
class LogService {
    private static $logFile = 'storage/logs/install.log';
    
    /**
     * 初始化日志服务
     */
    public static function init($context = '') {
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }
    }
    
    /**
     * 记录日志
     */
    public static function log($message, $level = 'INFO') {
        $logEntry = sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * 记录错误
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    /**
     * 记录调试信息
     */
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
}