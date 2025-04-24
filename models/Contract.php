<?php
namespace Models;

use Libs\Exception\SecurityException;
use Libs\DatabaseHelper;
use Libs\CryptoHelper;

class Contract extends BaseModel {
    protected $table = 'contracts';
    
    public $id;
    public $title;
    public $content;
    public $created_by;
    public $status;
    public $created_at;
    public $updated_at;
    public $blockchain_txid;
    public $blockchain_timestamp;
    
    public function parties(): array {
        return ContractParty::where('contract_id', $this->id);
    }
    
    public function signatures(): array {
        return ContractSignature::where('contract_id', $this->id);
    }
    
    public static function validateContractData(array $data): void {
        if (empty($data['title'])) {
            throw new SecurityException('合同标题不能为空');
        }
        
        if (empty($data['content'])) {
            throw new SecurityException('合同内容不能为空');
        }
    }
    
    public function getEncryptedContent(): string {
        return DatabaseHelper::getInstance()->getValue(
            "SELECT content FROM {$this->table} WHERE id = ?",
            [$this->id]
        );
    }
    
    public function decryptContent(CryptoHelper $crypto): string {
        $encrypted = $this->getEncryptedContent();
        return $crypto->decrypt($encrypted);
    }
    
    public function encryptContent(CryptoHelper $crypto): void {
        $this->content = $crypto->encrypt($this->content);
    }
}