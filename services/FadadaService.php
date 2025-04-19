<?php
namespace Services;

use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Libs\Exception\SecurityException;

class FadadaService {
    const API_BASE = 'https://api.fadada.com/v4';
    private $apiKey;
    private $securityToken;

    public function __construct() {
        $this->apiKey = config('services.fadada.key');
        $this->securityToken = $this->generateDynamicToken();
    }

    private function generateDynamicToken() {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        return hash_hmac('sha256', $timestamp.$nonce, $this->apiKey);
    }

    public function createTemplate($content) {
        $payload = [
            'content' => CryptoHelper::encrypt($content),
            'timestamp' => time()
        ];
        
        $response = $this->callApi('/template/create', $payload);
        return $response['templateId'];
    }

    public function generateSignUrl($contractId, $parties) {
        $db = DatabaseHelper::getInstance();
        $contract = $db->get('contracts', $contractId);

        $params = [
            'contractId' => $contractId,
            'parties' => json_encode($parties),
            'returnUrl' => BASE_URL.'/contract/callback'
        ];

        return $this->callApi('/sign/url', $params)['signUrl'];
    }

    public function verifyCallback($data, $sign) {
        $localSign = hash_hmac('sha256', json_encode($data), CryptoHelper::decrypt(FADADA_API_SECRET));
        if (!hash_equals($localSign, $sign)) {
            throw new SecurityException('非法回调签名');
        }
    }

    private function callApi($endpoint, $data) {
        $ch = curl_init(self::API_BASE.$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: '.CryptoHelper::decrypt(FADADA_API_KEY),
                'X-Fadada-Signature: '.$this->generateRequestSignature($payload),
            'X-Fadada-Timestamp: '.time(),
            'X-Fadada-Nonce: '.bin2hex(random_bytes(8)),
            'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = json_decode(curl_exec($ch), true);
        if ($response['code'] !== 200) {
            throw new \RuntimeException('法大大API错误: '.$response['message']);
        }
        return $response['data'];
    }
}