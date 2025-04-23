<?php
namespace App\Providers;

use Security\Path\PathEncryptionService;
use Security\Audit\PathAccessAuditor;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PathEncryptionService::class, function () {
            return new PathEncryptionService();
        });
        
        $this->app->singleton(PathAccessAuditor::class, function () {
            return new PathAccessAuditor();
        });
    }
    
    public function boot()
    {
        // 初始化审计系统
        $auditor = $this->app->make(PathAccessAuditor::class);
        $auditor->initialize();
    }
}