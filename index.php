<?php
require_once 'config.php';
require_once 'middlewares/AuthMiddleware.php';

// 检查登录状态
$isLoggedIn = \Libs\AuthMiddleware::checkAuth();
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI伴侣 - 智能陪伴系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #1e88e5, #0d47a1);
            color: white;
            padding: 5rem 0;
            margin-bottom: 3rem;
        }
        .feature-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .nav-pills .nav-link.active {
            background-color: #1e88e5;
        }
        .quick-access {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-robot me-2"></i>AI伴侣
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chat.php">智能聊天</a>
                    </li>
                    <?php if ($isLoggedIn && $role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">系统管理</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($username); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>个人资料</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>设置</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>退出</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">登录</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主展示区 -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">您的智能AI伴侣</h1>
            <p class="lead mb-5">全天候陪伴，个性化交流，让科技温暖您的生活</p>
            <?php if (!$isLoggedIn): ?>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="login.php" class="btn btn-primary btn-lg px-4 gap-3">立即体验</a>
                    <a href="#features" class="btn btn-outline-light btn-lg px-4">了解更多</a>
                </div>
            <?php else: ?>
                <a href="chat.php" class="btn btn-light btn-lg px-4">开始聊天</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- 功能区域 -->
    <div class="container">
        <?php if ($isLoggedIn): ?>
            <!-- 已登录用户快捷入口 -->
            <div class="quick-access mb-5">
                <h3 class="mb-4"><i class="bi bi-lightning me-2"></i>快捷入口</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <a href="chat.php" class="card feature-card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-chat-square-text text-primary" style="font-size: 2rem;"></i>
                                <h5 class="card-title mt-3">智能聊天</h5>
                                <p class="card-text">与您的AI伴侣交流</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="#" class="card feature-card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-collection text-primary" style="font-size: 2rem;"></i>
                                <h5 class="card-title mt-3">对话记录</h5>
                                <p class="card-text">查看历史对话</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="settings.php" class="card feature-card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-sliders text-primary" style="font-size: 2rem;"></i>
                                <h5 class="card-title mt-3">个性设置</h5>
                                <p class="card-text">定制您的AI伴侣</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 功能特点 -->
        <section id="features" class="mb-5">
            <h2 class="text-center mb-5">核心功能</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <i class="bi bi-chat-left-text text-primary" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-3">智能对话</h5>
                            <p class="card-text">基于先进AI模型的自然语言交互，提供流畅的对话体验。</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <i class="bi bi-person-bounding-box text-primary" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-3">个性定制</h5>
                            <p class="card-text">自定义AI伴侣的性别、性格和交互方式，打造专属体验。</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <i class="bi bi-clock-history text-primary" style="font-size: 2rem;"></i>
                            <h5 class="card-title mt-3">记忆功能</h5>
                            <p class="card-text">AI会记住您的偏好和历史对话，提供连贯的交互体验。</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- 页脚 -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-robot me-2"></i>AI伴侣</h5>
                    <p class="text-muted">用科技温暖生活，让AI成为您的贴心伙伴。</p>
                </div>
                <div class="col-md-3">
                    <h5>快速链接</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-decoration-none text-muted">首页</a></li>
                        <li><a href="chat.php" class="text-decoration-none text-muted">智能聊天</a></li>
                        <li><a href="#" class="text-decoration-none text-muted">关于我们</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>联系我们</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-envelope me-2 text-muted"></i><span class="text-muted">contact@aicompanion.com</span></li>
                        <li><i class="bi bi-telephone me-2 text-muted"></i><span class="text-muted">400-123-4567</span></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center text-muted">
                <small>© <?php echo date('Y'); ?> AI伴侣 版权所有</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    
    <?php if ($isLoggedIn): ?>
    <!-- 聊天功能容器 -->
    <div class="app-container mt-5">
        <div class="companion-model" id="companionModel"></div>
        
        <div class="chat-container">
            <div class="chat-header">
                <div class="header-top">
                    <h2>与AI伴侣聊天</h2>
                </div>
                <div class="controls">
                    <div class="control-group">
                        <label>伴侣性别:</label>
                        <div class="gender-selector">
                            <button class="gender-btn active" data-gender="male">男</button>
                            <button class="gender-btn" data-gender="female">女</button>
                        </div>
                    </div>
                    <div class="control-group">
                        <label>态度:</label>
                        <input type="range" id="attitudeSlider" min="0" max="2" step="0.1" value="1">
                        <span id="attitudeValue">中性</span>
                    </div>
                    <div class="control-group">
                        <label>记忆功能:</label>
                        <label class="switch">
                            <input type="checkbox" id="memoryToggle" checked>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages"></div>
            
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="和你的AI伴侣聊天...">
                <button id="sendButton">
                    <svg viewBox="0 0 24 24" width="24" height="24">
                        <path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- 加载必要的JS -->
    <script src="js/companion-model.js"></script>
    <script src="js/chat.js"></script>
    <?php endif; ?>

    <script src="js/main.js"></script>
</body>
</html>
