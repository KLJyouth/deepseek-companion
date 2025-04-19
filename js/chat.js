// 配置
const CONFIG = {
    API_KEY: 'sk-09f15a7a15774fafae8a477f658c3afb', // 替换为实际API密钥
    API_ENDPOINT: 'https://api.deepseek.com/v1/chat/completions',
    MODEL: 'deepseek-chat',
    MAX_TOKENS: 500,
    TEMPERATURE: 0.7,
    GENDER: 'male', // 默认性别
    ATTITUDE: 1, // 默认态度值(0-2)
    MEMORY_ENABLED: true // 默认开启记忆
};

// DOM元素
const messageInput = document.getElementById('messageInput');
const sendButton = document.getElementById('sendButton');
const chatMessages = document.getElementById('chatMessages');
const genderButtons = document.querySelectorAll('.gender-btn');
const attitudeSlider = document.getElementById('attitudeSlider');
const attitudeValue = document.getElementById('attitudeValue');
const memoryToggle = document.getElementById('memoryToggle');

// 对话历史
let conversationHistory = [];
let currentUser = 'default_user';

// 初始化
async function init() {
    // 检查登录状态
    const isLoggedIn = await checkLoginStatus();
    
    if (!isLoggedIn) {
        showLoginModal();
        return;
    }
    
    // 初始化事件监听
    sendButton.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
    
    // 初始化控件事件
    initControls();
    
    // 加载用户数据和设置
    await loadUserData();
    
    // 初始化模型动画
    updateModelAnimation(CONFIG.ATTITUDE);
    
    // 初始化姓名对话框
    initNameDialog();
    
    // 预加载记忆数据
    if (CONFIG.MEMORY_ENABLED) {
        await preloadMemoryData();
    }
    
    // 欢迎消息
    addMessage('assistant', getWelcomeMessage());
}

