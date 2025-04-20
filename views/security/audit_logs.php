<?php require_once __DIR__.'/../layouts/header.php'; ?>

<div class="container">
    <h1>审计日志</h1>
    
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <label for="search" class="form-label">搜索</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                           placeholder="搜索用户、IP或操作">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">搜索</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>用户</th>
                            <th>IP</th>
                            <th>操作</th>
                            <th>详情</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr class="<?= $log['action'] === 'failed_login' ? 'table-danger' : '' ?>">
                            <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
                            <td><?= htmlspecialchars($log['user'] ?? '') ?></td>
                            <td><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                            <td>
                                <?php 
                                $actionMap = [
                                    'failed_login' => '⚠️ 登录失败',
                                    'update_gpg' => '🔑 更新密钥',
                                    'delete_backup' => '🗑️ 删除备份'
                                ];
                                echo $actionMap[$log['action']] ?? $log['action'];
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="showDetails(this)" 
                                        data-details="<?= htmlspecialchars(json_encode($log['details'] ?? [])) ?>">
                                    查看详情
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>">上一页</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>">下一页</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- 详情模态框 -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">操作详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(btn) {
    const details = JSON.parse(btn.dataset.details);
    document.getElementById('detailsContent').textContent = 
        JSON.stringify(details, null, 2);
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once __DIR__.'/../layouts/footer.php'; ?>