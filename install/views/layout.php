<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stanfai 安装向导</title>
    <link rel="stylesheet" href="/assets/install.css">
</head>
<body>
    <div class="install-container">
        <header>
            <h1>Stanfai 安装向导</h1>
            <div class="progress-bar">
                <div class="progress" style="width: <?= $progress ?>%"></div>
            </div>
        </header>
        
        <main>
            <?php include $contentView; ?>
        </main>
        
        <footer>
            <?php if ($prevStep): ?>
                <a href="?step=<?= $prevStep ?>" class="btn">上一步</a>
            <?php endif; ?>
            
            <?php if (!isset($hideNextButton) || !$hideNextButton): ?>
                <button type="submit" form="install-form" class="btn btn-primary">
                    <?= $nextButtonText ?? '下一步' ?>
                </button>
            <?php endif; ?>
        </footer>
    </div>
    
    <script src="/assets/install.js"></script>
</body>
</html>
