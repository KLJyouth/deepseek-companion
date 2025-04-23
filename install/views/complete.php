<?php
// 这是最后一步，没有上一步按钮
$prevStep = null;
$nextButtonText = '访问系统';
?>

<div class=\"install-complete\">
    <h2>安装完成!</h2>
    
    <div class=\"alert alert-success\">
        <p>Stanfai 已成功安装到您的系统。</p>
    </div>
    
    <div class=\"card\">
        <div class=\"card-body\">
            <h3>后续步骤</h3>
            <ol>
                <li>点击下方按钮访问系统控制台</li>
                <li>建议立即修改管理员密码</li>
                <li>配置系统设置以适应您的需求</li>
            </ol>
            
            <div class=\"text-center mt-4\">
                <a href=\"/\" class=\"btn btn-primary btn-lg\">访问系统</a>
            </div>
        </div>
    </div>
    
    <div class=\"alert alert-warning mt-4\">
        <h4>安全提示</h4>
        <p>为了系统安全，建议您:</p>
        <ul>
            <li>立即删除或重命名 install 目录</li>
            <li>限制对 install.php 的访问</li>
            <li>定期备份数据库和文件</li>
        </ul>
    </div>
</div>
