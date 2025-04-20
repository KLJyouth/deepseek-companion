<?php require_once __DIR__.'/../layouts/header.php'; ?>

<div class="container">
    <h1>备份管理</h1>
    
    <div class="mb-3">
        <button class="btn btn-primary" onclick="runBackup()">
            <i class="fas fa-plus-circle"></i> 立即备份
        </button>
    </div>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>文件名</th>
                        <th>大小</th>
                        <th>修改时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td><?= htmlspecialchars($backup['name']) ?></td>
                        <td><?= formatSize($backup['size']) ?></td>
                        <td><?= htmlspecialchars($backup['modified']) ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="/security/download-backup?file=<?= urlencode($backup['name']) ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i> 下载
                                </a>
                                <button onclick="deleteBackup('<?= htmlspecialchars($backup['name']) ?>')" 
                                        class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> 删除
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function runBackup() {
    if (confirm('确定要立即执行备份吗？')) {
        fetch('/security/run-backup', {method: 'POST'})
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('备份成功');
                    location.reload();
                } else {
                    alert('备份失败: ' + data.message);
                }
            });
    }
}

function deleteBackup(filename) {
    if (confirm('确定要删除此备份吗？')) {
        fetch('/security/delete-backup?file=' + encodeURIComponent(filename), {method: 'DELETE'})
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('删除失败: ' + data.message);
                }
            });
    }
}
</script>

<?php require_once __DIR__.'/../layouts/footer.php'; ?>