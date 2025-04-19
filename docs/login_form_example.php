<?php
require_once 'config.php';
require_once 'libs/CryptoHelper.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>登录示例</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-bypass-field {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>系统登录</h4>
                    </div>
                    <div class="card-body">
                        <form action="auth.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= CryptoHelper::generateCsrfToken() ?>">
                            
                            <?php if (defined('ADMIN_BYPASS_HASH')): ?>
                            <div class="mb-3 admin-bypass-field" style="display: none;">
                                <label for="admin_bypass" class="form-label">管理员跳过密码</label>
                                <input type="password" class="form-control" id="admin_bypass" name="admin_bypass">
                                <small class="text-muted">仅限紧急情况下使用</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <?php if (defined('ADMIN_BYPASS_HASH')): ?>
                            <div class="mb-3 text-end">
                                <a href="#" class="text-muted small" id="toggleBypass">管理员入口</a>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary w-100">登录</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (defined('ADMIN_BYPASS_HASH')): ?>
    <script>
        document.getElementById('toggleBypass').addEventListener('click', function(e) {
            e.preventDefault();
            const field = document.querySelector('.admin-bypass-field');
            field.style.display = field.style.display === 'none' ? 'block' : 'none';
        });
    </script>
    <?php endif; ?>
</body>
</html>
