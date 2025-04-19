<?php
declare(strict_types=1);

namespace DeepSeek\Crypto;

use DeepSeek\Exception\CryptoException;
use SodiumException;

final class QuantumSafeEncryptor {
    private const KEY_ROTATION_INTERVAL = 3600;
    
    private string $currentKey;
    private array $keyVersions = [];
    
    public function __construct() {
        $this->rotateKey();
    }
    
    public function encrypt(string $data): array {
        $this->validateKeyRotation();
        
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox(
                $data, 
                $nonce,
                $this->currentKey
            );
            
            return [
                'ciphertext' => base64_encode($encrypted),
                'nonce' => base64_encode($nonce),
                'key_version' => $this->getCurrentKeyVersion()
            ];
        } catch (SodiumException $e) {
            throw new CryptoException('Quantum encryption failed: '.$e->getMessage());
        }
    }
    
    public function decrypt(array $payload): string {
        $this->validateKeyVersion($payload['key_version']);
        
        try {
            return sodium_crypto_secretbox_open(
                base64_decode($payload['ciphertext']),
                base64_decode($payload['nonce']),
                $this->keyVersions[$payload['key_version']]
            );
        } catch (SodiumException $e) {
            throw new CryptoException('Quantum decryption failed: '.$e->getMessage());
        }
    }
    
    private function rotateKey(): void {
        $newKey = sodium_crypto_secretbox_keygen();
        $this->keyVersions[time()] = $newKey;
        $this->currentKey = $newKey;
        $this->cleanupOldKeys();
    }
    
    private function validateKeyRotation(): void {
        if (time() - $this->getCurrentKeyVersion() > self::KEY_ROTATION_INTERVAL) {
            $this->rotateKey();
        }
    }
    
    private function validateKeyVersion(int $version): void {
        if (!isset($this->keyVersions[$version])) {
            throw new CryptoException('Invalid key version');
        }
    }
    
    private function getCurrentKeyVersion(): int {
        return array_key_last($this->keyVersions);
    }
    
    private function cleanupOldKeys(): void {
        $cutoff = time() - (self::KEY_ROTATION_INTERVAL * 3);
        foreach ($this->keyVersions as $version => $key) {
            if ($version < $cutoff) {
                unset($this->keyVersions[$version]);
            }
        }
    }
}