# 安全组件测试指南

## 测试环境要求
- PHP 7.4+
- PHPUnit 9+
- 测试数据库(如使用)

## 运行测试
```bash
# 运行所有测试
./vendor/bin/phpunit tests/

# 运行特定测试类
./vendor/bin/phpunit tests/PathSecurityTest.php
```

## 测试用例说明

### PathSecurityTest
测试路径安全系统的核心功能：

1. **路径加密测试**  
   - 验证路径加密/解密流程
   - 检查签名有效性
   - 测试动态盐值生成

2. **中间件测试**  
   - 验证请求路径处理
   - 测试恶意路径检测
   - 验证异常处理流程

3. **审计系统测试**  
   - 验证敏感路径识别
   - 检查日志记录功能
   - 测试报告生成功能

4. **新增测试用例**  
```php
// 测试动态盐值生成
public function testDynamicSaltGeneration()
{
    $salt1 = $this->encryptor->getCurrentSalt();
    $salt2 = $this->encryptor->getCurrentSalt();
    $this->assertNotEquals($salt1, $salt2);
}

// 测试报告生成
public function testReportGeneration() 
{
    $report = $this->auditor->generateReport(
        new DateTime('-1 day'),
        new DateTime()
    );
    $this->assertArrayHasKey('total_accesses', $report);
}
```

### 预期结果
- 所有测试应通过(绿色)
- 无跳过或警告的测试
- 测试覆盖率应达85%以上

## 测试数据
测试使用模拟数据，不会影响生产环境。

## 常见问题
Q: 测试失败提示数据库连接错误?  
A: 复制config.php为config.test.php并修改测试数据库配置

Q: 如何添加新测试?  
A: 在tests/目录下创建新的*Test.php文件，继承TestCase类