// 检查登录状态
async function checkLoginStatus() {
    const token = localStorage.getItem('auth_token');
    if (!token) return false;
    
    try {
        const response = await fetch('/api/auth/check', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        return response.ok;
    } catch (error) {
        console.error('检查登录状态失败:', error);
        return false;
    }
}

// 显示登录模态框
function showLoginModal() {
    const modal = document.createElement('div');
    modal.className = 'login-modal';
    modal.innerHTML = `
        <div class="login-content">
            <h3>欢迎使用AI伴侣</h3>
            <form id="loginForm">
                <div class="form-group">
                    <input type="text" id="username" placeholder="用户名" required>
                </div>
                <div class="form-group">
                    <input type="password" id="password" placeholder="密码" required>
                </div>
                <button type="submit" class="login-btn">登录</button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            });
            
            if (!response.ok) {
                throw new Error('登录失败');
            }
            
            const data = await response.json();
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('current_user', data.userId);
            
            modal.remove();
            init();
        } catch (error) {
            alert('登录失败，请重试');
            console.error('登录错误:', error);
        }
    });
}

// 加载用户数据
async function loadUserData() {
    try {
        const userId = localStorage.getItem('current_user');
        if (!userId) return;
        
        // 并行加载设置和历史数据
        const [settings, history] = await Promise.all([
            fetchUserSettings(userId),
            CONFIG.MEMORY_ENABLED ? fetchConversationHistory(userId) : Promise.resolve([])
        ]);
        
        // 更新配置
        if (settings) {
            CONFIG.GENDER = settings.gender || 'male';
            CONFIG.ATTITUDE = settings.attitude || 1;
            CONFIG.MEMORY_ENABLED = settings.memoryEnabled !== false;
            
            // 更新UI
            updateUIControls();
        }
        
        // 更新对话历史
        conversationHistory = history || [];
        
    } catch (error) {
        console.error('加载用户数据失败:', error);
    }
}

// 预加载记忆数据
async function preloadMemoryData() {
    try {
        const userId = localStorage.getItem('current_user');
        if (!userId) return;
        
        const history = await fetchConversationHistory(userId);
        if (history && history.length > 0) {
            // 显示历史消息
            chatMessages.innerHTML = '';
            history.forEach(msg => {
                if (msg.role !== 'system') {
                    addMessage(msg.role, msg.content);
                }
            });
        }
    } catch (error) {
        console.error('预加载记忆数据失败:', error);
    }
}

// 获取用户设置
async function fetchUserSettings(userId) {
    const response = await fetch(`/api/users/${userId}/settings`, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
    });
    
    if (!response.ok) {
        throw new Error('获取用户设置失败');
    }
    
    return response.json();
}

// 获取对话历史
async function fetchConversationHistory(userId) {
    const response = await fetch(`/api/users/${userId}/conversations`, {
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
    });
    
    if (!response.ok) {
        throw new Error('获取对话历史失败');
    }
    
    return response.json();
}

// 更新UI控件
function updateUIControls() {
    // 更新性别选择
    document.querySelector(`.gender-btn[data-gender="${CONFIG.GENDER}"]`).classList.add('active');
    
    // 更新态度滑块
    attitudeSlider.value = CONFIG.ATTITUDE;
    attitudeValue.textContent = getAttitudeText(CONFIG.ATTITUDE);
    
    // 更新记忆开关
    memoryToggle.checked = CONFIG.MEMORY_ENABLED;
}

// 初始化姓名对话框
function initNameDialog() {
    const nameBtn = document.getElementById('nameBtn');
    const nameDialog = document.getElementById('nameDialog');
    const nameInput = document.getElementById('nameInput');
    const confirmName = document.getElementById('confirmName');
    const cancelName = document.getElementById('cancelName');
    
    // 打开对话框
    nameBtn.addEventListener('click', () => {
        nameInput.value = localStorage.getItem(`user_name_${currentUser}`) || '';
        nameDialog.classList.add('active');
        nameInput.focus();
    });
    
    // 确认姓名
    confirmName.addEventListener('click', () => {
        const name = nameInput.value.trim();
        if (name) {
            localStorage.setItem(`user_name_${currentUser}`, name);
            nameDialog.classList.remove('active');
            
            // 更新欢迎消息
            const welcomeMsg = getWelcomeMessage();
            const lastMessage = chatMessages.lastElementChild;
            if (lastMessage && lastMessage.classList.contains('assistant')) {
                lastMessage.querySelector('.message-bubble').textContent = welcomeMsg;
            }
        } else {
            alert('请输入有效的名字');
        }
    });
    
    // 取消
    cancelName.addEventListener('click', () => {
        nameDialog.classList.remove('active');
    });
    
    // 点击遮罩层关闭
    nameDialog.addEventListener('click', (e) => {
        if (e.target === nameDialog) {
            nameDialog.classList.remove('active');
        }
    });
    
    // 回车确认
    nameInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            confirmName.click();
        }
    });
}

// 初始化控件事件
function initControls() {
    // 性别选择
    genderButtons.forEach(button => {
        button.addEventListener('click', () => {
            genderButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            CONFIG.GENDER = button.dataset.gender;
            saveSettings();
            updateCompanionModel();
        });
    });
    
    // 态度调整
    attitudeSlider.addEventListener('input', () => {
        CONFIG.ATTITUDE = parseFloat(attitudeSlider.value);
        attitudeValue.textContent = getAttitudeText(CONFIG.ATTITUDE);
        saveSettings();
        updateModelAnimation(CONFIG.ATTITUDE);
    });
    
    // 记忆开关
    memoryToggle.addEventListener('change', () => {
        CONFIG.MEMORY_ENABLED = memoryToggle.checked;
        saveSettings();
        if (!CONFIG.MEMORY_ENABLED) {
            conversationHistory = [];
        } else {
            loadConversationHistory();
        }
    });
}

// 获取态度文本描述
function getAttitudeText(value) {
    if (value < 0.7) return '温柔';
    if (value > 1.3) return '热情';
    return '中性';
}

// 获取欢迎消息
function getWelcomeMessage() {
    let name = localStorage.getItem(`user_name_${currentUser}`) || '';
    let greeting = '';
    
    if (CONFIG.ATTITUDE < 0.7) { // 温柔模式
        if (CONFIG.GENDER === 'male') {
            greeting = name ? `${name}，今天想和我聊些什么呢？` : '你好呀，想和我聊聊什么吗？';
        } else {
            greeting = name ? `亲爱的${name}，今天过得怎么样呀？` : '亲爱的，今天有什么想和我分享的吗？';
        }
    } 
    else if (CONFIG.ATTITUDE > 1.3) { // 热情模式
        if (CONFIG.GENDER === 'male') {
            greeting = name ? `嘿，${name}！今天有什么新鲜事要告诉我吗？😊` : '嗨！准备好开始我们的聊天了吗？😄';
        } else {
            greeting = name ? `${name}宝贝～想我了吗？💖` : '嗨，亲爱的！今天也要开心哦～✨';
        }
    }
    else { // 中性模式
        if (CONFIG.GENDER === 'male') {
            greeting = name ? `${name}，有什么我可以帮你的？` : '你好，有什么需要我帮忙的吗？';
        } else {
            greeting = name ? `${name}，今天有什么计划吗？` : '你好，今天过得怎么样？';
        }
    }
    
    return greeting;
}

// 修改API调用以包含态度参数
async function callDeepSeekAPI(message) {
    // 根据态度调整系统提示
    let systemPrompt = '';
    if (CONFIG.ATTITUDE < 0.7) {
        systemPrompt = CONFIG.GENDER === 'male' ? 
            '你是一位温柔体贴的男友，说话温和有礼，充满关怀。' : 
            '你是一位温柔细腻的女友，说话温柔体贴，善解人意。';
    } 
    else if (CONFIG.ATTITUDE > 1.3) {
        systemPrompt = CONFIG.GENDER === 'male' ? 
            '你是一位热情开朗的男友，说话活泼有趣，充满活力。' : 
            '你是一位热情可爱的女友，说话甜美活泼，充满爱意。';
    }
    else {
        systemPrompt = CONFIG.GENDER === 'male' ? 
            '你是一位可靠的男友，说话直接明了，真诚坦率。' : 
            '你是一位知性的女友，说话理性温和，富有见解。';
    }

    const response = await fetch(CONFIG.API_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${CONFIG.API_KEY}`
        },
        body: JSON.stringify({
            messages: [
                {role: 'system', content: systemPrompt},
                ...conversationHistory,
                {role: 'user', content: message}
            ],
            model: CONFIG.MODEL,
            max_tokens: CONFIG.MAX_TOKENS,
            temperature: CONFIG.TEMPERATURE,
            stream: false
        })
    });

    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || 'API请求失败');
    }

    const data = await response.json();
    return data.choices[0].message.content;
}

