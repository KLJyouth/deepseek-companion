@echo off
setlocal enabledelayedexpansion

echo MySQL服务管理助手
echo ==================

:: 检查管理员权限
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo 请以管理员权限运行此脚本！
    echo 右键点击脚本，选择"以管理员身份运行"
    pause
    exit /b 1
)

:menu
cls
echo.
echo MySQL服务管理菜单
echo ==================
echo 1. 检查MySQL服务状态
echo 2. 启动MySQL服务
echo 3. 停止MySQL服务
echo 4. 重启MySQL服务
echo 5. 检查MySQL端口
echo 6. 配置MySQL
echo 7. 创建数据库和用户
echo 8. 退出
echo.

set /p choice=请选择操作 (1-8): 

if "%choice%"=="1" goto check_status
if "%choice%"=="2" goto start_service
if "%choice%"=="3" goto stop_service
if "%choice%"=="4" goto restart_service
if "%choice%"=="5" goto check_port
if "%choice%"=="6" goto configure_mysql
if "%choice%"=="7" goto create_db
if "%choice%"=="8" goto end

echo 无效的选择，请重试
timeout /t 2 >nul
goto menu

:check_status
echo.
echo 检查MySQL服务状态...
sc query MySQL
pause
goto menu

:start_service
echo.
echo 正在启动MySQL服务...
net start MySQL
if %errorLevel% neq 0 (
    echo MySQL服务启动失败！
    echo 可能的原因：
    echo 1. 服务未安装
    echo 2. 服务已经在运行
    echo 3. 配置文件错误
)
pause
goto menu

:stop_service
echo.
echo 正在停止MySQL服务...
net stop MySQL
pause
goto menu

:restart_service
echo.
echo 正在重启MySQL服务...
net stop MySQL
timeout /t 5
net start MySQL
pause
goto menu

:check_port
echo.
echo 检查MySQL端口 (3306)...
netstat -an | find "3306"
if %errorLevel% neq 0 (
    echo 未发现MySQL端口监听
    echo 可能的原因：
    echo 1. MySQL服务未运行
    echo 2. 使用了不同的端口
    echo 3. 防火墙阻止
)
pause
goto menu

:configure_mysql
echo.
echo MySQL配置向导
echo ==============
echo 注意：此操作将修改MySQL配置
echo.
set /p confirm=是否继续？(Y/N) 
if /i not "%confirm%"=="Y" goto menu

:: 查找MySQL配置文件
set "MYSQL_CONF="
for %%i in (
    "C:\ProgramData\MySQL\MySQL Server 8.0\my.ini"
    "C:\Program Files\MySQL\MySQL Server 8.0\my.ini"
) do (
    if exist "%%~i" set "MYSQL_CONF=%%~i"
)

if "%MYSQL_CONF%"=="" (
    echo 未找到MySQL配置文件！
    pause
    goto menu
)

echo 找到配置文件：%MYSQL_CONF%
echo.
echo 当前设置：
type "%MYSQL_CONF%" | find "port"
type "%MYSQL_CONF%" | find "bind-address"
echo.
pause
goto menu

:create_db
echo.
echo 数据库创建向导
echo ==============
set /p dbname=请输入数据库名称: 
set /p username=请输入用户名: 
set /p password=请输入密码: 

echo.
echo 正在创建数据库和用户...
echo.

:: 创建SQL文件
echo CREATE DATABASE IF NOT EXISTS %dbname%; > create_db.sql
echo CREATE USER IF NOT EXISTS '%username%'@'localhost' IDENTIFIED BY '%password%'; >> create_db.sql
echo GRANT ALL PRIVILEGES ON %dbname%.* TO '%username%'@'localhost'; >> create_db.sql
echo FLUSH PRIVILEGES; >> create_db.sql

:: 执行SQL文件
mysql -u root -p < create_db.sql

if %errorLevel% neq 0 (
    echo 数据库创建失败！
    echo 请检查：
    echo 1. MySQL服务是否运行
    echo 2. Root密码是否正确
    echo 3. 用户权限是否足够
) else (
    echo 数据库和用户创建成功！
)

:: 清理SQL文件
del create_db.sql

pause
goto menu

:end
echo 感谢使用！
timeout /t 2 >nul
exit /b 0