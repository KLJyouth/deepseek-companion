<?php
$prevStep = 'requirements';
$nextButtonText = '继续';

// 默认值
$dbConfig = $_SESSION['install_data']['database'] ?? [
    'db_type' => 'mysql',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'stanfai',
    'db_user' => 'root',
    'db_password' => ''
];
?>
<form id=\"install-form\" method=\"post\" action=\"?step=database\">
    <input type=\"hidden\" name=\"csrf_token\" value=\"<?= $csrfToken ?>\">
    
    <h2>数据库配置</h2>
    
    <div class=\"form-group\">
        <label>数据库类型</label>
        <select name=\"db_type\" class=\"form-control\" required>
            <option value=\"mysql\" <?= $dbConfig['db_type'] === 'mysql' ? 'selected' : '' ?>>MySQL</option>
            <option value=\"pgsql\" <?= $dbConfig['db_type'] === 'pgsql' ? 'selected' : '' ?>>PostgreSQL</option>
            <option value=\"sqlite\" <?= $dbConfig['db_type'] === 'sqlite' ? 'selected' : '' ?>>SQLite</option>
        </select>
    </div>
    
    <div class=\"form-group\">
        <label>数据库主机</label>
        <input type=\"text\" name=\"db_host\" value=\"<?= htmlspecialchars($dbConfig['db_host']) ?>\" class=\"form-control\" required>
    </div>
    
    <div class=\"form-group\">
        <label>数据库端口</label>
        <input type=\"text\" name=\"db_port\" value=\"<?= htmlspecialchars($dbConfig['db_port']) ?>\" class=\"form-control\" required>
    </div>
    
    <div class=\"form-group\">
        <label>数据库名称</label>
        <input type=\"text\" name=\"db_name\" value=\"<?= htmlspecialchars($dbConfig['db_name']) ?>\" class=\"form-control\" required>
    </div>
    
    <div class=\"form-group\">
        <label>数据库用户名</label>
        <input type=\"text\" name=\"db_user\" value=\"<?= htmlspecialchars($dbConfig['db_user']) ?>\" class=\"form-control\" required>
    </div>
    
    <div class=\"form-group\">
        <label>数据库密码</label>
        <input type=\"password\" name=\"db_password\" value=\"<?= htmlspecialchars($dbConfig['db_password']) ?>\" class=\"form-control\">
    </div>
    
    <?php if ($error): ?>
        <div class=\"alert alert-danger\"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</form>
