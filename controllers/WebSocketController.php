<?php
namespace Controllers;

require_once __DIR__ . '/../libs/CryptoHelper.php';
require_once __DIR__ . '/../libs/DatabaseHelper.php';

use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Exception;

class WebSocketController {
    private $dbHelper;
    
    public function __construct(DatabaseHelper $dbHelper) {
        $this->dbHelper = $dbHelper;
    }
    
    /**
     * 获取WebSocket认证令牌
     */
    public function getAuthToken() {
        if (!isset($_SESSION['user_id'])) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
        
        // 生成临时令牌(有效期1小时)
        $token = bin2hex(random_bytes(32));
        $expires = time() + 3600;
        
        // 存储到数据库
        $this->dbHelper->insert('ws_auth_tokens', [
            'user_id' => $_SESSION['user_id'],
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', $expires)
        ]);
        
        // 返回给客户端
        header('Content-Type: application/json');
        echo json_encode([
            'token' => $token,
            'expires' => $expires
        ]);
    }
    
    /**
     * 验证WebSocket令牌
     */
    public function verifyToken(string $token): bool {
        $record = $this->dbHelper->getRow(
            "SELECT user_id FROM ws_auth_tokens 
             WHERE token = ? AND expires_at > NOW()",
            [['value' => $token, 'encrypt' => false]]
        );
        
        return !empty($record);
    }
}