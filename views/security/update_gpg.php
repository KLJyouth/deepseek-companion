<?php require_once __DIR__.'/../layouts/header.php'; ?>

<div class="container">
    <h1>GPG密钥管理</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">当前配置</h5>
            <dl class="row">
                <dt class="col-sm-3">当前GPG收件人</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($currentKey) ?></dd>
                
                <dt class="col-sm-3">密钥状态</dt>
                <dd class="col-sm-9">
                    <?php if ($currentKey !== '未配置'): ?>
                        <span class="badge bg-success">已配置</span>
                    <?php else: ?>
                        <span class="badge bg-danger">未配置</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">更新GPG密钥</h5>
            
            <form method="post">
                <div class="mb-3">
                    <label for="email" class="form-label">GPG密钥邮箱</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($currentKey !== '未配置' ? $currentKey : '') ?>" required>
                    <div class="form-text">
                        请输入与系统中GPG密钥关联的邮箱地址
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">更新密钥</button>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title">使用说明</h5>
            <ol>
                <li>在服务器上生成GPG密钥对：<code>gpg --gen-key</code></li>
                <li>列出可用密钥：<code>gpg --list-keys</code></li>
                <li>在上表输入密钥关联的邮箱地址</li>
                <li>系统将使用该密钥加密所有备份文件</li>
            </ol>
            <div class="alert alert-warning">
                <strong>注意：</strong> 更新密钥后，新的备份将使用新密钥加密，但旧备份仍需原密钥解密。
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../layouts/footer.php'; ?>