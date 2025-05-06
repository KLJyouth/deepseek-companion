<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContractRiskAnalysisController;

/*
|--------------------------------------------------------------------------
| 合同风险分析路由
|--------------------------------------------------------------------------
|
| 这里定义了与合同风险分析相关的所有路由
|
*/

Route::prefix('contract-risk-analysis')->group(function () {
    // 显示合同风险分析页面
    Route::get('/', [ContractRiskAnalysisController::class, 'index'])->name('contract-risk-analysis.index');
    
    // 分析合同风险
    Route::post('/analyze', [ContractRiskAnalysisController::class, 'analyze'])->name('contract-risk-analysis.analyze');
    
    // 获取知识图谱可视化数据
    Route::post('/graph-visualization', [ContractRiskAnalysisController::class, 'getGraphVisualization'])
        ->name('contract-risk-analysis.graph-visualization');
    
    // 导出风险分析报告
    Route::post('/export-report', [ContractRiskAnalysisController::class, 'exportReport'])
        ->name('contract-risk-analysis.export-report');
});