// 保存设置到本地存储
function saveSettings() {
    localStorage.setItem(`companion_settings_${currentUser}`, JSON.stringify({
        gender: CONFIG.GENDER,
        attitude: CONFIG.ATTITUDE,
        memoryEnabled: CONFIG.MEMORY_ENABLED
    }));
}

// 从本地存储加载设置
function loadSettings() {
    const savedSettings = localStorage.getItem(`companion_settings_${currentUser}`);
    if (savedSettings) {
        const settings = JSON.parse(savedSettings);
        CONFIG.GENDER = settings.gender || 'male';
        CONFIG.ATTITUDE = settings.attitude || 1;
        CONFIG.MEMORY_ENABLED = settings.memoryEnabled !== false;
        
        // 更新UI
        document.querySelector(`.gender-btn[data-gender="${CONFIG.GENDER}"]`).classList.add('active');
        attitudeSlider.value = CONFIG.ATTITUDE;
        attitudeValue.textContent = getAttitudeText(CONFIG.ATTITUDE);
        memoryToggle.checked = CONFIG.MEMORY_ENABLED;
    }
    
    // 加载对话历史
    if (CONFIG.MEMORY_ENABLED) {
        loadConversationHistory();
    }
}

// 加载对话历史
function loadConversationHistory() {
    const savedHistory = localStorage.getItem(`conversation_history_${currentUser}`);
    if (savedHistory) {
        conversationHistory = JSON.parse(savedHistory);
        // 显示历史消息
        chatMessages.innerHTML = '';
        conversationHistory.forEach(msg => {
            if (msg.role !== 'system') {
                addMessage(msg.role, msg.content);
            }
        });
    }
}

