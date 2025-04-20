<?php
/**
 * 合同合规性自动化测试工具
 * 版权所有 广西港妙科技有限公司
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/libs/CryptoHelper.php';

// 初始化加密组件
\Libs\CryptoHelper::init([
    'key' => 'test_encryption_key_123', // 测试环境使用固定密钥
    'cipher' => 'AES-256-CBC'
]);

require_once __DIR__ . '/services/ContractService.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';

// 模拟认证
session_start();
$_SESSION['user_id'] = 1; // 测试用户ID

// 处理测试请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $testType = $_POST['test_type'] ?? 'basic';
        $contractService = new ContractService();
        
        // 创建测试合同
        $testContract = [
            'title' => '测试合同-' . time(),
            'content' => $testType === 'invalid' ? 
                '本合同包含不合规条款...' : 
                '本合同符合《电子签名法》...',
            'parties' => [1, 2] // 测试参与方
        ];
        
        // 调用不同测试场景
        switch ($testType) {
            case 'invalid':
                $result = $contractService->checkClausesCompliance($testContract['content']);
                break;
                
            case 'signature':
                $result = $contractService->checkSignatureCompliance(1); // 测试合同ID
                break;
                
            default:
                $contractId = $contractService->createContract($testContract);
                $result = $contractService->checkContractCompliance($contractId);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'contract' => $testContract
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>合同合规性测试工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-container { max-width: 800px; margin: 0 auto; }
        .test-form { background: #f5f5f5; padding: 20px; border-radius: 5px; }
        .test-result { margin-top: 20px; padding: 15px; border: 1px solid #ddd; }
        .success { background: #e6ffed; }
        .error { background: #ffebee; }
        pre { background: #f8f8f8; padding: 10px; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>合同合规性测试工具</h1>
        
        <div class="test-form">
            <h3>选择测试场景</h3>
            <form id="testForm">
                <div>
                    <input type="radio" name="test_type" value="basic" checked> 基础合规合同
                    <input type="radio" name="test_type" value="invalid"> 不合规条款
                    <input type="radio" name="test_type" value="signature"> 签名验证
                </div>
                <button type="submit" style="margin-top: 10px;">执行测试</button>
            </form>
        </div>
        
        <div class="test-result" id="testResult" style="display: none;">
            <h3>测试结果</h3>
            <div id="resultContent"></div>
        </div>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('testResult');
                const contentDiv = document.getElementById('resultContent');
                
                resultDiv.style.display = 'block';
                resultDiv.className = data.success ? 'test-result success' : 'test-result error';
                
                if (data.success) {
                    let html = `
                        <h4>测试合同</h4>
                        <pre>${JSON.stringify(data.contract, null, 2)}</pre>
                        
                        <h4>合规检查结果</h4>
                        <pre>${JSON.stringify(data.data, null, 2)}</pre>
                        
                        <p>总体合规状态: <strong>${
                            data.data.overall_compliance ? 
                            '✅ 合规' : '❌ 不合规'
                        }</strong></p>
                    `;
                    
                    if (data.data.signature && !data.data.signature.valid) {
                        html += `
                            <div style="color: red;">
                                <h5>签名问题</h5>
                                <ul>
                                    ${data.data.signature.errors.map(err => `<li>${err}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }
                    
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `
                        <p>测试失败: ${data.error}</p>
                    `;
                }
            });
        });
    </script>
</body>
</html>