<?php
namespace Services;

class MonitorService {
    private $analytics;
    private $reportBuilder;
    private $ruleEngine;
    
    public function __construct() {
        $this->analytics = new AdvancedAnalyticsService();
        $this->reportBuilder = new CustomReportBuilder();
        $this->ruleEngine = new AlertRuleEngine();
        
        // 配置规则联动
        $this->setupAlertRules();
    }
    
    private function setupAlertRules(): void {
        // CPU使用率超过90%触发内存检查
        $this->ruleEngine->addRule('high_cpu', function($metrics) {
            return $metrics['cpu_usage'] > 90;
        }, ['notify_admin', 'log_incident'])
        ->chainRules('high_cpu', 'check_memory', [
            'delay' => 300, // 5分钟后检查内存
            'conditions' => ['memory_threshold' => 80]
        ]);
        
        // 数据库连接数接近上限触发性能分析
        $this->ruleEngine->addRule('db_connections_high', function($metrics) {
            return $metrics['db_connections'] > $metrics['max_connections'] * 0.8;
        }, ['start_connection_analysis'])
        ->chainRules('db_connections_high', 'optimize_queries', [
            'delay' => 0,
            'conditions' => ['query_time_threshold' => 1000]
        ]);
    }
}
