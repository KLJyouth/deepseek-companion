<?php
namespace Services;

class SystemMonitorService {
    private $metrics = [];
    private $sampleInterval = 5; // 采样间隔(秒)
    private $sampleCount = 12; // 保留的样本数(1分钟数据)
    
    private $wsServer;
    
    public function __construct($wsServer = null) {
        $this->wsServer = $wsServer;
        $this->startMonitoring();
    }
    
    public function broadcastMetrics() {
        if ($this->wsServer) {
            $this->wsServer->broadcastMetrics();
        }
    }
    
    private function startMonitoring() {
        // 启动后台采样
        register_shutdown_function(function() {
            $this->sampleMetrics();
        });
    }
    
    public function sampleMetrics() {
        $timestamp = time();
        $metrics = [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'concurrent' => $this->getConcurrentRequests(),
            'throughput' => $this->getRequestThroughput()
        ];
        
        // 存储到数据库
        $db = new DatabaseHelper();
        foreach ($metrics as $type => $value) {
            $db->insert('system_metrics', [
                'metric' => $type,
                'value' => $value,
                'timestamp' => $timestamp
            ]);
        }
        
        // 保留内存中的样本
        $this->metrics[] = array_merge(['timestamp' => $timestamp], $metrics);
        if (count($this->metrics) > $this->sampleCount) {
            array_shift($this->metrics);
        }
    }
    
    public function getCurrentLoad(): array {
        if (empty($this->metrics)) {
            $this->sampleMetrics();
        }
        
        $recent = end($this->metrics);
        return [
            'cpu' => $recent['cpu'],
            'memory' => $recent['memory'],
            'concurrent' => $recent['concurrent'],
            'throughput' => $recent['throughput']
        ];
    }
    
    public function getLoadTrend(): array {
        $count = count($this->metrics);
        if ($count < 2) {
            return ['trend' => 'stable'];
        }
        
        $current = $this->metrics[$count-1];
        $previous = $this->metrics[$count-2];
        
        $trend = [
            'cpu' => $current['cpu'] - $previous['cpu'],
            'memory' => $current['memory'] - $previous['memory'],
            'concurrent' => $current['concurrent'] - $previous['concurrent']
        ];
        
        return $trend;
    }
    
    private function getCpuUsage(): float {
        $stat = file('/proc/stat');
        $info = preg_split('/\s+/', $stat[0]);
        $total = array_sum(array_slice($info, 1, 7));
        $idle = $info[4];
        return 1 - ($idle / max(1, $total));
    }
    
    private function getMemoryUsage(): float {
        $free = shell_exec('free');
        $mem = preg_split('/\s+/', $free);
        return 1 - ($mem[7] / max(1, $mem[1]));
    }
    
    private function getConcurrentRequests(): int {
        // 简化实现 - 实际应使用共享内存或Redis
        return rand(5, 150); // 模拟数据
    }
    
    private function getRequestThroughput(): float {
        // 请求/秒
        if (count($this->metrics) < 2) {
            return 0;
        }
        
        $prev = $this->metrics[count($this->metrics)-2];
        $curr = end($this->metrics);
        $interval = max(1, $curr['timestamp'] - $prev['timestamp']);
        return ($curr['throughput'] - $prev['throughput']) / $interval;
    }
}