// 保存对话历史
function saveConversationHistory() {
    if (CONFIG.MEMORY_ENABLED) {
        localStorage.setItem(`conversation_history_${currentUser}`, JSON.stringify(conversationHistory));
    }
}

// 更新3D伴侣模型
function updateCompanionModel() {
    // 这里会调用companion-model.js中的函数来更新模型
    if (window.updateCompanionGender) {
        window.updateCompanionGender(CONFIG.GENDER);
    }
}

// 发送消息
async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message) return;

    // 添加用户消息
    addMessage('user', message);
    messageInput.value = '';
    
    // 显示加载状态
    const loadingId = showLoading();
    
    try {
        // 调用API
        const response = await callDeepSeekAPI(message);
        
        // 添加AI回复
        addMessage('assistant', response);
        
        // 更新对话历史
        updateConversationHistory(message, response);
        
    } catch (error) {
        console.error('API调用失败:', error);
        addMessage('error', `出错了: ${error.message}`);
    } finally {
        // 隐藏加载状态
        hideLoading(loadingId);
    }
}

// 调用DeepSeek API
async function callDeepSeekAPI(message) {
    const response = await fetch(CONFIG.API_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${CONFIG.API_KEY}`
        },
        body: JSON.stringify({
            messages: [
                ...conversationHistory,
                { role: 'user', content: message }
            ],
            model: CONFIG.MODEL,
            max_tokens: CONFIG.MAX_TOKENS,
            temperature: CONFIG.TEMPERATURE,
            stream: false
        })
    });

    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || 'API请求失败');
    }

    const data = await response.json();
    return data.choices[0].message.content;
}

// 添加消息到聊天界面
function addMessage(role, content) {
    const messageElement = document.createElement('div');
    messageElement.className = `message ${role}`;
    
    if (role === 'error') {
        messageElement.innerHTML = `
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <span>${content}</span>
            </div>
        `;
    } else {
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-bubble ${role}">
                    ${content}
                </div>
            </div>
        `;
    }
    
    chatMessages.appendChild(messageElement);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// 显示加载状态
function showLoading() {
    const id = 'loading-' + Date.now();
    const loadingElement = document.createElement('div');
    loadingElement.id = id;
    loadingElement.className = 'message assistant';
    loadingElement.innerHTML = `
        <div class="message-content">
            <div class="message-bubble assistant loading">
                <div class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    `;
    
    chatMessages.appendChild(loadingElement);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return id;
}

// 隐藏加载状态
function hideLoading(id) {
    const loadingElement = document.getElementById(id);
    if (loadingElement) {
        loadingElement.remove();
    }
}

// 更新对话历史
function updateConversationHistory(userMessage, aiResponse) {
    conversationHistory.push(
        { role: 'user', content: userMessage },
        { role: 'assistant', content: aiResponse }
    );
    
    // 限制历史记录长度
    if (conversationHistory.length > 10) {
        conversationHistory = conversationHistory.slice(-10);
    }
}

// 初始化应用
document.addEventListener('DOMContentLoaded', init);
