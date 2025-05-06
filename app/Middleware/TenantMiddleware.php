<?php

namespace app\Middleware;

use app\Models\Tenant;
use Libs\LogHelper;
use Exception;

/**
 * 租户识别中间件
 * 
 * 用于多租户SaaS架构中识别当前请求的租户，并设置相应的租户上下文
 * 支持通过域名、请求头和查询参数识别租户
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class TenantMiddleware
{
    /**
     * 日志助手实例
     * 
     * @var \Libs\LogHelper
     */
    protected $logger;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logger = new LogHelper('tenant');
    }
    
    /**
     * 处理请求
     *
     * @param \Illuminate\Http\Request $request 请求对象
     * @param \Closure $next 下一个中间件
     * @return mixed
     */
    public function handle($request, $next)
    {
        try {
            // 识别租户
            $tenant = $this->identifyTenant($request);
            
            if ($tenant) {
                // 设置租户上下文
                $this->setTenantContext($tenant);
                
                // 记录日志
                $this->logger->info("租户识别成功", [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'domain' => $request->host()
                ]);
            } else {
                // 使用默认租户或公共访问模式
                $this->setDefaultTenantContext();
                
                $this->logger->info("使用默认租户", [
                    'domain' => $request->host()
                ]);
            }
            
            return $next($request);
        } catch (Exception $e) {
            $this->logger->error("租户识别失败: " . $e->getMessage(), [
                'domain' => $request->host()
            ]);
            
            // 使用默认租户或公共访问模式
            $this->setDefaultTenantContext();
            
            return $next($request);
        }
    }
    
    /**
     * 识别租户
     *
     * @param \Illuminate\Http\Request $request 请求对象
     * @return \app\Models\Tenant|null 租户对象
     */
    protected function identifyTenant($request)
    {
        // 1. 尝试从域名识别租户
        $domain = $request->host();
        $tenant = Tenant::where('domain', $domain)
            ->orWhere('subdomain', explode('.', $domain)[0])
            ->where('status', 1)
            ->first();
        
        if ($tenant) {
            return $tenant;
        }
        
        // 2. 尝试从请求头识别租户
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            $tenant = Tenant::where('id', $tenantId)
                ->where('status', 1)
                ->first();
            
            if ($tenant) {
                return $tenant;
            }
        }
        
        // 3. 尝试从查询参数识别租户
        $tenantId = $request->query('tenant_id');
        if ($tenantId) {
            $tenant = Tenant::where('id', $tenantId)
                ->where('status', 1)
                ->first();
            
            if ($tenant) {
                return $tenant;
            }
        }
        
        return null;
    }
    
    /**
     * 设置租户上下文
     *
     * @param \app\Models\Tenant $tenant 租户对象
     */
    protected function setTenantContext($tenant)
    {
        // 1. 绑定租户实例到容器
        app()->bind('tenant', function() use ($tenant) {
            return $tenant;
        });
        
        // 2. 设置租户数据库连接
        if ($tenant->db_name) {
            config(['database.connections.tenant' => [
                'driver' => config('database.connections.mysql.driver'),
                'host' => $tenant->db_host ?? config('database.connections.mysql.host'),
                'port' => $tenant->db_port ?? config('database.connections.mysql.port'),
                'database' => $tenant->db_name,
                'username' => $tenant->db_username ?? config('database.connections.mysql.username'),
                'password' => $tenant->db_password ?? config('database.connections.mysql.password'),
                'charset' => config('database.connections.mysql.charset'),
                'collation' => config('database.connections.mysql.collation'),
                'prefix' => config('database.connections.mysql.prefix'),
            ]]);
            
            // 设置默认连接为租户连接
            config(['database.default' => 'tenant']);
        }
        
        // 3. 设置租户配置
        if ($tenant->config && is_array($tenant->config)) {
            foreach ($tenant->config as $key => $value) {
                config([$key => $value]);
            }
        }
        
        // 4. 设置租户存储路径
        if ($tenant->storage_path) {
            config(['filesystems.disks.tenant' => [
                'driver' => 'local',
                'root' => storage_path('tenants/' . $tenant->id),
            ]]);
            
            // 设置默认磁盘为租户磁盘
            config(['filesystems.default' => 'tenant']);
        }
        
        // 5. 设置租户ID到请求
        request()->merge(['tenant_id' => $tenant->id]);
    }
    
    /**
     * 设置默认租户上下文
     */
    protected function setDefaultTenantContext()
    {
        // 1. 绑定默认租户实例到容器
        app()->bind('tenant', function() {
            return null;
        });
        
        // 2. 使用默认数据库连接
        config(['database.default' => 'mysql']);
        
        // 3. 使用默认存储配置
        config(['filesystems.default' => 'local']);
    }
}