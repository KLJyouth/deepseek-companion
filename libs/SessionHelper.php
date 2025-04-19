<?php
namespace Libs;

/**
 * 安全会话管理助手
 */
class SessionHelper {
    private static $instance;
    private $sessionStarted = false;

    private function __construct() {
        // 防止直接实例化
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 启动会话
     */
    public function start(): void {
        if (!$this->sessionStarted) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => true,
                'cookie_samesite' => 'Strict'
            ]);
            $this->sessionStarted = true;
        }
    }

    /**
     * 设置会话值
     */
    public function set(string $key, $value): void {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * 获取会话值
     */
    public function get(string $key, $default = null) {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 删除会话值
     */
    public function remove(string $key): void {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * 销毁会话
     */
    public function destroy(): void {
        if ($this->sessionStarted) {
            session_unset();
            session_destroy();
            $this->sessionStarted = false;
        }
    }

    /**
     * 重新生成会话ID
     */
    public function regenerate(): void {
        $this->start();
        session_regenerate_id(true);
    }

    /**
     * 检查会话是否存在
     */
    public function has(string $key): bool {
        $this->start();
        return isset($_SESSION[$key]);
    }
}
