<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 合同模板表迁移
 * 
 * 创建存储合同模板数据的表结构，支持可视化编辑器生成的模板
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateContractTemplatesTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->string('name', 100)->comment('模板名称');
            $table->string('type', 20)->comment('模板类型(visual:可视化, html:HTML, pdf:PDF)');
            $table->unsignedBigInteger('category_id')->nullable()->comment('分类ID');
            $table->string('description', 255)->nullable()->comment('模板描述');
            $table->longText('content')->comment('模板内容(JSON格式)');
            $table->longText('preview')->nullable()->comment('预览内容(Base64编码)');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->index('tenant_id');
            $table->index('category_id');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
            
            // 外键约束
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contract_templates');
    }
}