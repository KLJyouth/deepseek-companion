<?php
namespace Models;

use Libs\Exception\SecurityException;
use Libs\DatabaseHelper;
use Libs\CryptoHelper;

class ContractSignature extends BaseModel {
    protected $table = 'contract_signatures';
    
    public $id;
    public $contract_id;
    public $user_id;
    public $signature;
    public $algorithm;
    public $quantum_key_id;
    public $sm9_params;
    public $signed_at;
    
    public function contract(): ?Contract {
        return Contract::find($this->contract_id);
    }
    
    public function user(): ?User {
        return User::find($this->user_id);
    }
    
    public static function validateSignatureData(array $data): void {
        if (empty($data['contract_id'])) {
            throw new SecurityException('合同ID不能为空');
        }
        
        if (empty($data['user_id'])) {
            throw new SecurityException('用户ID不能为空');
        }
        
        if (empty($data['signature'])) {
            throw new SecurityException('签名数据不能为空');
        }
    }
    
    public function getEncryptedSignature(): string {
        return DatabaseHelper::getInstance()->getValue(
            "SELECT signature FROM {$this->table} WHERE id = ?",
            [$this->id]
        );
    }
    
    public function decryptSignature(CryptoHelper $crypto): string {
        $encrypted = $this->getEncryptedSignature();
        return $crypto->decrypt($encrypted);
    }
    
    public function encryptSignature(CryptoHelper $crypto): void {
        $this->signature = $crypto->encrypt($this->signature);
    }
}