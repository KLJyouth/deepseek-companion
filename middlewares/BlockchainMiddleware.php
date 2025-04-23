<?php
class BlockchainMiddleware {
    public function handle($request, $next) {
        $response = $next($request);

        if ($request->isMethod('post') && $request->fullUrlIs('/api/templates')) {
            try {
                $data = $request->all();
                $contentHash = hash('sha256', $data['content']);
                
                // 长安链存证SDK调用
                $chainMakerConfig = [
                    'endpoint' => env('CHAINMAKER_ENDPOINT'),
                    'contract_name' => 'ContractNotarization',
                    'user_cert' => $this->prepareChainmakerCertificate(auth()->user())
                ];

                $chainmaker = new ChainMakerSDK($chainMakerConfig);
                $blockchainResponse = $chainmaker->invokeContract('saveEvidence', [
                    'content_hash' => $contentHash,
                    'owner_id' => auth()->user()->id,
                    'quantum_key_id' => app(QuantumKeyManager::class)->getCurrentKeyId()
                ]);

                if ($blockchainResponse->failed()) {
                    Log::error('长安链存证失败', [
                    'error' => $blockchainResponse->getError(),
                    'tx_id' => $blockchainResponse->getTxId(),
                    'chain_id' => env('CHAINMAKER_CHAIN_ID')
                ]);
                }
            } catch (Exception $e) {
                Log::error('存证流程异常', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }
}