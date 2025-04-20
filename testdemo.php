<?php
/**
 * 全功能测试聚合页面
 * 版权所有 广西港妙科技有限公司
 */

// 环境检测 - 仅允许测试环境访问
if (!defined('TEST_ENVIRONMENT') || !TEST_ENVIRONMENT) {
    die('测试页面仅限测试环境访问');
}

// 初始化加密组件
require_once __DIR__ . '/libs/CryptoHelper.php';
\Libs\CryptoHelper::init(
    '0123456789abcdef0123456789abcdef', // 32字节测试密钥
    '123456789012' // 12字节IV (AES-256-GCM要求)
);

// 加载配置和依赖
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/ContractService.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';

// 模拟认证
session_start();
$_SESSION['user_id'] = 1;

// 处理测试请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $module = $_POST['module'] ?? 'contract';
        $action = $_POST['action'] ?? 'test';
        $data = [];

        switch ($module) {
            case 'contract':
                $contractService = new \Services\ContractService();
                switch ($action) {
                    case 'create':
                        $data = $contractService->createContract([
                            'title' => '测试合同-' . time(),
                            'content' => '测试合同内容',
                            'parties' => [1, 2]
                        ]);
                        break;
                    case 'compliance':
                        $data = $contractService->checkContractCompliance($_POST['contract_id'] ?? 1);
                        break;
                }
                break;
                
            case 'crypto':
                $crypto = new \Libs\CryptoHelper();
                switch ($action) {
                    case 'encrypt':
                        $data = $crypto->encrypt($_POST['text'] ?? '');
                        break;
                    case 'decrypt':
                        $data = $crypto->decrypt($_POST['text'] ?? '');
                        break;
                }
                break;
                
            case 'session':
                switch ($action) {
                    case 'check':
                        \Middlewares\AuthMiddleware::check();
                        $data = ['status' => 'authenticated'];
                        break;
                }
                break;
        }

        echo json_encode([
            'success' => true,
            'module' => $module,
            'action' => $action,
            'data' => $data
        ]);
    } catch (\Exception $e) {
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
    <title>全功能测试平台</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .module { background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .module-title { margin-top: 0; color: #333; }
        .test-form { margin-bottom: 15px; }
        .test-result { padding: 15px; border: 1px solid #ddd; margin-top: 10px; display: none; }
        .success { background: #e6ffed; }
        .error { background: #ffebee; }
        pre { background: #f8f8f8; padding: 10px; overflow: auto; }
        .nav { display: flex; margin-bottom: 20px; }
        .nav a { padding: 10px 15px; margin-right: 10px; background: #eee; text-decoration: none; color: #333; }
        .nav a.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>全功能测试平台</h1>
        
        <div class="nav">
            <a href="#contract" class="active">合同测试</a>
            <a href="#crypto">加密测试</a>
            <a href="#session">会话测试</a>
        </div>
        
        <!-- 合同测试模块 -->
        <div id="contract" class="module">
            <h2 class="module-title">合同功能测试</h2>
            
            <div class="test-form">
                <h3>创建测试合同</h3>
                <button onclick="runTest('contract', 'create')">执行测试</button>
            </div>
            
            <div class="test-form">
                <h3>合规性检查</h3>
                <label>合同ID: <input type="text" id="contract_id" value="1"></label>
                <button onclick="runTest('contract', 'compliance')">执行测试</button>
            </div>
            
            <div id="contract-result" class="test-result"></div>
        </div>
        
        <!-- 加密测试模块 -->
        <div id="crypto" class="module" style="display:none;"></div>
            <h2 class="module-title">加密功能测试</h2>
            
            <div class="test-form">
                <h3>加密测试</h3>
                <textarea id="encrypt-text" rows="3" style="width:100%;">测试加密文本</textarea>
                <button onclick="runTest('crypto', 'encrypt')">加密</button>
                <button onclick="runTest('crypto', 'decrypt')">解密</button>
            </div>
            
            <div id="crypto-result" class="test-result"></div>
        </div>
        
        <!-- 会话测试模块 -->
        <div id="session" class="module" style="display:none;">
            <h2 class="module-title">会话安全测试</h2>
            
            <div class="test-form">
                <h3>会话验证</h3>
                <button onclick="runTest('session', 'check')">检查会话</button>
            </div>
            
            <div id="session-result" class="test-result"></div>
        </div>
    </div>

    <script>
        // 导航切换
        document.querySelectorAll('.nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('.module').forEach(module => {
                    module.style.display = 'none';
                });
                
                document.querySelector(this.getAttribute('href')).style.display = 'block';
            });
        });
        
        // 执行测试
        function runTest(module, action) {
            const resultDiv = document.getElementById(`${module}-result`);
            resultDiv.style.display = 'none';
            
            const formData = new FormData();
            formData.append('module', module);
            formData.append('action', action);
            
            // 模块特定参数
            if (module === 'contract' && action === 'compliance') {
                formData.append('contract_id', document.getElementById('contract_id').value);
            } else if (module === 'crypto') {
                const text = document.getElementById('encrypt-text').value;
                formData.append('text', text);
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.style.display = 'block';
                resultDiv.className = data.success ? 'test-result success' : 'test-result error';
                
                let html = `
                    <h4>${module}模块 - ${action}操作</h4>
                    <p>状态: ${data.success ? '✅ 成功' : '❌ 失败'}</p>
                    <pre>${JSON.stringify(data.data, null, 2)}</pre>
                `;
                
                if (!data.success) {
                    html += `<p>错误: ${data.error}</p>`;
                }
                
                resultDiv.innerHTML = html;
            });
        }
    </script>
</body>
</html>