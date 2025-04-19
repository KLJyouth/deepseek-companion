<?php
require_once __DIR__ . '/config.php';
require_lib('libs/AuthMiddleware.php');

// 验证用户登录
Libs\AuthMiddleware::checkLogin();

// 设置页面标题
$pageTitle = "AI聊天";

// 包含头部
include ROOT_PATH . '/navbar.php';

// 主聊天界面
?>
<div class="chat-container">
    <div class="chat-header">
        <h2>AI聊天伴侣</h2>
    </div>
    <div class="chat-messages" id="chatMessages">
        <!-- 聊天消息将在这里动态加载 -->
    </div>
    <div class="chat-input">
        <textarea id="messageInput" placeholder="输入您的消息..."></textarea>
        <button id="sendButton">发送</button>
    </div>
</div>

<?php
// 包含页脚
include ROOT_PATH . '/sidebar.php';

// 初始化聊天JS
?>
<script src="<?php echo ROOT_PATH; ?>/js/chat.js"></script>
