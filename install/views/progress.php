<?php
$prevStep = 'admin';
$nextButtonText = '正在安装...';

// 禁用缓存以确保进度实时更新
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
?>
<form id=\"install-form\" method=\"post\" action=\"?step=install\">
    <input type=\"hidden\" name=\"csrf_token\" value=\"<?= $csrfToken ?>\">
    
    <h2>安装进度</h2>
    
    <div class=\"progress-container\">
        <div class=\"progress-bar\">
            <div class=\"progress\" id=\"install-progress\" style=\"width: 0%\"></div>
        </div>
        <div class=\"progress-text\" id=\"progress-text\">准备开始安装...</div>
    </div>
    
    <div id=\"install-log\"></div>
    
    <?php if ($error): ?>
        <div class=\"alert alert-danger\"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 自动提交表单开始安装
    document.getElementById('install-form').submit();
    
    // 模拟进度更新 (实际应由服务器推送)
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 5;
        if (progress > 100) progress = 100;
        document.getElementById('install-progress').style.width = progress + '%';
        document.getElementById('progress-text').textContent = getProgressMessage(progress);
        
        if (progress === 100) {
            clearInterval(progressInterval);
            setTimeout(() => {
                window.location.href = '?step=complete';
            }, 1000);
        }
    }, 500);
    
    function getProgressMessage(p) {
        if (p < 20) return '正在检查系统配置...';
        if (p < 40) return '正在创建数据库表...';
        if (p < 60) return '正在写入配置文件...';
        if (p < 80) return '正在创建管理员账户...';
        if (p < 100) return '正在完成安装...';
        return '安装完成!';
    }
});
</script>
