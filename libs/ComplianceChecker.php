<?php
/**
 * 信创合规审查系统
 * 版权所有 © 广西港妙科技有限公司
 */

namespace Libs;

use DeepSeek\Security\Quantum\QuantumKeyManager;
use Exception;

class ComplianceChecker
{
    /**
     * @var QuantumKeyManager 量子密钥管理器
     */
    private QuantumKeyManager $keyManager;

    // 法律条款特征库
    private const LEGAL_CLAUSES = [
        '网络安全法' => [
            '第二十一条' => '国家实行网络安全等级保护制度',
            '第三十一条' => '关键信息基础设施运营者采购网络产品和服务需通过安全审查'
        ],
        '电子签名法' => [
            '第十三条' => '可靠的电子签名需满足真实身份、真实意愿、签名未改、内容未改',
            '第十四条' => '可靠的电子签名与手写签名具有同等法律效力'
        ]
    ];

    public function __construct(QuantumKeyManager $keyManager)
    {
        $this->keyManager = $keyManager;
    }

    /**
     * 执行深度合规审查
     */
    public function fullComplianceCheck(string $content): array
    {
        return [
            'security_law' => $this->checkCyberSecurityLaw($content),
            'esign_law' => $this->checkESignatureLaw($content),
            'quantum_safe' => $this->verifyQuantumSecurity()
        ];
    }

    /**
     * 网络安全法合规检查
     */
    private function checkCyberSecurityLaw(string $content): array
    {
        $results = [];
        foreach (self::LEGAL_CLAUSES['网络安全法'] as $article => $requirement) {
            $results[$article] = mb_strpos($content, $requirement) !== false;
        }
        return $results;
    }

    /**
     * 电子签名法合规验证
     */
    private function checkESignatureLaw(string $content): array
    {
        $validation = [
            'identity_verified' => preg_match('/身份认证机制/i', $content),
            'intention_confirmed' => preg_match('/意愿确认流程/i', $content),
            'tamper_proof' => preg_match('/哈希校验|数字指纹/i', $content)
        ];

        return array_merge($validation, [
            'legal_articles' => array_filter(
                self::LEGAL_CLAUSES['电子签名法'],
                fn($req) => mb_strpos($content, $req) !== false
            )
        ]);
    }

    /**
     * 量子安全验证
     */
    private function verifyQuantumSecurity(): bool
    {
        try {
            return $this->keyManager->verifyCurrentKeySafety()
                && $this->keyManager->getActiveAlgorithm() === 'CRYSTALS-Kyber-768';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 生成OFD合规报告
     */
    public function generateOFDReport(array $checkResults): string
    {
        $ofdHeader = "<?xml version='1.0' encoding='UTF-8'?>
            <ofd:OFD xmlns:ofd='http://www.ofdspec.org' version='1.2'>
            <ofd:DocBody>";

        $complianceSection = "<ComplianceReport>".
            $this->buildXmlSection('网络安全法', $checkResults['security_law']).
            $this->buildXmlSection('电子签名法', $checkResults['esign_law']).
            "<QuantumSecurity>{$checkResults['quantum_safe']}</QuantumSecurity>".
            "</ComplianceReport>";

        return $this->keyManager->encryptData(
            $ofdHeader . $complianceSection . "</ofd:DocBody></ofd:OFD>"
        );
    }

    private function buildXmlSection(string $lawName, array $results): string
    {
        $xml = "<{$lawName}>";
        foreach ($results as $key => $value) {
            $xml .= "<{$key}>" . ($value ? '合规' : '不合规') . "</{$key}>";
        }
        return $xml . "</{$lawName}>";
    }
}