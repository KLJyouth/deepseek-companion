<?php
declare(strict_types=1);

namespace Services;

use Libs\DatabaseHelper;
use Libs\CacheHelper;
use Exception;

class SystemMonitorService {
    private array $metrics = [];
    private int $sampleInterval = 5;
    private int $sampleCount = 12;
    private ?object $wsServer;
    private DatabaseHelper $db;
    private CacheHelper $cache;
    
    public function __construct(
        DatabaseHelper $db,
        CacheHelper $cache,
        ?object $wsServer = null
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->wsServer = $wsServer;
        $this->startMonitoring();
    }
    
    public function broadcastMetrics(): void {
        if ($this->wsServer) {
            $this->wsServer->broadcastMetrics();
        }
    }
    
    private function startMonitoring(): void {
        register_shutdown_function(fn() => $this->sampleMetrics());
    }
    
    public function sampleMetrics(): void {
        try {
            $timestamp = time();
            $metrics = [
                'cpu' => $this->getCpuUsage(),
                'memory' => $this->getMemoryUsage(),
                'concurrent' => $this->getConcurrentRequests(),
                'throughput' => $this->getRequestThroughput()
            ];
            
            // 存储到数据库
            foreach ($metrics as $type => $value) {
                $this->db->insert('system_metrics', [
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
        } catch (Exception $e) {
            error_log("采样指标失败: " . $e->getMessage());
        }
    }
    
    public function getSystemLoad(): array {
        if (empty($this->metrics)) {
            $this->sampleMetrics();
        }
        
        return end($this->metrics) ?: [
            'cpu' => 0.3,
            'memory' => 0.5,
            'concurrent' => 10,
            'throughput' => 5.0
        ];
    }
    
    public function getLoadTrend(): array {
        $count = count($this->metrics);
        if ($count < 2) {
            return ['trend' => 'stable'];
        }
        
        $current = $this->metrics[$count-1];
        $previous = $this->metrics[$count-2];
        
        return [
            'cpu' => $current['cpu'] - $previous['cpu'],
            'memory' => $current['memory'] - $previous['memory'],
            'concurrent' => $current['concurrent'] - $previous['concurrent']
        ];
    }
    
    private function getCpuUsage(): float {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows系统实现
            try {
                $wmi = new \COM("Winmgmts://");
                $cpus = $wmi->ExecQuery("SELECT LoadPercentage FROM Win32_Processor");
                $total = 0;
                $count = 0;
                foreach ($cpus as $cpu) {
                    $total += $cpu->LoadPercentage;
                    $count++;
                }
                return $count > 0 ? ($total / $count) / 100 : 0.3;
            } catch (Exception $e) {
                error_log("获取CPU使用率失败: " . $e->getMessage());
                return 0.3;
            }
        } else {
            // Linux/Unix系统实现
            try {
                $stat = file('/proc/stat');
                $info = preg_split('/\s+/', $stat[0]);
                $total = array_sum(array_slice($info, 1, 7));
                $idle = $info[4];
                return 1 - ($idle / max(1, $total));
            } catch (Exception $e) {
                error_log("获取CPU使用率失败: " . $e->getMessage());
                return 0.3;
            }
        }
    }
    
    private function getMemoryUsage(): float {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows系统实现
            try {
                $wmi = new \COM("Winmgmts://");
                $memory = $wmi->ExecQuery("SELECT TotalVisibleMemorySize, FreePhysicalMemory FROM Win32_OperatingSystem");
                foreach ($memory as $mem) {
                    $total = $mem->TotalVisibleMemorySize;
                    $free = $mem->FreePhysicalMemory;
                    return 1 - ($free / max(1, $total));
                }
                return 0.5;
            } catch (Exception $e) {
                error_log("获取内存使用率失败: " . $e->getMessage());
                return 0.5;
            }
        } else {
            // Linux/Unix系统实现
            try {
                $free = shell_exec('free');
                $mem = preg_split('/\s+/', trim($free));
                return 1 - ($mem[7] / max(1, $mem[1]));
            } catch (Exception $e) {
                error_log("获取内存使用率失败: " . $e->getMessage());
                return 0.5;
            }
        }
    }
    
    private function getConcurrentRequests(): int {
        try {
            return $this->cache->get('concurrent_requests') ?? 10;
        } catch (Exception $e) {
            error_log("获取并发请求数失败: " . $e->getMessage());
            return 10;
        }
    }
    
    private function getRequestThroughput(): float {
        if (count($this->metrics) < 2) {
            return 0;
        }
        
        $prev = $this->metrics[count($this->metrics)-2];
        $curr = end($this->metrics);
        $interval = max(1, $curr['timestamp'] - $prev['timestamp']);
        return ($curr['throughput'] - $prev['throughput']) / $interval;
    }
}