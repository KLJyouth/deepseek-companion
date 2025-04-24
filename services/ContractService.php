<?php
namespace Services;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Models\Contract;
use Models\ContractSignature;
use Libs\Exception\SecurityException;
use DeepSeek\Security\Quantum\QuantumKeyManager;

class ContractService {
    private $db;
    private $cache;
    private $crypto;
    private $keyManager;
    
    public function __construct(
        DatabaseHelper $db,
        \Services\CacheService $cache,
        CryptoHelper $crypto,
        QuantumKeyManager $keyManager
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->crypto = $crypto;
        $this->keyManager = $keyManager;
    }
    
    public function createContract(array $data): Contract {
        $this->validateContractData($data);
        
        $contract = new Contract();
        $contract->title = $data['title'];
        $contract->content = $data['content'];
        $contract->created_by = $data['created_by'];
        $contract->status = 'draft';
        
        $contract->encryptContent($this->crypto);
        
        if (!$contract->save()) {
            throw new SecurityException('创建合同失败');
        }
        
        return $contract;
    }
    
    public function signContract(int $contractId, int $userId, string $signatureData, string $algorithm = 'SM9'): array {
        $contract = Contract::find($contractId);
        if (!$contract) {
            throw new SecurityException('合同不存在');
        }
        
        // 绑定量子密钥指纹
        $quantumKeyId = $this->keyManager->getCurrentKeyId();
        
        $signature = new ContractSignature();
        $signature->contract_id = $contractId;
        $signature->user_id = $userId;
        $signature->signature = $signatureData;
        $signature->algorithm = $algorithm;
        $signature->quantum_key_id = $quantumKeyId;
        
        if ($algorithm === 'SM9') {
            $signature->sm9_params = json_encode([
                'master_public_key' => $this->keyManager->getMasterPublicKey(),
                'expiration' => $this->keyManager->getKeyExpiration()
            ]);
        }
        
        $signature->encryptSignature($this->crypto);
        
        if (!$signature->save()) {
            throw new SecurityException('保存签名失败');
        }
        
        return [
            'contract_id' => $contractId,
            'signature_id' => $signature->id,
            'signed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function blockchainArchive(Contract $contract): void {
        $content = $contract->decryptContent($this->crypto);
        
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://blockchain-api.example.com/archive', [
            'json' => [
                'content' => $content,
                'metadata' => [
                    'contract_id' => $contract->id,
                    'title' => $contract->title
                ]
            ]
        ]);
        
        $result = json_decode($response->getBody(), true);
        
        $contract->blockchain_txid = $result['txid'];
        $contract->blockchain_timestamp = date('Y-m-d H:i:s');
        $contract->save();
    }
    
    public function generateOFDContract(Contract $contract): string {
        $ofdGenerator = new \Libs\OFDGenerator();
        return $ofdGenerator->generate(
            $contract->content,
            $contract->signatures,
            $this->keyManager->getCurrentPublicKey()
        );
    }
    
    private function validateContractData(array $data): void {
        if (empty($data['title'])) {
            throw new SecurityException('合同标题不能为空');
        }
        
        if (empty($data['content'])) {
            throw new SecurityException('合同内容不能为空');
        }
        
        if (empty($data['created_by'])) {
            throw new SecurityException('必须指定合同创建者');
        }
    }
    
    public function extractTemplates(string $content): array {
        // 从合同内容中提取模板
        return [];
    }
}