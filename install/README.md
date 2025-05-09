# 系统安装向导使用说明

## 系统要求

在开始安装前，请确保您的系统满足以下最低要求：

- **操作系统**: 类Unix系统 (推荐Linux)
- **Web服务器**: Nginx/Apache/IIS
- **PHP**: 版本 >= 7.2
- **MySQL**: 版本 5.7
- **磁盘空间**: 至少100MB可用空间
- **内存**: 建议至少512MB

## 安装步骤

1. **上传文件**
   - 将安装包上传到您的Web服务器
   - 确保所有文件和目录保持原有结构

2. **设置权限**
   ```bash
   chmod -R 755 install/
   chmod +x install/env_check.sh
   ```

3. **访问安装向导**
   - 在浏览器中访问: `http://您的域名/install/`

4. **按照向导步骤操作**
   - 步骤1: 阅读并同意许可协议
   - 步骤2: 系统环境检测
   - 步骤3: 填写系统配置信息
   - 步骤4: 执行安装
   - 步骤5: 完成安装

5. **删除安装目录 (安全建议)**
   ```bash
   rm -rf install/
   ```

## 宝塔面板用户

如果您使用宝塔面板，安装过程会更简单：

1. 确保已安装以下软件：
   - Nginx/Apache
   - PHP 7.2+
   - MySQL 5.7

2. 通过宝塔面板上传安装包

3. 直接访问安装向导URL

## 常见问题

### 环境检测失败怎么办？
- 检查错误信息中提示的组件
- 确保所有必需软件已安装并运行
- 检查PHP和MySQL版本是否符合要求

### 安装过程中断怎么办？
- 检查服务器错误日志
- 确保数据库连接信息正确
- 尝试重新运行安装向导

### 如何验证安装是否成功？
访问安装目录中的验证脚本：
```
http://您的域名/install/verify.php
```

## 故障排除

### 数据库连接问题
- 检查MySQL服务是否运行
- 验证用户名和密码是否正确
- 确保数据库用户有足够的权限

### 文件权限问题
```bash
# 设置正确的文件权限
chown -R www-data:www-data /您的安装目录
find /您的安装目录 -type d -exec chmod 755 {} \;
find /您的安装目录 -type f -exec chmod 644 {} \;
```

### 白屏或500错误
- 检查PHP错误日志
- 确保所有PHP扩展已安装
- 验证文件是否完整上传

## 安全建议

1. 安装完成后立即删除install目录
2. 定期备份您的数据和配置文件
3. 保持系统和软件更新到最新版本
4. 不要使用简单的管理员密码

## 获取帮助

如果您遇到无法解决的问题，请联系技术支持：
- 邮箱: support@example.com
- 电话: 400-123-4567

---

**注意**: 本安装向导仅用于初始安装。如需升级系统，请参考升级文档。