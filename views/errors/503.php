<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统维护中 - Stanfai</title>
    <link rel="stylesheet" href="/css/error-pages.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="globe-container"></div>
    
    <video class="brand-video" autoplay loop muted playsinline>
        <source src="/assets/sb-logo.mp4" type="video/mp4">
    </video>
    
    <div class="error-container">
        <div class="content-wrapper">
            <div class="error-code">503</div>
            <h1 class="error-message">系统维护中</h1>
            <div class="error-details">
                很抱歉，系统正在进行维护升级，请稍后再访问。<br>
                预计维护时间: <?php echo isset($maintenance) ? date('H:i', time() + $maintenance['retry_after']) : '30分钟'; ?>
            </div>
            <div class="contact-info">
                如需帮助，请联系管理员<br>
                邮箱: admin@stanfai.com<br>
                电话: 400-XXX-XXXX
            </div>
            <a href="javascript:void(0)" class="action-button" onclick="window.location.reload()">刷新页面</a>
        </div>
    </div>
    
    <script src="/js/error-globe.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initGlobe);
        // 30秒自动刷新
        setTimeout(() => window.location.reload(), 30000);
    </script>
</body>
</html>
