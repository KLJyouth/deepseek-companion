<?php
namespace Admin\Services;

use Libs\DatabaseHelper;
use Admin\Models\OperationLog;

class AnalyticsService {
    const TF_SERVING_URL = 'http://localhost:8501/v1/models/contract_risk:predict';

    public function getDailyStats($date) {
        // 基础统计计算
        $db = DatabaseHelper::getInstance();
        $stats = $db->query("SELECT 
            COUNT(*) as total_contracts,
            SUM(amount) as total_amount,
            AVG(processing_time) as avg_time
            FROM contracts WHERE DATE(created_at) = ?", [$date]);

        // 时间序列预测
        $prediction = $this->predictNextDayVolume($date);
        
        OperationLog::log(
            $_SESSION['admin_id'], 
            '数据分析',
            ['date' => $date, 'type' => 'daily_stats']
        );

        return array_merge($stats, ['predicted_volume' => $prediction]);
    }

    private function predictNextDayVolume($date) {
        // 调用TensorFlow Serving进行预测
        $historicalData = $this->getWeeklyTrend($date);
        $payload = ['instances' => [$historicalData]];

        $ch = curl_init(self::TF_SERVING_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.getenv('TF_SERVING_TOKEN')
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = json_decode(curl_exec($ch), true);
        return $response['predictions'][0]['volume'] ?? 0;
    }

    private function getWeeklyTrend($date) {
        // 获取最近7天数据用于模型输入
        $db = DatabaseHelper::getInstance();
        return $db->query("SELECT 
            COUNT(*) as volume,
            AVG(amount) as avg_amount,
            STDDEV(processing_time) as time_deviation
            FROM contracts 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)", 
            [date('Y-m-d', strtotime($date.'-6 days')), $date]
        );
    }
}