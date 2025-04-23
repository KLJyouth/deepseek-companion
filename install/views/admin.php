<?php
$prevStep = 'database';
$nextButtonText = '开始安装';

// 默认值
$adminConfig = $_SESSION['install_data']['admin'] ?? [
    'admin_username' => 'admin',
    'admin_password' => '',
    'admin_password_confirm' => ''
];
?>
<form id=\"install-form\" method=\"post\" action=\"?step=admin\">
    <input type=\"hidden\" name=\"csrf_token\" value=\"<?= $csrfToken ?>\">
    
    <h2>管理员账户设置</h2>
    
    <div class=\"form-group\">
        <label>管理员用户名</label>
        <input type=\"text\" name=\"admin_username\" value=\"<?= htmlspecialchars($adminConfig['admin_username']) ?>\" class=\"form-control\" required>
    </div>
    
    <div class=\"form-group\">
        <label>管理员密码</label>
        <input type=\"password\" name=\"admin_password\" value=\"<?= htmlspecialchars($adminConfig['admin_password']) ?>\" class=\"form-control\" required>
        <small class=\"form-text text-muted\">密码至少8个字符，包含大小写字母和数字</small>
    </div>
    
    <div class=\"form-group\">
        <label>确认密码</label>
        <input type=\"password\" name=\"admin_password_confirm\" value=\"<?= htmlspecialchars($adminConfig['admin_password_confirm']) ?>\" class=\"form-control\" required>
    </div>
    
    <?php if ($error): ?>
        <div class=\"alert alert-danger\"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</form>
