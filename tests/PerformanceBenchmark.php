<?php
namespace Tests;

class PerformanceBenchmark
{
    private $results = [];
    private $startTime;
    
    public function startBenchmark($name)
    {
        $this->startTime = microtime(true);
    }
    
    public function endBenchmark($name)
    {
        $duration = microtime(true) - $this->startTime;
        $this->results[$name] = $duration;
    }

    public function runAllBenchmarks()
    {
        // 合同创建性能
        $this->benchmarkContractCreation();
        
        // 缓存性能
        $this->benchmarkCacheOperations();
        
        // 数据库性能
        $this->benchmarkDatabaseQueries();
        
        return $this->generateReport();
    }

    private function benchmarkContractCreation()
    {
        $this->startBenchmark('contract_creation');
        $service = new \Services\ContractService();
        
        for ($i = 0; $i < 100; $i++) {
            $service->createContract([
                'title' => "Benchmark Contract $i",
                'content' => str_repeat('Content ', 100),
                'parties' => ['1', '2']
            ]);
        }
        
        $this->endBenchmark('contract_creation');
    }

    private function benchmarkCacheOperations()
    {
        $this->startBenchmark('cache_operations');
        $cache = \Services\CacheService::getInstance();
        
        for ($i = 0; $i < 1000; $i++) {
            $cache->set("bench:$i", "value:$i", 60);
            $cache->get("bench:$i");
        }
        
        $this->endBenchmark('cache_operations');
    }

    private function generateReport(): array
    {
        return [
            'results' => $this->results,
            'summary' => [
                'total_time' => array_sum($this->results),
                'average_time' => array_sum($this->results) / count($this->results),
                'slowest_operation' => array_search(max($this->results), $this->results),
                'fastest_operation' => array_search(min($this->results), $this->results)
            ]
        ];
    }
}
