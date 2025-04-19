<?php
namespace Libs;
/**
 * 安全重定向助手类
 */
class RedirectHelper {
    /**
     * 执行安全重定向
     * @param string $url 重定向目标URL
     * @param int $statusCode HTTP状态码（默认302临时重定向）
     */
    public static function redirect($url, $statusCode = 302) {
        // 防止头部注入攻击
        $cleanUrl = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!headers_sent()) {
            header("Location: " . $cleanUrl, true, $statusCode);
        } else {
            // 使用JavaScript后备方案
            echo '<script>window.location.href="' . htmlspecialchars($cleanUrl) . '";</script>';
        }
        exit();
    }
}
?>