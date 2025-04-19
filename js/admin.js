// 后台管理主逻辑
document.addEventListener('DOMContentLoaded', () => {
    // 初始化UI组件
    initUI();
    
    // 初始化API连接
    initAPI();
    
    // 加载初始数据
    loadDashboardData();
});

// 初始化UI组件
function initUI() {
    // 侧边栏折叠按钮
    document.getElementById('sidebarToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
    });
    
    // 模型设置表单提交
    document.getElementById('modelSettingsForm').addEventListener('submit', saveModelSettings);
    
    // API测试按钮
    document.getElementById('testApiBtn').addEventListener('click', testAPIConnection);
    
    // 初始化滑块控件
    initSliders();
    
    // 初始化图表
    initCharts();
}

// 初始化API连接
function initAPI() {
    // 从本地存储加载API配置
    const apiConfig = localStorage.getItem('deepseek_api_config') || {
        endpoint: 'https://api.deepseek.com/v1/chat/completions',
        apiKey: '',
        model: 'deepseek-chat'
    };
    
    // 更新UI显示
    updateAPIStatus('connecting');
    
    // 测试API连接
    testAPIConnection().then(() => {
        updateAPIStatus('connected');
    }).catch(() => {
        updateAPIStatus('error');
    });
}

// 测试API连接
async function testAPIConnection() {
    try {
        updateAPIStatus('connecting');
        
        // 根据DeepSeek API文档进行测试调用
        const response = await fetch('https://api.deepseek.com/v1/models', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${getAPIKey()}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('API连接失败');
        }
        
        const data = await response.json();
        updateAPIStatus('connected');
        return data;
    } catch (error) {
        console.error('API测试失败:', error);
        updateAPIStatus('error');
        throw error;
    }
}

// 更新API状态显示
function updateAPIStatus(status) {
    const statusElement = document.getElementById('apiStatus');
    
    switch (status) {
        case 'connected':
            statusElement.className = 'alert alert-success';
            statusElement.innerHTML = '<i class="bi bi-check-circle-fill"></i> DeepSeek API 连接正常';
            break;
        case 'error':
            statusElement.className = 'alert alert-danger';
            statusElement.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> DeepSeek API 连接失败';
            break;
        case 'connecting':
            statusElement.className = 'alert alert-warning';
            statusElement.innerHTML = '<i class="bi bi-arrow-repeat loading-spinner"></i> 正在连接DeepSeek API...';
            break;
    }
}

// 保存模型设置
async function saveModelSettings(e) {
    e.preventDefault();
    
    const settings = {
        model: document.getElementById('modelSelect').value,
        maxTokens: document.getElementById('maxTokens').value,
        temperature: document.getElementById('temperature').value
    };
    
    try {
        // 保存到本地存储
        localStorage.setItem('model_settings', JSON.stringify(settings));
        
        // 显示成功提示
        showToast('设置保存成功', 'success');
        
        // 更新API配置
        await testAPIConnection();
    } catch (error) {
        showToast('设置保存失败', 'error');
        console.error('保存设置失败:', error);
    }
}

// 加载仪表盘数据
async function loadDashboardData() {
    try {
        // 显示加载状态
        showLoading(true);
        
        // 并行加载所有数据
        const [stats, apiUsage, userDistribution] = await Promise.all([
            fetchStats(),
            fetchAPIUsage(),
            fetchUserDistribution()
        ]);
        
        // 更新UI
        updateStats(stats);
        updateAPIUsageChart(apiUsage);
        updateUserMap(userDistribution);
        
    } catch (error) {
        console.error('加载数据失败:', error);
        showToast('数据加载失败', 'error');
    } finally {
        showLoading(false);
    }
}

// 获取统计信息
async function fetchStats() {
    const response = await fetch('/api/stats', {
        headers: {
            'Authorization': `Bearer ${getAPIKey()}`
        }
    });
    
    if (!response.ok) {
        throw new Error('获取统计信息失败');
    }
    
    return response.json();
}

// 获取API使用情况
async function fetchAPIUsage() {
    const response = await fetch('/api/usage', {
        headers: {
            'Authorization': `Bearer ${getAPIKey()}`
        }
    });
    
    if (!response.ok) {
        throw new Error('获取API使用情况失败');
    }
    
    return response.json();
}

// 获取用户分布
async function fetchUserDistribution() {
    const response = await fetch('/api/users/distribution', {
        headers: {
            'Authorization': `Bearer ${getAPIKey()}`
        }
    });
    
    if (!response.ok) {
        throw new Error('获取用户分布失败');
    }
    
    return response.json();
}

// 更新统计卡片
function updateStats(data) {
    document.getElementById('totalUsers').textContent = data.totalUsers.toLocaleString();
    document.getElementById('activeUsers').textContent = data.activeUsers.toLocaleString();
    document.getElementById('onlineUsers').textContent = data.onlineUsers.toLocaleString();
    document.getElementById('apiCalls').textContent = data.apiCalls.toLocaleString();
}

// 初始化图表
function initCharts() {
    // API使用情况图表
    const apiCtx = document.getElementById('apiChart').getContext('2d');
    window.apiChart = new Chart(apiCtx, {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    
    // 用户分布地图
    window.userMap = echarts.init(document.getElementById('userMap'));
    userMap.setOption({
        title: { text: '全球用户分布', left: 'center' },
        tooltip: { trigger: 'item', formatter: '{b}: {c} 用户' },
        visualMap: {
            min: 0,
            max: 1000,
            text: ['高', '低'],
            inRange: { color: ['#e0f3f8', '#0868ac'] }
        },
        series: [{
            name: '用户数量',
            type: 'map',
            map: 'world',
            roam: true,
            emphasis: { label: { show: true } },
            data: []
        }]
    });
}

// 更新API使用图表
function updateAPIUsageChart(data) {
    window.apiChart.data.labels = data.labels;
    window.apiChart.data.datasets = [{
        label: 'API调用量',
        data: data.values,
        backgroundColor: 'rgba(78, 115, 223, 0.2)',
        borderColor: 'rgba(78, 115, 223, 1)',
        borderWidth: 2,
        tension: 0.3
    }];
    window.apiChart.update();
}

// 更新用户地图
function updateUserMap(data) {
    const option = window.userMap.getOption();
    option.series[0].data = data;
    window.userMap.setOption(option);
}

// 显示加载状态
function showLoading(show) {
    const loader = document.getElementById('loadingOverlay');
    if (show) {
        loader.style.display = 'flex';
    } else {
        loader.style.display = 'none';
    }
}

// 显示Toast提示
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type}`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// 获取API密钥
function getAPIKey() {
    return localStorage.getItem('deepseek_api_key') || '';
}

// 初始化滑块控件
function initSliders() {
    const maxTokens = document.getElementById('maxTokens');
    const maxTokensValue = document.getElementById('maxTokensValue');
    const temperature = document.getElementById('temperature');
    const temperatureValue = document.getElementById('temperatureValue');
    
    maxTokens.addEventListener('input', () => {
        maxTokensValue.textContent = maxTokens.value;
    });
    
    temperature.addEventListener('input', () => {
        temperatureValue.textContent = temperature.value;
    });
    
    // 加载保存的值
    const settings = JSON.parse(localStorage.getItem('model_settings') || '{}');
    if (settings.maxTokens) {
        maxTokens.value = settings.maxTokens;
        maxTokensValue.textContent = settings.maxTokens;
    }
    if (settings.temperature) {
        temperature.value = settings.temperature;
        temperatureValue.textContent = settings.temperature;
    }
    if (settings.model) {
        document.getElementById('modelSelect').value = settings.model;
    }
}
