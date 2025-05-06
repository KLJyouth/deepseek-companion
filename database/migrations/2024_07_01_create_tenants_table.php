<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户表迁移
 * 
 * 创建多租户SaaS架构中的租户管理表结构
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateTenantsTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('租户名称');
            $table->string('domain', 100)->nullable()->comment('租户域名');
            $table->string('subdomain', 50)->nullable()->comment('租户子域名');
            $table->string('db_name', 50)->comment('租户数据库名称');
            $table->string('db_host', 100)->nullable()->comment('租户数据库主机');
            $table->string('db_port', 10)->nullable()->comment('租户数据库端口');
            $table->string('db_username', 50)->nullable()->comment('租户数据库用户名');
            $table->string('db_password', 100)->nullable()->comment('租户数据库密码');
            $table->string('storage_path', 255)->nullable()->comment('租户存储路径');
            $table->json('config')->nullable()->comment('租户配置');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->unique('domain');
            $table->unique('subdomain');
            $table->index('status');
        });
        
        // 添加租户ID字段到相关表
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->comment('租户ID');
                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        });
        
        Schema::table('contract_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_templates', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->comment('租户ID');
                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });
        
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->comment('租户ID');
                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        // 移除外键和字段
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
        
        Schema::table('contract_templates', function (Blueprint $table) {
            if (Schema::hasColumn('contract_templates', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
        
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
        
        // 删除租户表
        Schema::dropIfExists('tenants');
    }
}