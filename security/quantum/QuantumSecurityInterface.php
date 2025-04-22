<?php
/**
 * QuantumSecurityInterface - 量子安全接口
 *
 * 定义TOE框架中技术维度量子安全层的核心功能接口
 * 所有量子安全相关服务必须实现该接口
 * 
 * @package DeepSeek\Security\Quantum
 * @author DeepSeek Security Team
 * @copyright © 广西港妙科技有限公司
 * @version 1.0.0
 * @license Proprietary
 */

namespace DeepSeek\Security\Quantum;

/**
 * QuantumSecurityInterface接口 - 定义量子安全层的标准功能
 * 
 * 该接口规定了量子安全服务必须实现的核心功能，包括密钥生成、
 * 量子加密、量子解密和量子签名验证等功能
 */
interface QuantumSecurityInterface
{
    /**
     * 生成量子安全密钥
     * 
     * 使用后量子密码学算法生成安全密钥对
     * 
     * @return array 包含密钥信息的数组
     */
    public function generateQuantumKey(): array;
    
    /**
     * 使用量子安全算法加密数据
     * 
     * @param string $data 要加密的数据
     * @param int $securityLevel 安全级别(1-5)
     * @return array 加密后的数据和元数据
     */
    public function encryptWithQuantum(string $data, int $securityLevel = 0): array;
    
    /**
     * 解密使用量子安全算法加密的数据
     * 
     * @param array $encryptedData 加密数据包
     * @return string 解密后的原始数据
     */
    public function decryptWithQuantum(array $encryptedData): string;
    
    /**
     * 验证量子签名
     * 
     * @param string $data 原始数据
     * @param array $signature 量子签名
     * @return bool 签名是否有效
     */
    public function verifyQuantumSignature(string $data, array $signature): bool;
    
    /**
     * 创建量子签名
     * 
     * @param string $data 要签名的数据
     * @param int $securityLevel 安全级别
     * @return array 签名数据包
     */
    public function createQuantumSignature(string $data, int $securityLevel = 0): array;
}