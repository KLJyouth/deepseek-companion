<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 合同比对表迁移
 * 
 * 创建存储合同智能比对结果的表结构，支持差异标注和可视化展示
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class CreateContractComparisonsTable extends Migration
{
    /**
     * 运行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contract_comparisons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->string('name', 100)->comment('比对名称');
            $table->unsignedBigInteger('source_contract_id')->comment('源合同ID');
            $table->unsignedBigInteger('target_contract_id')->comment('目标合同ID');
            $table->json('comparison_result')->comment('比对结果(JSON格式)');
            $table->float('similarity_score', 8, 4)->comment('相似度得分');
            $table->integer('diff_count')->default(0)->comment('差异数量');
            $table->json('diff_summary')->nullable()->comment('差异摘要(JSON格式)');
            $table->longText('visual_diff')->nullable()->comment('可视化差异(HTML格式)');
            $table->text('ai_analysis')->nullable()->comment('AI分析结果');
            $table->string('created_by', 50)->nullable()->comment('创建人');
            $table->tinyInteger('status')->default(1)->comment('状态(1:有效, 0:无效)');
            $table->timestamps();
            
            // 索引
            $table->index('tenant_id');
            $table->index('source_contract_id');
            $table->index('target_contract_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['source_contract_id', 'target_contract_id']);
            
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
        Schema::dropIfExists('contract_comparisons');
    }
}