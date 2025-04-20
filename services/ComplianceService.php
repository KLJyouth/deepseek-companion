<?php
/**
 * 合同合规性检测服务
 * 版权所有 广西港妙科技有限公司
 */

namespace Services;

use Libs\DatabaseHelper;
use Models\Contract;
use Models\ContractSignature;

class ComplianceService
{
    private $db;

    public function __construct()
    {
        $this->db = new DatabaseHelper();
    }

    /**
     * 检查电子签名合规性（符合《电子签名法》）
     */
    public function checkSignatureCompliance(int $contractId): array
    {
        $contract = Contract::find($contractId);
        if (!$contract) {
            return ['valid' => false, 'errors' => ['合同不存在']];
        }

        $errors = [];
        
        // 1. 检查是否有有效签名
        $signatures = ContractSignature::where('contract_id', $contractId)->get();
        if ($signatures->isEmpty()) {
            $errors[] = '合同缺少有效电子签名';
        }

        // 2. 检查签名算法是否符合要求
        foreach ($signatures as $signature) {
            if (!in_array($signature->algorithm, ['RSA-SHA256', 'RSA-SHA512'])) {
                $errors[] = '签名算法'.$signature->algorithm.'不符合《电子签名法》要求';
            }
        }

        // 3. 检查签名时间戳有效性
        if (!$contract->signed_at || $contract->signed_at > date('Y-m-d H:i:s')) {
            $errors[] = '合同签名时间无效';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'standard' => '《中华人民共和国电子签名法》'
        ];
    }

    /**
     * 检查合同主体资质
     */
    public function checkPartiesCompliance(int $contractId): array
    {
        // 实现主体资质检查逻辑
        // ...
        return ['valid' => true, 'errors' => []];
    }

    /**
     * 检查合同条款合规性
     */
    public function checkClausesCompliance(string $content): array
    {
        // 实现条款合规检查逻辑
        // ...
        return ['valid' => true, 'errors' => []];
    }

    /**
     * 生成合规性报告
     */
    public function generateComplianceReport(int $contractId): array
    {
        $signatureCheck = $this->checkSignatureCompliance($contractId);
        $partiesCheck = $this->checkPartiesCompliance($contractId);
        $clausesCheck = $this->checkClausesCompliance(
            Contract::find($contractId)->content
        );

        return [
            'signature' => $signatureCheck,
            'parties' => $partiesCheck,
            'clauses' => $clausesCheck,
            'overall_compliance' => $signatureCheck['valid'] 
                && $partiesCheck['valid'] 
                && $clausesCheck['valid']
        ];
    }
}