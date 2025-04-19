<?php
namespace Controllers;

use Libs\AuthMiddleware;
use Libs\CryptoHelper;
use Libs\DatabaseHelper;
use Libs\Exception\SecurityException;

class ContractController {
    private $signService;
    
    public function __construct() {
        $this->signService = new \Services\ApiSignService();
    }
    
    // 合同模板管理
    public function createTemplateAction() {
        AuthMiddleware::verifyAdmin();
        
        // 验证API签名
        $signature = $_SERVER['HTTP_X_API_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_API_TIMESTAMP'] ?? '';
        
        if (empty($signature) || empty($timestamp)) {
            throw new SecurityException('缺少必要的签名头信息');
        }
        
        // 验证时间戳
        if (!$this->signService->validateTimestamp($timestamp)) {
            throw new SecurityException('请求已过期');
        }
        
        // 验证签名
        $data = $_POST;
        if (!$this->signService->verifySignature($data, $timestamp, $signature)) {
            throw new SecurityException('签名验证失败');
        }
        
        $db = DatabaseHelper::getInstance();
        $templateData = [
            'template_name' => $_POST['name'],
            'template_content' => CryptoHelper::encrypt($_POST['content']),
            'created_by' => $_SESSION['user_id']
        ];
        
        return $db->insert('contract_templates', $templateData);
    }

    // 合同实例生成
    public function createContractAction() {
        AuthMiddleware::verifyAuth();
        
        $templateId = CryptoHelper::decrypt($_POST['template_id']);
        $db = DatabaseHelper::getInstance();
        
        $contractData = [
            'template_id' => $templateId,
            'parties' => json_encode($_POST['parties']),
            'contract_content' => CryptoHelper::encrypt($_POST['content'])
        ];
        
        return $db->insert('contracts', $contractData);
    }

    // 生成法大大签约URL
    public function getSignUrlAction() {
        AuthMiddleware::verifyContractAccess($contractId);
        
        $contract = DatabaseHelper::getInstance()->get('contracts', $contractId);
        
        // 调用法大大API生成签约链接
        return [
            'sign_url' => $this->generateFadadaSignUrl($contract),
            'expires_at' => time() + 3600
        ];
    }

    // 处理法大大回调
    public function handleCallbackAction() {
        $rawData = file_get_contents('php://input');
        
        if (!$this->verifyFadadaSignature($rawData)) {
            throw new SecurityException('非法回调请求');
        }
        
        $data = json_decode($rawData, true);
        $db = DatabaseHelper::getInstance();
        
        $db->update('contracts', [
            'status' => $data['status'],
            'fadada_evidence_id' => $data['evidenceId']
        ], ['id' => $data['contractId']]);
        
        // 调用DeepSeek审计接口
        $this->submitDeepseekAudit($data['contractId']);
    }

    private function generateFadadaSignUrl($contract) {
        // 实现法大大API调用逻辑
    }

    private function verifyFadadaSignature($data) {
        // 实现签名验证逻辑
    }

    private function submitDeepseekAudit($contractId) {
        // 调用DeepSeek审计接口
    }
}