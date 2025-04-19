<?php
declare(strict_types=1);

namespace Libs;

use DeepSeek\Crypto\QuantumSafeEncryptor;
use DeepSeek\Exception\CryptoException;

class QuantumCryptoHelper {
    private static ?QuantumSafeEncryptor $encryptor = null;

    public static function init(): void {
        self::$encryptor = new QuantumSafeEncryptor();
    }

    public static function encrypt($data): array {
        if (self::$encryptor === null) {
            self::init();
        }

        try {
            $payload = is_string($data) ? $data : json_encode($data);
            if ($payload === false) {
                throw new CryptoException('Failed to encode data');
            }

            $encrypted = self::$encryptor->encrypt($payload);
            return [
                'ciphertext' => $encrypted['ciphertext'],
                'nonce' => $encrypted['nonce'],
                'key_version' => $encrypted['key_version'],
                'algo' => 'quantum'
            ];
        } catch (\Throwable $e) {
            throw new CryptoException('Quantum encryption failed: ' . $e->getMessage());
        }
    }

    public static function decrypt(array $encrypted) {
        if (self::$encryptor === null) {
            self::init();
        }

        try {
            $decrypted = self::$encryptor->decrypt([
                'ciphertext' => $encrypted['ciphertext'],
                'nonce' => $encrypted['nonce'],
                'key_version' => $encrypted['key_version']
            ]);

            $decoded = json_decode($decrypted, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $decrypted;
        } catch (\Throwable $e) {
            throw new CryptoException('Quantum decryption failed: ' . $e->getMessage());
        }
    }

    public static function migrateFromLegacy(array $legacyEncrypted) {
        // Migration logic from old format to quantum format
    }
}