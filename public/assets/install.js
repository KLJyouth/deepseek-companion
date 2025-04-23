/**
 * Stanfai安装向导JavaScript功能
 */

document.addEventListener('DOMContentLoaded', function() {
    // 表单验证
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // 实时输入验证
        const inputs = form.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                validateInput(this);
            });
        });

        // 提交前验证
        form.addEventListener('submit', function(e) {
            let isValid = true;
            inputs.forEach(input => {
                if (!validateInput(input)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                showAlert('请填写所有必填字段', 'danger');
            }
        });
    });

    // 密码强度检查
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            checkPasswordStrength(this);
        });
    });

    // 安装进度页面特殊处理
    if (document.getElementById('install-progress')) {
        simulateInstallProgress();
    }
});

/**
 * 验证单个输入字段
 */
function validateInput(input) {
    if (!input.value.trim()) {
        input.classList.add('is-invalid');
        return false;
    } else {
        input.classList.remove('is-invalid');
        return true;
    }
}

/**
 * 检查密码强度
 */
function checkPasswordStrength(input) {
    const password = input.value;
    let strength = 0;

    // 长度检查
    if (password.length >= 8) strength++;
    // 包含大写字母
    if (/[A-Z]/.test(password)) strength++;
    // 包含小写字母
    if (/[a-z]/.test(password)) strength++;
    // 包含数字
    if (/[0-9]/.test(password)) strength++;
    // 包含特殊字符
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    // 更新UI
    const strengthMeter = document.getElementById('password-strength-meter') || 
        createPasswordStrengthMeter(input);
    
    strengthMeter.className = 'password-strength strength-' + strength;
}

/**
 * 创建密码强度指示器
 */
function createPasswordStrengthMeter(input) {
    const container = document.createElement('div');
    container.className = 'password-strength-container';
    container.innerHTML = 
        <div class="password-strength-meter">
            <div class="password-strength strength-0"></div>
        </div>
        <small class="form-text text-muted">密码强度: <span class="strength-text">弱</span></small>
    ;
    
    input.parentNode.insertBefore(container, input.nextSibling);
    return container.querySelector('.password-strength');
}

/**
 * 模拟安装进度
 */
function simulateInstallProgress() {
    const progressBar = document.getElementById('install-progress');
    const progressText = document.getElementById('progress-text');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 10;
        if (progress > 100) progress = 100;
        
        progressBar.style.width = progress + '%';
        progressText.textContent = getProgressMessage(progress);
        
        if (progress === 100) {
            clearInterval(interval);
            setTimeout(() => {
                window.location.href = '?step=complete';
            }, 1000);
        }
    }, 500);
}

/**
 * 获取进度消息
 */
function getProgressMessage(progress) {
    if (progress < 20) return '正在检查系统配置...';
    if (progress < 40) return '正在创建数据库表...';
    if (progress < 60) return '正在写入配置文件...';
    if (progress < 80) return '正在创建管理员账户...';
    if (progress < 100) return '正在完成安装...';
    return '安装完成!';
}

/**
 * 显示提示信息
 */
function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = lert alert-;
    alert.textContent = message;
    
    const container = document.querySelector('.install-container main');
    container.insertBefore(alert, container.firstChild);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
