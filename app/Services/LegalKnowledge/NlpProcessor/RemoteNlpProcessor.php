<?php

namespace App\Services\LegalKnowledge\NlpProcessor;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * 远程NLP处理器实现
 * 
 * 通过API调用远程服务进行法律文本NLP处理
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class RemoteNlpProcessor implements NlpProcessorInterface
{
    /**
     * 处理器类型标识
     * 
     * @var string
     */
    protected $processorType = 'remote';
    
    /**
     * API端点URL
     * 
     * @var string
     */
    protected $apiEndpoint;
    
    /**
     * API密钥
     * 
     * @var string
     */
    protected $apiKey;
    
    /**
     * 请求超时时间（秒）
     * 
     * @var int
     */
    protected $timeout = 30;
    
    /**
     * 重试次数
     * 
     * @var int
     */
    protected $retries = 2;
    
    /**
     * 构造函数
     * 
     * @param string $apiEndpoint API端点URL
     * @param string $apiKey API密钥
     * @param array $options 其他选项
     */
    public function __construct(string $apiEndpoint, string $apiKey, array $options = [])
    {
        $this->apiEndpoint = rtrim($apiEndpoint, '/');
        $this->apiKey = $apiKey;
        
        // 设置其他选项
        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        }
        
        if (isset($options['retries'])) {
            $this->retries = $options['retries'];
        }
    }
    
    /**
     * 从文本中识别实体
     * 
     * {@inheritdoc}
     */
    public function recognizeEntities(string $text, array $options = []): array
    {
        try {
            // 构建请求参数
            $params = [
                'text' => $text,
                'options' => $options
            ];
            
            // 发送API请求
            $response = $this->sendRequest('/entity-recognition', $params);
            
            // 处理响应结果
            if (isset($response['entities']) && is_array($response['entities'])) {
                return $response['entities'];
            }
            
            Log::warning('远程实体识别返回格式异常', [
                'response' => $response
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error('远程实体识别失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'options' => $options
            ]);
            
            return [];
        }
    }
    
    /**
     * 从文本中提取实体间的关系
     * 
     * {@inheritdoc}
     */
    public function extractRelations(string $text, array $entities, array $options = []): array
    {
        try {
            // 构建请求参数
            $params = [
                'text' => $text,
                'entities' => $entities,
                'options' => $options
            ];
            
            // 发送API请求
            $response = $this->sendRequest('/relation-extraction', $params);
            
            // 处理响应结果
            if (isset($response['relations']) && is_array($response['relations'])) {
                return $response['relations'];
            }
            
            Log::warning('远程关系提取返回格式异常', [
                'response' => $response
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error('远程关系提取失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'entity_count' => count($entities),
                'options' => $options
            ]);
            
            return [];
        }
    }
    
    /**
     * 对文本进行语义分析
     * 
     * {@inheritdoc}
     */
    public function semanticAnalysis(string $text, array $options = []): array
    {
        try {
            // 构建请求参数
            $params = [
                'text' => $text,
                'options' => $options
            ];
            
            // 发送API请求
            $response = $this->sendRequest('/semantic-analysis', $params);
            
            // 处理响应结果
            if (is_array($response) && !isset($response['error'])) {
                return $response;
            }
            
            Log::warning('远程语义分析返回格式异常', [
                'response' => $response
            ]);
            
            return [
                'error' => '远程服务返回格式异常',
                'text_length' => strlen($text)
            ];
        } catch (\Exception $e) {
            Log::error('远程语义分析失败: ' . $e->getMessage(), [
                'text_length' => strlen($text),
                'options' => $options
            ]);
            
            return [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ];
        }
    }
    
    /**
     * 计算两段文本的相似度
     * 
     * {@inheritdoc}
     */
    public function calculateSimilarity(string $text1, string $text2, array $options = []): float
    {
        try {
            // 构建请求参数
            $params = [
                'text1' => $text1,
                'text2' => $text2,
                'options' => $options
            ];
            
            // 发送API请求
            $response = $this->sendRequest('/text-similarity', $params);
            
            // 处理响应结果
            if (isset($response['similarity']) && is_numeric($response['similarity'])) {
                return (float)$response['similarity'];
            }
            
            Log::warning('远程文本相似度计算返回格式异常', [
                'response' => $response
            ]);
            
            return 0.0;
        } catch (\Exception $e) {
            Log::error('远程文本相似度计算失败: ' . $e->getMessage(), [
                'text1_length' => strlen($text1),
                'text2_length' => strlen($text2),
                'options' => $options
            ]);
            
            return 0.0;
        }
    }
    
    /**
     * 发送API请求
     * 
     * @param string $endpoint API端点路径
     * @param array $params 请求参数
     * @return array 响应结果
     * @throws \Exception 请求失败时抛出异常
     */
    protected function sendRequest(string $endpoint, array $params): array
    {
        $url = $this->apiEndpoint . $endpoint;
        
        // 记录请求开始时间
        $startTime = microtime(true);
        
        try {
            // 构建HTTP请求
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->timeout($this->timeout)
            ->retry($this->retries, 1000) // 重试间隔1秒
            ->post($url, $params);
            
            // 记录请求耗时
            $duration = microtime(true) - $startTime;
            
            // 检查响应状态
            if ($response->successful()) {
                $result = $response->json();
                
                // 记录API调用日志
                Log::info('NLP API调用成功', [
                    'endpoint' => $endpoint,
                    'duration' => round($duration, 3) . 's',
                    'status' => $response->status()
                ]);
                
                return $result;
            } else {
                // 记录错误响应
                Log::error('NLP API调用失败', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'duration' => round($duration, 3) . 's'
                ]);
                
                throw new \Exception('API请求失败: HTTP ' . $response->status());
            }
        } catch (\Exception $e) {
            // 记录异常
            Log::error('NLP API请求异常', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'duration' => round(microtime(true) - $startTime, 3) . 's'
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 获取处理器类型
     * 
     * {@inheritdoc}
     */
    public function getProcessorType(): string
    {
        return $this->processorType;
    }
}