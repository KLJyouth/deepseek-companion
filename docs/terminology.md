# 技术术语规范

## 1. 核心术语表

| 术语 | 标准表述 | 允许的变体 | 禁用表述 |
|------|----------|------------|----------|
| Post-Quantum Cryptography | 量子加密(PQC) | 后量子加密 | 抗量子 |
| Blockchain Notarization | 区块链存证 | 链上存证 | 区块链记录 |
| Zero-Knowledge Proof | 零知识证明(ZKP) | ZK证明 | 零知识 |

## 2. 术语使用规范

### 2.1 量子加密(PQC)
- **定义**：抵抗量子计算机攻击的加密算法
- **使用场景**：
  ```markdown
  正确：使用量子加密(PQC)保护数据传输
  错误：使用抗量子算法保护数据
  ```

### 2.2 区块链存证
- **定义**：将关键操作哈希值写入区块链
- **示例**：
  ```php
  // 正确
  $blockchain->notarize('USER_LOGIN', $hash);
  
  // 错误
  $blockchain->record('USER_LOGIN', $hash);
  ```

### 2.3 零知识证明(ZKP)
- **定义**：证明方在不泄露信息的情况下验证陈述
- **代码注释**：
  ```php
  /**
   * @param string $prover 证明方
   * @param string $verifier 验证方 
   * @return ZKPResult 零知识证明结果
   */
  ```

## 3. 术语检查工具
```bash
# 检查术语使用一致性
grep -r -E "抗量子|区块链记录|零知识" docs/

# 自动替换错误术语
find docs/ -type f -exec sed -i 's/抗量子/量子加密(PQC)/g' {} +
```

[返回文档首页](../index.md)