// 安装向导主脚本
document.addEventListener('DOMContentLoaded', function() {
    // DOM元素引用
    const steps = document.querySelectorAll('.step');
    const stepContents = document.querySelectorAll('.step-content');
    const btnPrev = document.querySelectorAll('.btn-prev');
    const btnNext = document.querySelectorAll('.btn-next');
    const btnFinish = document.querySelector('.btn-finish');
    const agreeCheckbox = document.getElementById('agree');
    const envResults = document.getElementById('envResults');
    const progressBar = document.querySelector('.progress');
    const progressText = document.querySelector('.progress-text');
    const installLog = document.getElementById('installLog');
    const configForm = document.getElementById('configForm');

    // 当前步骤
    let currentStep = 1;
    const totalSteps = steps.length;

    // 初始化步骤
    updateStepNavigation();

    // 步骤导航事件监听
    btnNext.forEach(btn => {
        btn.addEventListener('click', goToNextStep);
    });

    btnPrev.forEach(btn => {
        btn.addEventListener('click', goToPrevStep);
    });

    // 许可协议勾选事件
    if (agreeCheckbox) {
        agreeCheckbox.addEventListener('change', function() {
            const nextBtn = document.querySelector('#step1 .btn-next');
            nextBtn.disabled = !this.checked;
        });
    }

    // 下一步函数
    function goToNextStep() {
        if (currentStep >= totalSteps) return;

        // 步骤特定逻辑
        switch (currentStep) {
            case 1:
                // 许可协议步骤无特殊逻辑
                break;
            case 2:
                // 环境检测步骤
                runEnvironmentCheck();
                return; // 异步操作，不立即进入下一步
            case 3:
                // 系统配置步骤
                if (!validateConfigForm()) {
                    return;
                }
                break;
            case 4:
                // 安装步骤
                startInstallation();
                return; // 异步操作，不立即进入下一步
        }

        currentStep++;
        updateStepNavigation();
    }

    // 上一步函数
    function goToPrevStep() {
        if (currentStep <= 1) return;
        currentStep--;
        updateStepNavigation();
    }

    // 更新步骤导航状态
    function updateStepNavigation() {
        // 更新步骤指示器
        steps.forEach((step, index) => {
            if (index + 1 === currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });

        // 更新内容区域
        stepContents.forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`step${currentStep}`).classList.add('active');

        // 更新按钮状态
        const nextBtns = document.querySelectorAll('.btn-next');
        nextBtns.forEach(btn => {
            if (currentStep === 1) {
                btn.disabled = !agreeCheckbox.checked;
            } else if (currentStep === 3) {
                btn.disabled = false;
            } else if (currentStep === totalSteps) {
                btn.style.display = 'none';
            } else {
                btn.disabled = false;
            }
        });

        // 自动执行环境检查
        if (currentStep === 2) {
            runEnvironmentCheck();
        }
    }

    // 运行环境检测
    function runEnvironmentCheck() {
        const nextBtn = document.querySelector('#step2 .btn-next');
        nextBtn.disabled = true;
        envResults.innerHTML = '<p>正在检测系统环境...</p>';

        // 模拟异步检测（实际应使用AJAX调用env_check.sh）
        setTimeout(() => {
            fetch('api/env_check.php')
                .then(response => response.json())
                .then(data => {
                    displayEnvResults(data);
                    nextBtn.disabled = !data.allChecksPassed;
                })
                .catch(error => {
                    envResults.innerHTML = `<p class="error">环境检测失败: ${error.message}</p>`;
                });
        }, 1000);
    }

    // 显示环境检测结果
    function displayEnvResults(data) {
        let html = '<div class="env-result-list">';
        
        // 这里应该根据实际env_check.sh的输出格式来解析显示
        // 示例数据格式
        const sampleData = {
            os: { passed: true, message: "类Unix系统 (Linux)" },
            php: { passed: true, message: "PHP版本 7.4" },
            mysql: { passed: true, message: "MySQL版本 5.7" },
            webserver: { passed: true, message: "检测到: Nginx" },
            btPanel: { passed: false, message: "未检测到宝塔面板" },
            allChecksPassed: true
        };

        for (const [key, value] of Object.entries(sampleData)) {
            const icon = value.passed ? '✓' : '✗';
            const color = value.passed ? 'green' : 'red';
            html += `<p style="color: ${color}">${icon} ${value.message}</p>`;
        }

        html += '</div>';
        envResults.innerHTML = html;
    }

    // 验证系统配置表单
    function validateConfigForm() {
        let isValid = true;
        const inputs = configForm.querySelectorAll('input[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = 'red';
                isValid = false;
            } else {
                input.style.borderColor = '';
            }
        });

        return isValid;
    }

    // 开始安装
    function startInstallation() {
        const nextBtn = document.querySelector('#step4 .btn-next');
        nextBtn.disabled = true;
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        installLog.innerHTML = '';

        // 模拟安装过程
        const steps = [
            { progress: 10, message: "正在创建数据库..." },
            { progress: 30, message: "正在导入初始数据..." },
            { progress: 50, message: "正在写入配置文件..." },
            { progress: 70, message: "正在设置管理员账户..." },
            { progress: 90, message: "正在进行最终检查..." },
            { progress: 100, message: "安装完成！" }
        ];

        steps.forEach((step, index) => {
            setTimeout(() => {
                progressBar.style.width = `${step.progress}%`;
                progressText.textContent = `${step.progress}%`;
                addInstallLog(step.message);

                if (step.progress === 100) {
                    currentStep++;
                    updateStepNavigation();
                }
            }, index * 1000);
        });
    }

    // 添加安装日志
    function addInstallLog(message) {
        const logEntry = document.createElement('div');
        logEntry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        installLog.appendChild(logEntry);
        installLog.scrollTop = installLog.scrollHeight;
    }
});