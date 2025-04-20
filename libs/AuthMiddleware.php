<?php
namespace Libs;

use Exception;
use Libs\DatabaseHelper;

/**
 * 认证中间件类
 * 提供统一的权限验证方法
 */
class AuthMiddleware {
    private static $instance = null;

    // 私有构造方法防止外部实例化
    private function __construct() {}

    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 验证管理员权限
     * @throws Exception 当用户不是管理员时
     */
    public static function verifyAdmin() {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            throw new Exception('无权访问：需要管理员权限');
        }
    }

    /**
     * 验证用户是否登录
     * @throws Exception 当用户未登录时
     */
    public static function verifyAuth() {
        if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            throw new Exception('请先登录');
        }
    }

    /**
     * 验证合同访问权限
     * @param string $contractId 合同ID
     * @throws Exception 当用户无权访问合同时
     */
    public static function verifyContractAccess($contractId) {
        self::verifyAuth(); // 先验证用户是否登录
        
        $db = DatabaseHelper::getInstance();
        $contract = $db->getRow(
            "SELECT * FROM {$db->tablePrefix}contracts WHERE id = ?",
            [['value' => $contractId, 'type' => 's']]
        );
        
        if (!$contract || $contract['created_by'] != $_SESSION['user']['id']) {
            throw new Exception('无权访问此合同');
        }
    }

    /**
     * 销毁用户会话
     * @throws Exception 当会话销毁失败时
     */
    public static function destroySession() {
        try {
            // 清除所有会话变量
            $_SESSION = [];

            // 如果要彻底销毁会话，同时删除会话cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), 
                    '', 
                    time() - 42000,
                    $params["path"], 
                    $params["domain"],
                    $params["secure"], 
                    $params["httponly"]
                );
            }

            // 最后销毁会话
            session_destroy();
        } catch (Exception $e) {
            error_log("销毁会话失败: " . $e->getMessage());
            throw new Exception("注销失败，请重试");
        }
    }
}