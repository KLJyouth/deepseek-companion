# Windows系统PDO MySQL扩展安装指南

## 问题诊断
系统检查显示缺少PDO MySQL扩展，错误信息：
```
PDO MySQL扩展未加载 - PDO MySQL驱动用于MySQL数据库支持
```

## 解决方案

### 1. 检查PHP安装
运行以下命令检查PHP是否已安装PDO和MySQL驱动：
```bash
php -m | findstr pdo
php -m | findstr mysql
```

### 2. 启用PDO MySQL扩展

1. 打开PHP配置文件(php.ini)
   - 通常位于：`C:\php\php.ini`
   - 可以通过命令查找：`php --ini`

2. 取消注释以下行（移除前面的分号）：
```ini
extension=pdo
extension=pdo_mysql
extension=php_mysqli.dll
```

3. 确保extension_dir指向正确的扩展目录：
```ini
extension_dir = "ext"
```

### 3. 验证扩展文件存在
检查`php\ext`目录下是否存在以下文件：
- php_pdo.dll
- php_pdo_mysql.dll
- php_mysqli.dll

如果缺少这些文件，需要重新安装PHP或从官网下载对应版本。

### 4. 重启Web服务器
修改配置后需要重启：
- Apache/IIS服务
- 或直接重启电脑

### 5. 验证安装
再次运行系统检查：
```bash
php tools/system_check.php
```

## 常见问题

### Q: 修改php.ini后仍无效？
A: 确保修改的是正确的php.ini文件，使用`php --ini`确认加载的配置文件路径。

### Q: 缺少扩展文件？
A: 从PHP官网下载对应版本的线程安全(TS)或非线程安全(NTS)的扩展包。

### Q: 仍然报错？
A: 检查PHP错误日志获取更详细的错误信息。

## 其他建议
- 考虑使用XAMPP/WAMP等集成环境简化配置
- 确保PHP版本与扩展版本匹配
- 开发环境建议使用PHP 8.1+版本