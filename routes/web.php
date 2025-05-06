<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;
use Middlewares\PathValidationMiddleware;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// 公开路由
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 需要认证的路由
Route::middleware(['auth', PathValidationMiddleware::class])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    
    // 安全相关路由 (需要管理员权限)
    Route::middleware(['admin'])->prefix('security')->group(function () {
        // 仪表盘
        Route::get('/dashboard', [SecurityController::class, 'dashboard'])->name('security.dashboard');
        
        // 备份管理
        Route::get('/backups', [SecurityController::class, 'backups'])->name('security.backups');
        Route::post('/run-backup', [SecurityController::class, 'runBackup'])->name('security.run-backup');
        Route::delete('/delete-backup', [SecurityController::class, 'deleteBackup'])->name('security.delete-backup');
        Route::get('/download-backup', [SecurityController::class, 'downloadBackup'])->name('security.download-backup');
        
        // 日志管理
        Route::get('/access-logs', [SecurityController::class, 'accessLogs'])->name('security.access-logs');
        Route::get('/audit-logs', [SecurityController::class, 'auditLogs'])->name('security.audit-logs');
        
        // GPG密钥管理
        Route::match(['get', 'post'], '/update-gpg', [SecurityController::class, 'updateGpgKey'])->name('security.update-gpg');
    });
});

// 法律知识图谱和合同比对路由
Route::middleware(['auth', PathValidationMiddleware::class])->prefix('legal')->group(function () {
    // 知识图谱相关
    Route::post('/build-knowledge-graph', [App\Http\Controllers\LegalKnowledgeController::class, 'buildKnowledgeGraph'])->name('legal.build-knowledge-graph');
    Route::post('/find-related-entities', [App\Http\Controllers\LegalKnowledgeController::class, 'findRelatedEntities'])->name('legal.find-related-entities');
    Route::post('/find-shortest-path', [App\Http\Controllers\LegalKnowledgeController::class, 'findShortestPath'])->name('legal.find-shortest-path');
    Route::post('/calculate-entity-centrality', [App\Http\Controllers\LegalKnowledgeController::class, 'calculateEntityCentrality'])->name('legal.calculate-entity-centrality');
    
    // 合同比对相关
    Route::post('/compare-contracts', [App\Http\Controllers\LegalKnowledgeController::class, 'compareContracts'])->name('legal.compare-contracts');
    Route::post('/merge-comparison-graph', [App\Http\Controllers\LegalKnowledgeController::class, 'mergeComparisonWithGraph'])->name('legal.merge-comparison-graph');
    Route::get('/comparison-visualization/{id}', [App\Http\Controllers\LegalKnowledgeController::class, 'getComparisonVisualization'])->name('legal.comparison-visualization');
    Route::post('/highlight-differences', [App\Http\Controllers\LegalKnowledgeController::class, 'highlightDifferences'])->name('legal.highlight-differences');
    
    // 合同风险分析
    Route::post('/analyze-contract-risk', [App\Http\Controllers\LegalKnowledgeController::class, 'analyzeContractRisk'])->name('legal.analyze-contract-risk');
});

// 引入合同风险分析路由
require __DIR__ . '/contract-risk-analysis.php';

// 其他业务路由...
Route::fallback(function () {
    return view('errors.404');
});