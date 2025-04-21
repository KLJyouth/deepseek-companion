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
    <title>DeepSeek 智慧法务系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/pako@2.1.0/dist/pako.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.132.2/examples/js/controls/OrbitControls.js"></script>
</head>
<body>
    <header class="main-header">
        <nav class="nav-container">
            <div class="logo">DeepSeek 智慧法务</div>
            <div class="nav-links">
                <a href="#services">法律服务</a>
                <a href="#compliance">智能合规</a>
                <a href="#risk">风险评估</a>
                <a href="#law-firms">律所入驻</a>
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i><?php echo htmlspecialchars($username); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i>个人资料</a></li>
                            <li><a class="dropdown-item" href="chat.php"><i class="bi bi-chat"></i>智能助手</a></li>
                            <?php if ($role === 'admin'): ?>
                            <li><a class="dropdown-item" href="admin.php"><i class="bi bi-gear"></i>系统管理</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i>退出</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn">登录</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero-section">
            <div class="hero-content">
                <h1>AI驱动的智慧法务解决方案</h1>
                <p>融合人工智能与法律专业，为企业提供全方位的法务管理服务</p>
                <div class="cta-buttons">
                    <?php if ($isLoggedIn): ?>
                        <a href="chat.php" class="primary-btn">智能助手</a>
                        <a href="#services" class="secondary-btn">浏览服务</a>
                    <?php else: ?>
                        <a href="login.php" class="primary-btn">开始使用</a>
                        <a href="#demo" class="secondary-btn">查看演示</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-visual">
                <div id="globe" style="width: 100%; height: 500px; position: relative;"></div>
            </div>
        </section>

    <!-- 数据卡片 -->
    <section class="py-5 bg-dark bg-opacity-75 position-relative z-index-1">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card bg-primary bg-opacity-10 border-primary">
                        <div class="card-body text-center">
                            <h3 class="card-title"><i class="bi bi-globe"></i> 全球节点</h3>
                            <div class="display-4 fw-bold" id="node-count">12</div>
                            <p class="mb-0">覆盖5大洲</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success bg-opacity-10 border-success">
                        <div class="card-body text-center">
                            <h3 class="card-title"><i class="bi bi-people"></i> 用户数量</h3>
                            <div class="display-4 fw-bold" id="user-count">0</div>
                            <p class="mb-0">全球用户</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info bg-opacity-10 border-info">
                        <div class="card-body text-center">
                            <h3 class="card-title"><i class="bi bi-lightning"></i> 实时请求</h3>
                            <div class="display-4 fw-bold" id="request-count">0</div>
                            <p class="mb-0">次/分钟</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning bg-opacity-10 border-warning">
                        <div class="card-body text-center">
                            <h3 class="card-title"><i class="bi bi-shield-check"></i> 安全运行</h3>
                            <div class="display-4 fw-bold" id="uptime">99.99%</div>
                            <p class="mb-0">服务可用性</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <h3>24/7</h3>
                <p>智能服务</p>
            </div>
        </section>

        <section id="services" class="feature-section">
            <h2>核心服务</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-file-text"></i></div>
                    <h3>合同智能管理</h3>
                    <p>AI辅助合同审核、风险分析、自动化管理</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-search"></i></div>
                    <h3>法律研究助手</h3>
                    <p>智能检索法规、案例分析、法律咨询</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                    <h3>合规风控</h3>
                    <p>实时监控、风险预警、合规审查</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-graph-up"></i></div>
                    <h3>数据分析</h3>
                    <p>法务数据可视化、智能决策支持</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-robot"></i></div>
                    <h3>智能法律助手</h3>
                    <p>24/7在线咨询、智能对话、个性化服务</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-people"></i></div>
                    <h3>律所协作</h3>
                    <p>律所资源对接、案件分配、协同办公</p>
                </div>
            </div>
        </section>

        <section id="law-firms" class="law-firm-section">
            <h2>律所入驻</h2>
            <div class="law-firm-content">
                <div class="benefits">
                    <h3>入驻优势</h3>
                    <ul>
                        <li><i class="bi bi-check-circle"></i>专属管理后台</li>
                        <li><i class="bi bi-check-circle"></i>智能案件管理</li>
                        <li><i class="bi bi-check-circle"></i>客户资源对接</li>
                        <li><i class="bi bi-check-circle"></i>AI辅助办案</li>
                    </ul>
                </div>
                <div class="join-form">
                    <h3>快速入驻</h3>
                    <form id="joinForm" action="process_join.php" method="POST">
                        <input type="text" name="firm_name" placeholder="律所名称" required>
                        <input type="email" name="email" placeholder="联系邮箱" required>
                        <input type="tel" name="phone" placeholder="联系电话" required>
                        <button type="submit" class="primary-btn">申请入驻</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>关于我们</h4>
                <p>DeepSeek智慧法务系统致力于为企业提供智能化法务解决方案</p>
            </div>
            <div class="footer-section">
                <h4>联系方式</h4>
                <p><i class="bi bi-envelope"></i>邮箱：contact@deepseek.com</p>
                <p><i class="bi bi-telephone"></i>电话：400-888-8888</p>
            </div>
            <div class="footer-section">
                <h4>快速链接</h4>
                <a href="#"><i class="bi bi-file-text"></i>使用文档</a>
                <a href="#"><i class="bi bi-code-square"></i>API接口</a>
                <a href="#"><i class="bi bi-headset"></i>技术支持</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> DeepSeek 智慧法务系统. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/globe-enhanced.js"></script>
    <script>
    // 更新数据卡片的动态效果
    function updateStats() {
        // 模拟实时数据更新
        const userCount = document.getElementById('user-count');
        const requestCount = document.getElementById('request-count');
        const nodeCount = document.getElementById('node-count');
        
        // 添加数字增长动画
        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }
    
        // 定期更新数据
        setInterval(() => {
            // 模拟用户数增长
            const currentUsers = parseInt(userCount.innerHTML) || 0;
            animateValue(userCount, currentUsers, currentUsers + Math.floor(Math.random() * 10), 1000);
            
            // 模拟请求数波动
            requestCount.innerHTML = Math.floor(Math.random() * 100 + 200);
        }, 3000);
    }
    
    // 添加区块链数据流转效果
    function initBlockchainFlow() {
        const canvas = document.createElement('canvas');
        canvas.style.position = 'absolute';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '0';
        document.querySelector('.stat-card').appendChild(canvas);
    
        const ctx = canvas.getContext('2d');
        const particles = [];
    
        function Particle(x, y) {
            this.x = x;
            this.y = y;
            this.speed = Math.random() * 2 + 1;
            this.size = Math.random() * 3 + 2;
        }
    
        function createParticles() {
            const particle = new Particle(
                Math.random() * canvas.width,
                Math.random() * canvas.height
            );
            particles.push(particle);
        }
    
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach((particle, index) => {
                particle.x += particle.speed;
                
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(64, 196, 255, 0.5)';
                ctx.fill();
                
                if (particle.x > canvas.width) {
                    particles.splice(index, 1);
                }
            });
            
            if (particles.length < 20) {
                createParticles();
            }
            
            requestAnimationFrame(animate);
        }
    
        animate();
    }    
    function initCharts() {
         // 节点分布图表
         const nodeCtx = document.getElementById('nodeChart').getContext('2d');
         new Chart(nodeCtx, {
             type: 'line',
             data: {
                 labels: ['亚洲', '欧洲', '北美', '南美', '非洲'],
                 datasets: [{
                     data: [5, 3, 2, 1, 1],
                     borderColor: 'rgba(13, 110, 253, 0.8)',
                     borderWidth: 2,
                     fill: false,
                     tension: 0.4
                 }]
             },
             options: {
                 plugins: { legend: { display: false } },
                 scales: { x: { display: false }, y: { display: false } }
             }
         });

         // 请求量图表
         const requestCtx = document.getElementById('requestChart').getContext('2d');
         const requestData = Array(10).fill(0).map(() => Math.floor(Math.random() * 100 + 150));
         const requestChart = new Chart(requestCtx, {
             type: 'line',
             data: {
                 labels: Array(10).fill(''),
                 datasets: [{
                     data: requestData,
                     borderColor: 'rgba(13, 202, 240, 0.8)',
                     borderWidth: 2,
                     fill: false,
                     tension: 0.4
                 }]
             },
             options: {
                 plugins: { legend: { display: false } },
                 scales: { x: { display: false }, y: { display: false } }
             }
         });

         // AI案件分析图表
         const caseCtx = document.getElementById('caseAnalysisChart').getContext('2d');
         new Chart(caseCtx, {
             type: 'radar',
             data: {
                 labels: ['合同审核', '知识产权', '劳动争议', '商事纠纷', '合规风险'],
                 datasets: [{
                     label: 'AI处理能力',
                     data: [95, 88, 92, 85, 90],
                     backgroundColor: 'rgba(13, 110, 253, 0.2)',
                     borderColor: 'rgba(13, 110, 253, 0.8)',
                     borderWidth: 2
                 }]
             },
             options: {
                 scales: {
                     r: {
                         beginAtZero: true,
                         max: 100,
                         ticks: { display: false }
                     }
                 }
             }
         });

         // 智能合规评分图表
         const complianceCtx = document.getElementById('complianceChart').getContext('2d');
         new Chart(complianceCtx, {
             type: 'doughnut',
             data: {
                 labels: ['已完成', '待优化'],
                 datasets: [{
                     data: [95, 5],
                     backgroundColor: [
                         'rgba(25, 135, 84, 0.8)',
                         'rgba(173, 181, 189, 0.2)'
                     ],
                     borderWidth: 0
                 }]
             },
             options: {
                 cutout: '80%',
                 plugins: { legend: { display: false } }
             }
         });

         // 更新运行时间指示器
         const uptimeIndicator = document.getElementById('uptimeIndicator');
         uptimeIndicator.innerHTML = Array(10).fill('<span class="dot"></span>').join('');
         document.querySelectorAll('.dot').forEach(dot => {
             dot.style.cssText = `
                 display: inline-block;
                 width: 8px;
                 height: 8px;
                 border-radius: 50%;
                 background-color: rgba(25, 135, 84, 0.8);
                 margin: 0 2px;
             `;
         });
     }

     // 初始化所有动态效果
     document.addEventListener('DOMContentLoaded', function() {
         updateStats();
         initBlockchainFlow();
         initCharts();
     });
     </script>
  </body>
  </html>