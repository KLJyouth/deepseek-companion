<?php
class BlockchainMiddleware {
    // 长安链批量存证队列
    private array $batchQueue = [];
    private const BATCH_SIZE = 50;

    public function handle($request, $next) {
        $response = $next($request);

        if ($request->isMethod('post') && $request->fullUrlIs('/api/templates')) {
            $this->queueEvidence($request);
            $this->processBatch();
        }
        return $response;
    }

    /**
     * 批量存证处理
     */
    private function processBatch(): void {
        if (count($this->batchQueue) >= self::BATCH_SIZE) {
            try {
                $chainMakerConfig = [
                    'endpoint' => env('CHAINMAKER_ENDPOINT'),
                    'contract_name' => 'ContractNotarization',
                    'batch_mode' => true,
                    'timeout' => 30 // 长安链批量模式超时设置
                ];

                $chainmaker = new ChainMakerSDK($chainMakerConfig);
                $batchResponse = $chainmaker->invokeContract('batchSaveEvidence', [
                    'evidence_list' => $this->batchQueue,
                    'quantum_key_id' => app(QuantumKeyManager::class)->getCurrentKeyId()
                ]);

                if ($batchResponse->failed()) {
                    Log::error('长安链批量存证失败', [
                        'error' => $batchResponse->getError(),
                        'failed_count' => count($this->batchQueue)
                    ]);
                    // 失败重试机制
                    $this->retryFailedBatch();
                } else {
                    $this->batchQueue = [];
                }
            } catch (Exception $e) {
                Log::error('批量存证异常', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 加入存证队列
     */
    private function queueEvidence($request): void {
        $data = $request->all();
        $this->batchQueue[] = [
            'content_hash' => hash('sha256', $data['content']),
            'owner_id' => auth()->user()->id,
            'timestamp' => microtime(true),
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip()
            ]
        ];
    }
}