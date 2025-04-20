<?php require_once __DIR__.'/../layouts/header.php'; ?>

<div class="container">
    <h1>访问日志</h1>
    
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">搜索</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="type" class="form-label">日志类型</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">全部</option>
                        <option value="normal" <?= ($_GET['type'] ?? '') === 'normal' ? 'selected' : '' ?>>正常访问</option>
                        <option value="alert" <?= ($_GET['type'] ?? '') === 'alert' ? 'selected' : '' ?>>异常访问</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">筛选</button>
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
                            <th>IP地址</th>
                            <th>事件</th>
                            <th>详情</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): 
                            $isAlert = strpos($log, '异常访问') !== false;
                        ?>
                        <tr class="<?= $isAlert ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars(substr($log, 0, 19)) ?></td>
                            <td>
                                <?php 
                                preg_match('/\d+\.\d+\.\d+\.\d+/', $log, $matches);
                                echo $matches[0] ?? '未知IP';
                                ?>
                            </td>
                            <td><?= $isAlert ? '⚠️ 异常访问' : '正常访问' ?></td>
                            <td><?= htmlspecialchars(substr($log, 20)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&type=<?= $_GET['type'] ?? '' ?>">上一页</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&type=<?= $_GET['type'] ?? '' ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&type=<?= $_GET['type'] ?? '' ?>">下一页</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../layouts/footer.php'; ?>