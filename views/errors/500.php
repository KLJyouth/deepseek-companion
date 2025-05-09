<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统错误 - Stanfai</title>
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
            <div class="error-code">500</div>
            <h1 class="error-message">系统错误</h1>
            <div class="error-details">
                抱歉，系统出现了一些问题。<br>
                我们的技术团队已收到通知并正在处理。
            </div>
            <a href="/" class="action-button">返回首页</a>
        </div>
    </div>
    
    <script src="/js/error-globe.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initGlobe);
    </script>
</body>
</html>
