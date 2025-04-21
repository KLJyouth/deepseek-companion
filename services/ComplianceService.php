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
    public function __construct(
        private readonly DatabaseHelper $db = new DatabaseHelper()
    ) {
        $this->db = new DatabaseHelper();
    }

    /**
     * 检查电子签名合规性（符合《电子签名法》）
     */
    /**
     * 检查电子签名合规性（符合《电子签名法》）
     * @param int $contractId 合同ID
     * @return array{valid:bool,errors:array<string>,standard:string} 合规性检查结果
     * @throws RuntimeException 当合同不存在或检查失败时
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
    /**
     * 检查合同主体资质
     * @param int $contractId 合同ID
     * @return array{valid:bool,errors:array<string>,standards:array<string>} 合规性检查结果
     * @throws RuntimeException 当合同不存在或检查失败时
     */
    public function checkPartiesCompliance(int $contractId): array
    {
        $contract = Contract::find($contractId);
        if (!$contract) {
            return ['valid' => false, 'errors' => ['合同不存在']];
        }

        $errors = [];
        $parties = json_decode($contract->parties, true);

        foreach ($parties as $party) {
            // 检查主体类型
            if (!isset($party['type']) || !in_array($party['type'], ['individual', 'company'])) {
                $errors[] = "主体类型无效: {$party['name']}";
            }

            // 检查证件有效性
            if (!isset($party['id_type']) || !isset($party['id_number'])) {
                $errors[] = "缺少有效证件信息: {$party['name']}";
            }

            // 检查营业执照(如果是企业)
            if ($party['type'] === 'company') {
                if (!isset($party['business_license'])) {
                    $errors[] = "企业缺少营业执照: {$party['name']}";
                } else {
                    // 查询企业信用信息
                    $credit = $this->db->query(
                        "SELECT * FROM company_credits WHERE license_no = ?", 
                        [$party['business_license']]
                    );
                    
                    if (empty($credit)) {
                        $errors[] = "企业信用信息不存在: {$party['name']}";
                    } elseif ($credit['status'] !== 'normal') {
                        $errors[] = "企业存在经营异常: {$party['name']}";
                    }
                }
            }

            // 检查年龄限制(如果是个人)
            if ($party['type'] === 'individual') {
                if (!isset($party['birthday'])) {
                    $errors[] = "缺少出生日期信息: {$party['name']}";
                } else {
                    $age = date_diff(date_create($party['birthday']), date_create('now'))->y;
                    if ($age < 18) {
                        $errors[] = "签约方未满18周岁: {$party['name']}";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'standards' => [
                '《中华人民共和国民法典》',
                '《中华人民共和国公司法》',
                '《企业信息公示暂行条例》'
            ]
        ];
    }

    /**
     * 检查合同条款合规性
     */
    /**
     * 检查合同条款合规性
     * @param string $content 合同内容
     * @return array{valid:bool,errors:array<string>,standards:array<string>} 合规性检查结果
     */
    public function checkClausesCompliance(string $content): array
    {
        $errors = [];
        
        // 1. 检查强制性条款
        $requiredClauses = [
            '合同标的',
            '价格或报酬',
            '履行期限',
            '违约责任'
        ];
        
        foreach ($requiredClauses as $clause) {
            if (strpos($content, $clause) === false) {
                $errors[] = "缺少必要条款: {$clause}";
            }
        }

        // 2. 检查非法条款
        $illegalPatterns = [
            '/违反法律法规/',
            '/损害国家利益/',
            '/违反公序良俗/',
            '/免除责任/',
            '/霸王条款/',
            '/强制仲裁/'
        ];

        foreach ($illegalPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "包含非法条款: " . str_replace('/', '', $pattern);
            }
        }

        // 3. 检查格式规范
        if (!preg_match('/第.*?条/', $content)) {
            $errors[] = "条款格式不规范(缺少条款序号)";
        }

        // 4. 检查完整性
        if (!preg_match('/合同签订地/', $content)) {
            $errors[] = "缺少合同签订地点";
        }
        if (!preg_match('/签订时间|签署日期/', $content)) {
            $errors[] = "缺少合同签订时间";
        }

        // 5. 检查语言使用
        $ambiguousTerms = [
            '等', '及其他', '以上', '以下', 
            '相关', '适当', '合理'
        ];
        
        foreach ($ambiguousTerms as $term) {
            if (substr_count($content, $term) > 5) {
                $errors[] = "过度使用模糊词语: {$term}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'standards' => [
                '《中华人民共和国民法典》',
                '《中华人民共和国合同法》',
                '《最高人民法院关于适用<中华人民共和国合同法>若干问题的解释》'
            ]
        ];
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