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

// 其他业务路由...
Route::fallback(function () {
    return view('errors.404');
});