<?php require_once __DIR__.'/../layouts/header.php'; ?>

<div class="container">
    <h1>安全仪表盘</h1>
    
    <div class="row">
        <!-- 备份状态 -->
        <div class="col-md-4">
            <div class="card <?= $backupStatus['healthy'] ? 'bg-success' : 'bg-danger' ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">备份状态</h5>
                    <p class="card-text"><?= htmlspecialchars($backupStatus['message']) ?></p>
                    <a href="/security/backups" class="btn btn-light">管理备份</a>
                </div>
            </div>
        </div>
        
        <!-- 访问监控 -->
        <div class="col-md-4">
            <div class="card <?= empty($logAlerts) ? 'bg-success' : 'bg-warning' ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">访问监控</h5>
                    <p class="card-text">
                        <?= empty($logAlerts) ? '无异常访问' : count($logAlerts).'条异常记录' ?>
                    </p>
                    <a href="/security/access-logs" class="btn btn-light">查看日志</a>
                </div>
            </div>
        </div>
        
        <!-- GPG密钥 -->
        <div class="col-md-4">
            <div class="card <?= $gpgStatus['healthy'] ? 'bg-success' : 'bg-danger' ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">加密配置</h5>
                    <p class="card-text"><?= htmlspecialchars($gpgStatus['message']) ?></p>
                    <a href="/security/update-gpg" class="btn btn-light">更新密钥</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../layouts/footer.php'; ?>