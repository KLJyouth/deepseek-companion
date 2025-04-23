<?php
$prevStep = null;
$nextButtonText = '开始安装';
?>

<form id=\"install-form\" method=\"post\" action=\"?step=requirements\">
    <input type=\"hidden\" name=\"csrf_token\" value=\"<?= $csrfToken ?>\">
    
    <h2>欢迎使用Stanfai安装向导</h2>
    <p>本向导将帮助您完成Stanfai系统的安装过程。</p>
    
    <div class=\"alert alert-info\">
        <p>在开始前，请确保您的服务器满足以下要求：</p>
        <ul>
            <li>PHP版本 >= 7.4</li>
            <li>MySQL 5.7+ 或 PostgreSQL 9.5+</li>
            <li>必要的PHP扩展 (PDO, mbstring, JSON等)</li>
            <li>存储目录可写权限</li>
        </ul>
    </div>
    
    <?php if ($error): ?>
        <div class=\"alert alert-danger\"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</form>
