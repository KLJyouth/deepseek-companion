<?php
namespace Services;

use Libs\SessionHelper;
use Libs\CryptoHelper;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketService implements MessageComponentInterface {
    protected $clients;
    private $csrfTokenName = 'csrf_token';
    
    private $enableCompression;
    private $compressionThreshold;
    private $signingKey;
    private $keyRotationInterval = 3600; // 1小时轮换一次
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->enableCompression = ConfigHelper::get('websocket.compression', true);
        $this->compressionThreshold = ConfigHelper::get('websocket.compression_threshold', 1024);
        $this->signingKey = $this->getCurrentSigningKey();
        
        // 定时轮换密钥
        $this->scheduleKeyRotation();
    }
    
    private function getCurrentSigningKey(): string {
        $key = ConfigHelper::get('websocket.signing_key');
        if (empty($key)) {
            throw new \RuntimeException('WebSocket签名密钥未配置');
        }
        return $key;
    }
    
    private function scheduleKeyRotation() {
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm($this->keyRotationInterval);
            pcntl_signal(SIGALRM, function() {
                $this->signingKey = $this->generateNewKey();
                $this->scheduleKeyRotation();
            });
        }
    }
    
    private function generateNewKey(): string {
        $newKey = bin2hex(random_bytes(32));
        ConfigHelper::set('websocket.signing_key', $newKey);
        return $newKey;
    }
    
    private function signMessage(string $message): array {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $signature = hash_hmac('sha256', $timestamp.$nonce.$message, $this->signingKey);
        
        return [
            'message' => $message,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature
        ];
    }
    
    private function verifySignature(array $data): bool {
        if (empty($data['timestamp']) || empty($data['nonce']) || empty($data['signature'])) {
            return false;
        }
        
        // 验证时间戳有效性(5分钟内)
        if (abs(time() - $data['timestamp']) > 300) {
            return false;
        }
        
        $expected = hash_hmac('sha256', 
            $data['timestamp'].$data['nonce'].$data['message'], 
            $this->signingKey
        );
        
        return hash_equals($expected, $data['signature']);
    }
    
    private function verifyAuthToken(string $token): bool {
        try {
            $db = new DatabaseHelper();
            $result = $db->getRow(
                "SELECT user_id FROM ws_auth_tokens WHERE token = ? AND expires_at > NOW()",
                [['value' => $token, 'type' => 's']]
            );
            
            return !empty($result);
        } catch (Exception $e) {
            error_log('令牌验证失败: ' . $e->getMessage());
            return false;
        }
    }
    
    private function compressMessage(string $message): string {
        if (!$this->enableCompression || strlen($message) < $this->compressionThreshold) {
            return $message;
        }
        
        try {
            $compressed = gzcompress($message, 6);
            return base64_encode($compressed);
        } catch (\Exception $e) {
            error_log('消息压缩失败: ' . $e->getMessage());
            return $message;
        }
    }
    
    private function decompressMessage(string $message): string {
        if (strlen($message) < $this->compressionThreshold || !base64_decode($message, true)) {
            return $message;
        }
        
        try {
            $compressed = base64_decode($message);
            return gzuncompress($compressed) ?: $message;
        } catch (\Exception $e) {
            error_log('消息解压失败: ' . $e->getMessage());
            return $message;
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        // 验证CSRF令牌
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);
        
        if (empty($params['csrf_token']) || 
            !hash_equals(SessionHelper::get($this->csrfTokenName), $params['csrf_token'])) {
            $conn->close();
            return;
        }
        
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        // 处理不同类型的消息
        switch ($data['type'] ?? '') {
            case 'subscribe_metrics':
                // 客户端订阅监控数据
                $this->clients[$from->resourceId]['subscribed'] = true;
                $this->sendInitialMetrics($from);
                break;
                
            default:
                // 广播消息给所有客户端
                foreach ($this->clients as $client) {
                    if ($client !== $from) {
                        $client->send($msg);
                    }
                }
        }
    }
    
    private function sendInitialMetrics(ConnectionInterface $client) {
        $monitor = new SystemMonitorService();
        $metrics = [
            'type' => 'initial_metrics',
            'data' => $monitor->getCurrentLoad(),
            'compressed' => $this->enableCompression
        ];
        
        $message = json_encode($metrics);
        $signedMessage = $this->signMessage($message);
        $compressedMessage = $this->compressMessage(json_encode($signedMessage));
        
        $client->send($compressedMessage);
    }
    
    public function broadcastMetrics() {
        $monitor = new SystemMonitorService();
        $metrics = [
            'type' => 'update_metrics',
            'data' => $monitor->getCurrentLoad(),
            'compressed' => $this->enableCompression
        ];
        
        $message = json_encode($metrics);
        $signedMessage = $this->signMessage($message);
        $compressedMessage = $this->compressMessage(json_encode($signedMessage));
        
        foreach ($this->clients as $client) {
            if ($client['subscribed'] ?? false) {
                $client->send($compressedMessage);
            }
        }
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $decompressed = $this->decompressMessage($msg);
            $data = json_decode($decompressed, true);
            
            // 验证签名和令牌
            if (!$this->verifySignature($data) || 
                !$this->verifyAuthToken($data['token'] ?? '')) {
                error_log('WebSocket安全验证失败: ' . $from->resourceId);
                $from->close();
                return;
            }
            
            // 处理消息内容
            $message = json_decode($data['message'], true);
            
            switch ($message['type'] ?? '') {
                case 'subscribe_metrics':
                    // 客户端订阅监控数据
                    $this->clients[$from->resourceId]['subscribed'] = true;
                    $this->clients[$from->resourceId]['user_id'] = $this->getUserIdByToken($data['token']);
                    $this->sendInitialMetrics($from);
                    break;
                    
                case 'authenticate':
                    // 更新客户端用户ID
                    $this->clients[$from->resourceId]['user_id'] = $this->getUserIdByToken($data['token']);
                    break;
                    
                default:
                    // 广播消息给所有客户端
                    foreach ($this->clients as $client) {
                        if ($client !== $from) {
                            $client->send($msg); // 保持原始消息格式
                        }
                    }
            }
        } catch (\Exception $e) {
            error_log('WebSocket消息处理错误: ' . $e->getMessage());
            $from->close();
        }
    }
    
    private function getUserIdByToken(string $token): ?int {
        try {
            $db = new DatabaseHelper();
            $result = $db->getRow(
                "SELECT user_id FROM ws_auth_tokens WHERE token = ? AND expires_at > NOW()",
                [['value' => $token, 'type' => 's']]
            );
            
            return $result['user_id'] ?? null;
        } catch (Exception $e) {
            error_log('获取用户ID失败: ' . $e->getMessage());
            return null;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("WebSocket错误: {$e->getMessage()}");
        $conn->close();
    }
}