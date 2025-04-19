/**
 * stanfai-合同管理前端逻辑
 * 版权所有 广西港妙科技有限公司
 */

class ContractManager {
    constructor() {
        this.initEventListeners();
        this.loadContractTemplates();
    }

    // 初始化事件监听
    initEventListeners() {
        // 合同列表页面
        if (document.getElementById('contractTableBody')) {
            document.getElementById('refreshList').addEventListener('click', () => this.loadContracts());
            document.getElementById('statusFilter').addEventListener('change', () => this.loadContracts());
            document.getElementById('searchInput').addEventListener('input', () => this.loadContracts());
            this.loadContracts();
        }

        // 合同创建页面
        if (document.getElementById('contractForm')) {
            document.getElementById('addParty').addEventListener('click', () => this.addPartyField());
            document.getElementById('contractForm').addEventListener('submit', (e) => this.handleCreateContract(e));
            document.getElementById('cancelCreate').addEventListener('click', () => window.location.href = 'index.html');
        }

        // 合同签署页面
        if (document.getElementById('signaturePad')) {
            this.initSignaturePad();
            document.querySelectorAll('input[name="signMethod"]').forEach(radio => {
                radio.addEventListener('change', (e) => this.toggleSignMethod(e.target.value));
            });
            document.getElementById('submitSign').addEventListener('click', () => this.handleSignContract());
            document.getElementById('cancelSign').addEventListener('click', () => window.history.back());
        }

        // 合同查看页面
        if (document.getElementById('contractStatus')) {
            this.loadContractDetails();
            document.getElementById('backToList').addEventListener('click', () => window.location.href = 'index.html');
        }
    }

    // 加载合同列表
    async loadContracts() {
        try {
            const statusFilter = document.getElementById('statusFilter').value;
            const searchQuery = document.getElementById('searchInput').value;
            
            const response = await fetch(`/api/contracts?status=${statusFilter}&q=${encodeURIComponent(searchQuery)}`);
            const contracts = await response.json();
            
            const tableBody = document.getElementById('contractTableBody');
            tableBody.innerHTML = '';
            
            contracts.forEach(contract => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${contract.id}</td>
                    <td>${contract.name}</td>
                    <td><span class="status-badge ${contract.status}">${this.getStatusText(contract.status)}</span></td>
                    <td>${new Date(contract.created_at).toLocaleString()}</td>
                    <td>
                        <a href="view.html?id=${contract.id}" class="btn-view">查看</a>
                        ${contract.status === 'pending' ? `<a href="sign.html?id=${contract.id}" class="btn-sign">签署</a>` : ''}
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } catch (error) {
            console.error('加载合同列表失败:', error);
            alert('加载合同列表失败，请稍后重试');
        }
    }

    // 加载合同模板
    async loadContractTemplates() {
        if (!document.getElementById('templateSelect')) return;
        
        try {
            const response = await fetch('/api/contract-templates');
            const templates = await response.json();
            
            const select = document.getElementById('templateSelect');
            templates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('加载合同模板失败:', error);
        }
    }

    // 处理创建合同
    async handleCreateContract(e) {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('contractName').value,
            template_id: document.getElementById('templateSelect').value,
            content: document.getElementById('contractContent').value,
            parties: this.getPartiesData()
        };
        
        try {
            const response = await fetch('/api/contracts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            if (result.success) {
                alert('合同创建成功');
                window.location.href = `view.html?id=${result.contract_id}`;
            } else {
                alert(`创建失败: ${result.error}`);
            }
        } catch (error) {
            console.error('创建合同失败:', error);
            alert('创建合同失败，请稍后重试');
        }
    }

    // 初始化签名板
    initSignaturePad() {
        const canvas = document.getElementById('signaturePad');
        this.signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        document.getElementById('clearSignature').addEventListener('click', () => {
            this.signaturePad.clear();
        });
    }

    // 处理合同签署
    async handleSignContract() {
        const contractId = new URLSearchParams(window.location.search).get('id');
        const signMethod = document.querySelector('input[name="signMethod"]:checked').value;
        
        try {
            let signatureData;
            if (signMethod === 'digital') {
                if (this.signaturePad.isEmpty()) {
                    alert('请先完成签名');
                    return;
                }
                signatureData = this.signaturePad.toDataURL();
            } else {
                // 生物识别签名逻辑
                signatureData = await this.handleBiometricSign();
            }
            
            const response = await fetch(`/api/contracts/${contractId}/sign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    signature: signatureData,
                    algorithm: signMethod === 'digital' ? 'RSA-SHA512' : 'BIOMETRIC'
                })
            });
            
            const result = await response.json();
            if (result.success) {
                alert('合同签署成功');
                window.location.href = `view.html?id=${contractId}`;
            } else {
                alert(`签署失败: ${result.error}`);
            }
        } catch (error) {
            console.error('签署合同失败:', error);
            alert('签署合同失败，请稍后重试');
        }
    }

    // 加载合同详情
    async loadContractDetails() {
        const contractId = new URLSearchParams(window.location.search).get('id');
        
        try {
            const response = await fetch(`/api/contracts/${contractId}`);
            const contract = await response.json();
            
            // 填充基本信息
            document.getElementById('contractTitle').textContent = contract.name;
            document.getElementById('contractId').textContent = contract.id;
            document.getElementById('contractStatus').textContent = this.getStatusText(contract.status);
            document.getElementById('createdAt').textContent = new Date(contract.created_at).toLocaleString();
            document.getElementById('signedAt').textContent = contract.signed_at ? new Date(contract.signed_at).toLocaleString() : '未签署';
            document.getElementById('archivedAt').textContent = contract.archived_at ? new Date(contract.archived_at).toLocaleString() : '未归档';
            
            // 填充合同内容
            document.getElementById('contractContent').innerHTML = contract.content;
            
            // 加载签署记录
            this.loadSignatures(contractId);
            
            // 加载风险分析
            if (contract.status === 'signed' || contract.status === 'archived') {
                this.loadRiskAnalysis(contractId);
            }
        } catch (error) {
            console.error('加载合同详情失败:', error);
            alert('加载合同详情失败，请稍后重试');
        }
    }

    // 辅助方法
    getStatusText(status) {
        const statusMap = {
            draft: '草稿',
            pending: '待签署',
            signed: '已签署',
            archived: '已归档'
        };
        return statusMap[status] || status;
    }

    // 更多方法实现...
}

// 初始化合同管理
document.addEventListener('DOMContentLoaded', () => {
    new ContractManager();
});
