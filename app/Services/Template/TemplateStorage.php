<?php

namespace app\Services\Template;

use app\Models\ContractTemplate;
use Libs\LogHelper;
use Exception;

/**
 * 合同模板存储服务
 * 
 * 提供合同模板的存储、检索和预览生成功能
 * 支持可视化编辑器生成的模板结构
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class TemplateStorage
{
    /**
     * 日志助手实例
     * 
     * @var \Libs\LogHelper
     */
    protected $logger;
    
    /**
     * PDF生成器实例
     * 
     * @var object
     */
    protected $pdfGenerator;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logger = new LogHelper('template');
        $this->pdfGenerator = new PdfGenerator();
    }
    
    /**
     * 保存可视化模板
     *
     * @param array $data 模板数据
     * @param int|null $tenantId 租户ID
     * @return int 模板ID
     * @throws \Exception 保存失败时抛出异常
     */
    public function saveVisualTemplate($data, $tenantId = null)
    {
        try {
            // 验证模板数据
            $this->validateTemplateData($data);
            
            // 创建或更新模板
            $template = isset($data['id']) ? ContractTemplate::find($data['id']) : new ContractTemplate();
            
            if (!$template) {
                $template = new ContractTemplate();
            }
            
            // 设置模板属性
            $template->name = $data['name'] ?? '未命名模板';
            $template->type = 'visual';
            $template->category_id = $data['category_id'] ?? null;
            $template->description = $data['description'] ?? null;
            $template->content = json_encode([
                'version' => '2.0',
                'structure' => $data['components'] ?? [],
                'variables' => $data['variables'] ?? []
            ], JSON_UNESCAPED_UNICODE);
            
            // 生成预览
            $template->preview = $this->generatePreview($data);
            
            // 设置租户ID
            if ($tenantId) {
                $template->tenant_id = $tenantId;
            }
            
            // 保存模板
            $template->save();
            
            // 记录日志
            $this->logger->info("保存可视化模板", [
                'template_id' => $template->id,
                'name' => $template->name
            ]);
            
            return $template->id;
        } catch (Exception $e) {
            $this->logger->error("保存可视化模板失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 生成PDF预览
     *
     * @param array $data 模板数据
     * @return string 预览文件路径或Base64编码
     */
    public function generatePreview($data)
    {
        try {
            // 重置PDF生成器
            $this->pdfGenerator->reset();
            
            // 设置文档属性
            $this->pdfGenerator->setDocumentProperties([
                'title' => $data['name'] ?? '合同模板预览',
                'author' => '司单服Ai智能安全法务',
                'creator' => '广西港妙科技有限公司'
            ]);
            
            // 添加组件
            if (isset($data['components']) && is_array($data['components'])) {
                foreach ($data['components'] as $component) {
                    $this->pdfGenerator->addComponent(
                        $component['type'],
                        $component['content'] ?? '',
                        $component['properties'] ?? []
                    );
                }
            }
            
            // 渲染PDF
            return $this->pdfGenerator->render();
        } catch (Exception $e) {
            $this->logger->error("生成PDF预览失败: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 验证模板数据
     *
     * @param array $data 模板数据
     * @return bool 验证结果
     * @throws \Exception 验证失败时抛出异常
     */
    protected function validateTemplateData($data)
    {
        // 检查必要字段
        if (!isset($data['components']) || !is_array($data['components'])) {
            throw new Exception("模板组件数据无效");
        }
        
        // 检查组件结构
        foreach ($data['components'] as $component) {
            if (!isset($component['type'])) {
                throw new Exception("组件类型未定义");
            }
        }
        
        return true;
    }
    
    /**
     * 获取模板详情
     *
     * @param int $id 模板ID
     * @return array 模板详情
     */
    public function getTemplate($id)
    {
        $template = ContractTemplate::find($id);
        
        if (!$template) {
            return null;
        }
        
        // 解析模板内容
        $content = json_decode($template->content, true);
        
        return [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'category_id' => $template->category_id,
            'description' => $template->description,
            'components' => $content['structure'] ?? [],
            'variables' => $content['variables'] ?? [],
            'preview' => $template->preview,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at
        ];
    }
    
    /**
     * 删除模板
     *
     * @param int $id 模板ID
     * @return bool 删除结果
     */
    public function deleteTemplate($id)
    {
        try {
            $template = ContractTemplate::find($id);
            
            if (!$template) {
                return false;
            }
            
            $template->delete();
            
            $this->logger->info("删除模板", ['template_id' => $id]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error("删除模板失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取模板列表
     *
     * @param array $filters 过滤条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array 模板列表和分页信息
     */
    public function getTemplateList($filters = [], $page = 1, $pageSize = 20)
    {
        $query = ContractTemplate::query();
        
        // 应用过滤条件
        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        
        // 计算总数
        $total = $query->count();
        
        // 分页
        $templates = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(function ($template) {
                // 解析模板内容
                $content = json_decode($template->content, true);
                
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'category_id' => $template->category_id,
                    'description' => $template->description,
                    'preview' => $template->preview,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at
                ];
            });
        
        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'templates' => $templates
        ];
    }
}