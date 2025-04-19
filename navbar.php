<?php
// 检查用户是否登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <!-- 移动端菜单按钮 -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- 品牌Logo -->
        <a class="navbar-brand me-auto" href="admin.php">
            <i class="bi bi-robot"></i> AI伴侣管理
        </a>
        
        <!-- 导航栏内容 -->
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <!-- 搜索框 -->
            <form class="d-flex ms-3 me-auto">
                <input class="form-control me-2" type="search" placeholder="搜索..." aria-label="Search">
                <button class="btn btn-outline-light" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            
            <!-- 右侧导航项 -->
            <ul class="navbar-nav">
                <!-- 通知 -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <span class="badge bg-danger rounded-pill">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">新通知</h6></li>
                        <li><a class="dropdown-item" href="#">系统更新可用</a></li>
                        <li><a class="dropdown-item" href="#">新用户注册</a></li>
                        <li><a class="dropdown-item" href="#">API调用异常</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">查看所有通知</a></li>
                    </ul>
                </li>
                
                <!-- 用户菜单 -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'images/default-avatar.jpg'); ?>" 
                             class="rounded-circle me-1" width="30" height="30">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? '管理员'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text">
                                <small>登录身份: <strong><?php echo htmlspecialchars($_SESSION['role'] ?? '管理员'); ?></strong></small>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>个人资料</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>设置</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>登出
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- 内容间距调整 -->
<div style="height: 60px;"></div>
