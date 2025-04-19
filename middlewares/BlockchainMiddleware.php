<?php
class BlockchainMiddleware {
    public function handle($request, $next) {
        $response = $next($request);

        if ($request->isMethod('post') && $request->fullUrlIs('/api/templates')) {
            try {
                $data = $request->all();
                $contentHash = hash('sha256', $data['content']);
                
                // 模拟区块链服务调用（待替换为真实服务）
                $blockchainResponse = Http::post('https://blockchain-service.com/api/notarize', [
                    'content_hash' => $contentHash,
                    'metadata' => [
                        'creator' => auth()->user()->id,
                        'timestamp' => now()->toIso8601String()
                    ]
                ]);

                if ($blockchainResponse->failed()) {
                    Log::error('区块链存证失败', ['error' => $blockchainResponse->body()]);
                }
            } catch (Exception $e) {
                Log::error('存证流程异常', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }
}