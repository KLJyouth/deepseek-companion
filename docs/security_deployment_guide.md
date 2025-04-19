# 安全防护系统部署指南

这里将详细介绍安全防护系统的部署步骤，从整体部署到模块部署逐步展开。

## 整体部署

（此处添加整体部署步骤描述）

## 模块部署

### 多层级防御模块
1. 安装基础安全组件：运行 `npm install --save security-middleware threat-detector`
   - 组件说明：security-middleware提供基础HTTP安全头设置，threat-detector提供实时威胁检测
   - 版本要求：security-middleware@^2.3.0, threat-detector@^1.5.0
2. 配置防火墙规则：编辑 `config/security.js` 文件，设置适当的访问控制规则
   - 示例配置：
     ```javascript
     module.exports = {
       firewall: {
         ipWhitelist: ['192.168.1.0/24'],
         rateLimit: {
           windowMs: 15 * 60 * 1000,
           max: 100
         }
       }
     };
     ```
3. 启用日志监控：在 `services/LogService.js` 中配置日志级别和存储位置
   - 推荐配置：
     ```javascript
     const logger = new LogService({
       level: 'debug',
       storage: {
         type: 'elasticsearch',
         hosts: ['http://localhost:9200'],
         index: 'security-logs'
       }
     });
     ```
4. 测试防御层：运行 `npm run test:security` 验证各层防御功能
   - 测试内容：包括XSS防护、CSRF防护、暴力破解防护等

### 数据保护模块
1. 安装加密组件：运行 `npm install --save data-encryptor secure-storage`
   - 组件说明：data-encryptor提供AES-256加密，secure-storage提供安全存储方案
   - 版本要求：data-encryptor@^3.1.0, secure-storage@^2.0.0
2. 配置加密密钥：在 `.env` 文件中设置
   ```
   DATA_ENCRYPTION_KEY=your_32_char_key_here
   ```
3. 初始化加密服务：在 `services/DataProtectionService.js` 中添加
   ```javascript
   const encryptor = require('data-encryptor');
   const storage = require('secure-storage');
   
   encryptor.init(process.env.DATA_ENCRYPTION_KEY);
   storage.configure({
     encryption: true,
     ttl: 3600 // 1小时过期
   });
   ```
4. 测试数据保护：运行 `npm run test:data-protection` 验证
   - 测试内容：包括数据加密、解密、存储安全性和过期策略

### DeepSeek集成模块

#### 1. 准备工作
1. 申请API密钥：
   - 登录DeepSeek控制台(https://console.deepseek.com)
   - 创建新应用，选择"安全分析"产品类型
   - 获取API密钥和项目ID

2. 环境配置：
   ```bash
   # 安装必要依赖
   npm install @deepseek/sdk@latest jsonwebtoken
   ```

3. 环境变量配置(.env文件)：
   ```ini
   # DeepSeek配置
   DEEPSEEK_API_KEY=your_api_key_here
   DEEPSEEK_PROJECT_ID=your_project_id
   JWT_SECRET=your_jwt_secret
   ```

#### 2. 服务初始化
1. 创建配置文件(config/deepseek.js)：
   ```javascript
   module.exports = {
     apiKey: process.env.DEEPSEEK_API_KEY,
     projectId: process.env.DEEPSEEK_PROJECT_ID,
     endpoints: {
       analysis: 'https://api.deepseek.com/v1/threat-analysis',
       monitoring: 'wss://monitor.deepseek.com/realtime'
     },
     timeout: 10000 // 10秒超时
   };
   ```

2. 初始化服务(services/AIService.js)：
   ```javascript
   const DeepSeekClient = require('@deepseek/sdk');
   const config = require('../config/deepseek');
   const jwt = require('jsonwebtoken');

   class AIService {
     constructor() {
       this.client = new DeepSeekClient(config);
       this.cache = new Map(); // 本地缓存
     }

     async analyze(data) {
       const cacheKey = this._generateCacheKey(data);
       if (this.cache.has(cacheKey)) {
         return this.cache.get(cacheKey);
       }

       const token = jwt.sign({ scope: 'analysis' }, process.env.JWT_SECRET);
       try {
         const result = await this.client.analyze({
           jwt_token: token,
           data
         });
         
         this.cache.set(cacheKey, result);
         return result;
       } catch (error) {
         console.error('DeepSeek分析失败:', error);
         throw this._handleError(error);
       }
     }

     // 其他辅助方法...
   }

   module.exports = new AIService();
   ```

#### 3. 监控配置
1. 实时监控设置：
   ```javascript
   const WebSocket = require('ws');
   const config = require('../config/deepseek');

   const socket = new WebSocket(config.endpoints.monitoring);

   socket.on('open', () => {
     console.log('DeepSeek监控连接已建立');
     socket.send(JSON.stringify({
       action: 'subscribe',
       project_id: config.projectId
     }));
   });

   socket.on('message', (data) => {
     const alert = JSON.parse(data);
     // 处理安全警报
   });
   ```

#### 4. 测试验证
1. 单元测试：
   ```javascript
   describe('DeepSeek集成测试', () => {
     it('应成功分析威胁数据', async () => {
       const testData = { type: 'threat', payload: {...} };
       const result = await AIService.analyze(testData);
       expect(result).toHaveProperty('threat_level');
     });
   });
   ```

2. 集成测试命令：
   ```bash
   # 运行测试
   npm run test:deepseek

   # 测试覆盖率
   npm run test:deepseek -- --coverage
   ```

#### 5. 运维监控
1. 健康检查端点：
   ```javascript
   router.get('/deepseek/health', (req, res) => {
     AIService.healthCheck()
       .then(status => res.json(status))
       .catch(error => res.status(503).json({ error }));
   });
   ```

2. Prometheus监控配置：
   ```yaml
   scrape_configs:
     - job_name: 'deepseek'
       metrics_path: '/deepseek/metrics'
       static_configs:
         - targets: ['localhost:3000']
   ```