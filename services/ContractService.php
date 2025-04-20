<?php
/**
 * stanfai-司单服Ai智能安全法务 - 合同服务
 * 版权所有 广西港妙科技有限公司
 */

namespace Services;

use Libs\DatabaseHelper;
use Libs\CryptoHelper;
use Models\Contract;
use Models\ContractTemplate;
use Models\ContractSignature;

class ContractService
{
    private $db;
    private $crypto;

    public function __construct()
    {
        $this->db = new DatabaseHelper();
        $this->crypto = new CryptoHelper();
        $this->compliance = new ComplianceService();
    }

    /**
     * 创建合同模板
     */
    public function createTemplate(string $name, string $content, int $creatorId): array
    {
        // 内容加密存储
        $encryptedContent = $this->crypto->encrypt($content);
        
        $template = new ContractTemplate();
        $template->name = $name;
        $template->content = $encryptedContent;
        $template->created_by = $creatorId;
        
        if ($template->save()) {
            return ['success' => true, 'template_id' => $template->id];
        }
        
        return ['success' => false, 'error' => '模板创建失败'];
    }

    /**
     * 签署合同
     */
    public function signContract(int $contractId, int $userId, string $signatureData, string $algorithm = 'RSA-SHA512'): array
    {
        // 验证合同状态
        $contract = Contract::find($contractId);
        if (!$contract || $contract->status !== 'pending') {
            return ['success' => false, 'error' => '合同不可签署'];
        }

        // 创建签名记录
        $signature = new ContractSignature();
        $signature->contract_id = $contractId;
        $signature->user_id = $userId;
        $signature->signature = $this->crypto->encrypt($signatureData);
        $signature->algorithm = $algorithm;
        
        if ($signature->save()) {
            // 更新合同状态
            $contract->status = 'signed';
            $contract->signed_at = date('Y-m-d H:i:s');
            $contract->save();
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => '签署失败'];
    }

    /**
     * 归档合同
     */
    public function archiveContract(int $contractId): array
    {
        $contract = Contract::find($contractId);
        if (!$contract || $contract->status !== 'signed') {
            return ['success' => false, 'error' => '合同不可归档'];
        }

        $contract->status = 'archived';
        $contract->archived_at = date('Y-m-d H:i:s');
        
        if ($contract->save()) {
            // 区块链存证
            $this->blockchainArchive($contract);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => '归档失败'];
    }

    /**
     * 区块链存证
     */
    private function blockchainArchive(Contract $contract): void
    {
        // 获取合同内容
        $content = $this->crypto->decrypt($contract->content);
        
        // 生成数字指纹
        $hash = hash('sha256', $content);
        
        // 调用区块链存证服务
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://blockchain-api.example.com/archive', [
            'json' => [
                'contract_id' => $contract->id,
                'content_hash' => $hash,
                'timestamp' => time(),
                'signature' => $this->generateBlockchainSignature($contract)
            ]
        ]);
        
        // 记录存证结果
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $contract->blockchain_txid = $data['txid'];
            $contract->blockchain_timestamp = $data['timestamp'];
            $contract->save();
        }
    }

    private function generateBlockchainSignature(Contract $contract): string
    {
        // 使用数字证书生成签名
        $privateKey = openssl_pkey_get_private(
            file_get_contents(getenv('BLOCKCHAIN_SIGN_KEY'))
        );
        
        $data = $contract->id . $contract->signed_at;
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        return base64_encode($signature);
    }

    public function generateLegalCertificate(int $contractId): array
    {
        $contract = Contract::find($contractId);
        if (!$contract) {
            return ['success' => false, 'error' => '合同不存在'];
        }

        // 调用公证处API生成法律效力证书
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://notary-api.example.com/certificate', [
            'json' => [
                'contract_id' => $contract->id,
                'txid' => $contract->blockchain_txid,
                'timestamp' => $contract->blockchain_timestamp,
                'parties' => $contract->signatures->pluck('user_id')->toArray()
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            return ['success' => true, 'certificate' => $data['certificate_url']];
        }

        return ['success' => false, 'error' => '证书生成失败'];
    }

    /**
     * AI风险分析
     */
    public function analyzeContractRisk(string $content): array
    {
        // 实现AI风险分析逻辑
        // ...
        return ['risk_level' => 'medium', 'risky_clauses' => []];
    }

    /**
     * 自动采集合同模板
     */
    public function crawlTemplates(array $sources): array
    {
        $results = [];
        $httpClient = new \GuzzleHttp\Client();
        
        foreach ($sources as $source) {
            try {
                $response = $httpClient->get($source['url']);
                $content = $response->getBody()->getContents();
                
                // 提取模板内容
                $templates = $this->extractTemplates($content, $source['type']);
                
                foreach ($templates as $template) {
                    // 保存模板
                    $result = $this->createTemplate(
                        $template['name'],
                        $template['content'],
                        $_SESSION['user_id'] ?? 0
                    );
                    
                    if ($result['success']) {
                        $results[] = [
                            'source' => $source['name'],
                            'template_id' => $result['template_id']
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log("采集合同模板失败: {$source['name']} - " . $e->getMessage());
            }
        }
        
        return $results;
    }

    private function extractTemplates(string $content, string $type): array
    {
        // 根据不同类型提取模板
        switch ($type) {
            case 'html':
                return $this->extractFromHtml($content);
            case 'pdf':
                return $this->extractFromPdf($content);
            case 'docx':
                return $this->extractFromDocx($content);
            default:
                return [];
        }
    }

    private function extractFromHtml(string $content): array
    {
        // 实现HTML内容提取逻辑
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        
        $templates = [];
        $contractSections = $dom->getElementsByTagName('section');
        
        foreach ($contractSections as $section) {
            if ($section->getAttribute('class') === 'contract-template') {
                $templates[] = [
                    'name' => $section->getAttribute('data-name'),
                    'content' => $dom->saveHTML($section)
                ];
            }
        }
        
        return $templates;
    }

    /**
     * 微调AI模型
     */
    public function fineTuneModel(array $templateIds): bool
    {
        // 获取模板内容
        $templates = ContractTemplate::whereIn('id', $templateIds)->get();
        
        // 准备训练数据
        $trainingData = [];
        foreach ($templates as $template) {
            $trainingData[] = [
                'content' => $this->crypto->decrypt($template->content),
                'category' => $template->category
            ];
        }
        
        // 调用AI微调API
        $client = new \GuzzleHttp\Client();
        $response = $client->post('http://ai-service/api/fine-tune', [
            'json' => ['data' => $trainingData]
        ]);
        
        return $response->getStatusCode() === 200;
    }

    /**
     * 检查合同合规性
     * @param int $contractId 合同ID
     * @return array 合规性报告
     */
    public function checkContractCompliance(int $contractId): array
    {
        try {
            return $this->compliance->generateComplianceReport($contractId);
        } catch (\Exception $e) {
            error_log("合规检查失败: " . $e->getMessage());
            return [
                'valid' => false,
                'errors' => ['合规检查服务异常'],
                'exception' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查合同条款合规性
     * @param string $content 合同内容
     * @return array 合规性结果
     */
    public function checkClausesCompliance(string $content): array
    {
        // 解密内容(如果是加密存储的)
        $decrypted = $this->crypto->decrypt($content);
        
        return $this->compliance->checkClausesCompliance($decrypted);
    }
}