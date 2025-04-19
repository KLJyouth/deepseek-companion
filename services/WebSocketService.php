<?php
namespace Services;

use Libs\SessionHelper;
use Libs\CryptoHelper;

class WebSocketService {
    private $socket;
    private $clients = [];
    private $csrfTokenName = 'csrf_token';
    
    public function __construct($host = '0.0.0.0', $port = 8080) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $host, $port);
        socket_listen($this->socket);
    }

    public function run() {
        while (true) {
            $newSocket = socket_accept($this->socket);
            $header = socket_read($newSocket, 1024);
            
            if ($this->performHandshake($header, $newSocket)) {
                $this->clients[] = $newSocket;
                $this->handleClient($newSocket);
            }
        }
    }

    private function performHandshake($header, $socket) {
        // 验证CSRF令牌
        $session = SessionHelper::getInstance();
        if (!preg_match('/csrf_token=([^\s]+)/', $header, $matches) || 
            !hash_equals($session->get($this->csrfTokenName), urldecode($matches[1]))) {
            socket_close($socket);
            return false;
        }

        // WebSocket握手协议
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $header, $match)) {
            $key = base64_encode(pack(
                'H*',
                sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
            ));
            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                       "Upgrade: websocket\r\n" .
                       "Connection: Upgrade\r\n" .
                       "Sec-WebSocket-Accept: $key\r\n\r\n";
            socket_write($socket, $upgrade, strlen($upgrade));
            return true;
        }
        return false;
    }

    private function handleClient($socket) {
        while (true) {
            $data = $this->readFrame($socket);
            if ($data === false) break;
            
            $decoded = json_decode($data, true);
            if ($decoded && $this->verifyMessage($decoded)) {
                $this->broadcast($data);
            }
        }
        socket_close($socket);
    }

    private function readFrame($socket) {
        $data = socket_read($socket, 1024, PHP_BINARY_READ);
        if ($data === false) return false;
        
        $length = ord($data[1]) & 127;
        if ($length <= 125) {
            $mask = substr($data, 2, 4);
            $payload = substr($data, 6);
        } elseif ($length == 126) {
            $mask = substr($data, 4, 4);
            $payload = substr($data, 8);
        } else {
            $mask = substr($data, 10, 4);
            $payload = substr($data, 14);
        }
        
        $unmasked = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $unmasked .= $payload[$i] ^ $mask[$i % 4];
        }
        return $unmasked;
    }

    private function verifyMessage($data) {
        // 验证消息签名和时效性
        if (empty($data['timestamp']) || empty($data['signature'])) {
            return false;
        }
        
        // 5分钟内有效
        if (abs(time() - $data['timestamp']) > 300) {
            return false;
        }
        
        $expected = CryptoHelper::generateSignature($data);
        return hash_equals($expected, $data['signature']);
    }

    public function broadcast($message) {
        $frame = $this->createFrame($message);
        foreach ($this->clients as $client) {
            @socket_write($client, $frame, strlen($frame));
        }
    }

    private function createFrame($payload) {
        $frame = [];
        $payloadLength = strlen($payload);
        
        $frame[0] = 0x81; // 文本帧
        
        if ($payloadLength <= 125) {
            $frame[1] = $payloadLength;
        } elseif ($payloadLength <= 65535) {
            $frame[1] = 126;
            $frame[2] = ($payloadLength >> 8) & 0xFF;
            $frame[3] = $payloadLength & 0xFF;
        } else {
            $frame[1] = 127;
            for ($i = 0; $i < 8; $i++) {
                $frame[2 + $i] = ($payloadLength >> (8 * (7 - $i))) & 0xFF;
            }
        }
        
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame[] = ord($payload[$i]);
        }
        
        return implode(array_map("chr", $frame));
    }

    public function __destruct() {
        socket_close($this->socket);
    }
}
