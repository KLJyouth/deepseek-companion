<?php
// 检查用户权限
$role = '';
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
}
$isAdmin = $role === 'admin';
?>
<div class="sidebar col-md-3 col-lg-2 d-md-block bg-dark text-white collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <!-- 用户信息 -->
        <div class="text-center mb-4">
            <img src="<?php 
                $avatar = 'images/default-avatar.jpg';
                if (isset($_SESSION['avatar'])) {
                    $avatar = $_SESSION['avatar'];
                }
                echo htmlspecialchars($avatar); 
            ?>"
                 class="rounded-circle mb-2" width="80" height="80">
            <h5 class="mb-1"><?php 
                $username = '用户';
                if (isset($_SESSION['username'])) {
                    $username = $_SESSION['username'];
                }
                echo htmlspecialchars($username); 
            ?></h5>
            <small class="text-muted"><?php 
                $role = '普通用户';
                if (isset($_SESSION['role'])) {
                    $role = $_SESSION['role'];
                }
                echo htmlspecialchars($role); 
            ?></small>
        </div>
        
        <!-- 主菜单 -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'admin.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="admin.php">
                    <i class="bi bi-speedometer2 me-2"></i>仪表盘
                </a>
            </li>
            
            <!-- 系统管理 -->
            <?php if ($isAdmin): ?>
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'system.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="system.php">
                    <i class="bi bi-server me-2"></i>系统管理
                </a>
            </li>
            <?php endif; ?>
            
            <!-- 模型设置 -->
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'settings.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="settings.php">
                    <i class="bi bi-gear me-2"></i>模型设置
                </a>
            </li>
            
            <!-- 数据分析 -->
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'analytics.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="analytics.php">
                    <i class="bi bi-graph-up me-2"></i>数据分析
                </a>
            </li>
            
            <!-- 用户管理 -->
            <?php if ($isAdmin): ?>
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'users.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="users.php">
                    <i class="bi bi-people me-2"></i>用户管理
                </a>
            </li>
            <?php endif; ?>
            
            <!-- 对话记录 -->
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'conversations.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="conversations.php">
                    <i class="bi bi-chat-left-text me-2"></i>对话记录
                </a>
            </li>
            
            <!-- API管理 -->
            <li class="nav-item">
                <a class="nav-link <?php 
                    if (basename($_SERVER['PHP_SELF']) === 'api.php') {
                        echo 'active';
                    } else {
                        echo '';
                    }
                ?>"
                   href="api.php">
                    <i class="bi bi-plug me-2"></i>API管理
                </a>
            </li>
        </ul>
        
        <!-- 底部菜单 -->
        <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-dark">
            <div class="d-flex justify-content-between">
                <a href="profile.php" class="text-muted small">
                    <i class="bi bi-person"></i> 个人资料
                </a>
                <a href="logout.php" class="text-muted small">
                    <i class="bi bi-box-arrow-right"></i> 登出
                </a>
            </div>
        </div>
    </div>
</div>
