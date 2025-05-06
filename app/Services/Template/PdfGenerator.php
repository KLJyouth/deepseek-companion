<?php

namespace app\Services\Template;

use Exception;
use Libs\LogHelper;

/**
 * PDF生成器
 * 
 * 用于生成合同模板预览和最终合同文档
 * 支持多种组件类型和样式定制
 * 
 * @version 1.0.0
 * @author 广西港妙科技有限公司
 * @copyright 版权所有 © 广西港妙科技有限公司
 */
class PdfGenerator
{
    /**
     * TCPDF实例
     * 
     * @var \TCPDF
     */
    protected $pdf;
    
    /**
     * 日志助手实例
     * 
     * @var \Libs\LogHelper
     */
    protected $logger;
    
    /**
     * 文档属性
     * 
     * @var array
     */
    protected $documentProperties = [];
    
    /**
     * 组件渲染器映射
     * 
     * @var array
     */
    protected $componentRenderers = [];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logger = new LogHelper('pdf');
        $this->initPdf();
        $this->registerComponentRenderers();
    }
    
    /**
     * 初始化PDF实例
     */
    protected function initPdf()
    {
        // 创建TCPDF实例
        $this->pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // 设置默认属性
        $this->pdf->SetCreator('司单服Ai智能安全法务');
        $this->pdf->SetAuthor('广西港妙科技有限公司');
        $this->pdf->SetTitle('合同模板');
        $this->pdf->SetSubject('合同模板预览');
        
        // 设置页眉和页脚
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(true);
        
        // 设置默认字体
        $this->pdf->SetFont('stsongstd', '', 10);
        
        // 设置边距
        $this->pdf->SetMargins(15, 15, 15);
        
        // 设置自动分页
        $this->pdf->SetAutoPageBreak(true, 15);
        
        // 添加第一页
        $this->pdf->AddPage();
    }
    
    /**
     * 注册组件渲染器
     */
    protected function registerComponentRenderers()
    {
        // 注册基础组件渲染器
        $this->componentRenderers = [
            'text' => [$this, 'renderTextComponent'],
            'heading' => [$this, 'renderHeadingComponent'],
            'image' => [$this, 'renderImageComponent'],
            'table' => [$this, 'renderTableComponent'],
            'signature' => [$this, 'renderSignatureComponent'],
            'clause' => [$this, 'renderClauseComponent'],
            'variable' => [$this, 'renderVariableComponent'],
            'page_break' => [$this, 'renderPageBreakComponent'],
            'list' => [$this, 'renderListComponent'],
            'checkbox' => [$this, 'renderCheckboxComponent']
        ];
    }
    
    /**
     * 设置文档属性
     *
     * @param array $properties 文档属性
     * @return $this
     */
    public function setDocumentProperties($properties)
    {
        $this->documentProperties = array_merge($this->documentProperties, $properties);
        
        // 应用属性到PDF实例
        if (isset($properties['title'])) {
            $this->pdf->SetTitle($properties['title']);
        }
        
        if (isset($properties['author'])) {
            $this->pdf->SetAuthor($properties['author']);
        }
        
        if (isset($properties['subject'])) {
            $this->pdf->SetSubject($properties['subject']);
        }
        
        if (isset($properties['creator'])) {
            $this->pdf->SetCreator($properties['creator']);
        }
        
        return $this;
    }
    
    /**
     * 添加组件
     *
     * @param string $type 组件类型
     * @param mixed $content 组件内容
     * @param array $properties 组件属性
     * @return $this
     * @throws \Exception 组件类型不支持时抛出异常
     */
    public function addComponent($type, $content, $properties = [])
    {
        // 检查组件类型是否支持
        if (!isset($this->componentRenderers[$type])) {
            $this->logger->warning("不支持的组件类型: {$type}");
            return $this;
        }
        
        try {
            // 调用对应的渲染器
            call_user_func(
                $this->componentRenderers[$type],
                $content,
                $properties
            );
        } catch (Exception $e) {
            $this->logger->error("渲染组件失败: " . $e->getMessage(), [
                'type' => $type,
                'properties' => $properties
            ]);
        }
        
        return $this;
    }
    
    /**
     * 渲染文本组件
     *
     * @param string $content 文本内容
     * @param array $properties 组件属性
     */
    protected function renderTextComponent($content, $properties = [])
    {
        // 设置字体样式
        $fontSize = $properties['fontSize'] ?? 10;
        $fontStyle = $properties['fontStyle'] ?? '';
        $this->pdf->SetFont('stsongstd', $fontStyle, $fontSize);
        
        // 设置文本颜色
        if (isset($properties['color'])) {
            $color = $this->hexToRgb($properties['color']);
            $this->pdf->SetTextColor($color['r'], $color['g'], $color['b']);
        } else {
            $this->pdf->SetTextColor(0, 0, 0);
        }
        
        // 设置对齐方式
        $align = $properties['align'] ?? 'L';
        
        // 写入文本
        $this->pdf->MultiCell(0, 5, $content, 0, $align, false, 1);
        
        // 恢复默认设置
        $this->pdf->SetFont('stsongstd', '', 10);
        $this->pdf->SetTextColor(0, 0, 0);
    }
    
    /**
     * 渲染标题组件
     *
     * @param string $content 标题内容
     * @param array $properties 组件属性
     */
    protected function renderHeadingComponent($content, $properties = [])
    {
        // 获取标题级别
        $level = $properties['level'] ?? 1;
        
        // 根据级别设置字体大小
        $fontSize = 20 - (($level - 1) * 2);
        if ($fontSize < 10) $fontSize = 10;
        
        // 设置字体样式
        $this->pdf->SetFont('stsongstd', 'B', $fontSize);
        
        // 设置对齐方式
        $align = $properties['align'] ?? 'C';
        
        // 写入标题
        $this->pdf->MultiCell(0, 8, $content, 0, $align, false, 1);
        
        // 添加一些空间
        $this->pdf->Ln(2);
        
        // 恢复默认设置
        $this->pdf->SetFont('stsongstd', '', 10);
    }
    
    /**
     * 渲染图片组件
     *
     * @param string $content 图片路径或Base64
     * @param array $properties 组件属性
     */
    protected function renderImageComponent($content, $properties = [])
    {
        // 检查内容是否为Base64
        $isBase64 = strpos($content, 'data:image') === 0;
        
        // 设置图片尺寸
        $width = $properties['width'] ?? 100;
        $height = $properties['height'] ?? 0; // 0表示自动计算高度
        
        // 设置对齐方式
        $align = $properties['align'] ?? 'C';
        
        // 计算X坐标
        $x = 15; // 默认左边距
        $pageWidth = $this->pdf->getPageWidth() - 30; // 减去左右边距
        
        if ($align == 'C') {
            $x = ($pageWidth - $width) / 2 + 15;
        } elseif ($align == 'R') {
            $x = $pageWidth - $width + 15;
        }
        
        // 添加图片
        if ($isBase64) {
            // 处理Base64图片
            $imgData = explode(',', $content, 2);
            $this->pdf->Image('@' . base64_decode($imgData[1]), $x, null, $width, $height);
        } else {
            // 处理图片路径
            $this->pdf->Image($content, $x, null, $width, $height);
        }
        
        // 添加一些空间
        $this->pdf->Ln(5);
    }
    
    /**
     * 渲染表格组件
     *
     * @param array $content 表格数据
     * @param array $properties 组件属性
     */
    protected function renderTableComponent($content, $properties = [])
    {
        // 检查表格数据
        if (!is_array($content) || empty($content)) {
            return;
        }
        
        // 获取表头和数据
        $headers = $content['headers'] ?? [];
        $rows = $content['rows'] ?? [];
        
        // 设置表格宽度
        $width = $properties['width'] ?? 0; // 0表示自动宽度
        
        // 设置表格样式
        $borderWidth = $properties['borderWidth'] ?? 0.1;
        $this->pdf->SetLineWidth($borderWidth);
        
        // 计算列宽
        $colCount = count($headers);
        $colWidth = $width > 0 ? $width / $colCount : 0;
        
        // 渲染表头
        if (!empty($headers)) {
            $this->pdf->SetFont('stsongstd', 'B', 10);
            $this->pdf->SetFillColor(240, 240, 240);
            
            foreach ($headers as $i => $header) {
                $this->pdf->Cell($colWidth, 7, $header, 1, ($i == $colCount - 1 ? 1 : 0), 'C', true);
            }
            
            $this->pdf->SetFont('stsongstd', '', 10);
        }
        
        // 渲染数据行
        $this->pdf->SetFillColor(255, 255, 255);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $this->pdf->Cell($colWidth, 6, $cell, 1, ($i == $colCount - 1 ? 1 : 0), 'L');
            }
        }
        
        // 添加一些空间
        $this->pdf->Ln(5);
    }
    
    /**
     * 渲染签名组件
     *
     * @param string $content 签名标签
     * @param array $properties 组件属性
     */
    protected function renderSignatureComponent($content, $properties = [])
    {
        // 设置签名区域样式
        $width = $properties['width'] ?? 60;
        $height = $properties['height'] ?? 20;
        
        // 设置对齐方式
        $align = $properties['align'] ?? 'L';
        
        // 计算X坐标
        $x = 15; // 默认左边距
        $pageWidth = $this->pdf->getPageWidth() - 30; // 减去左右边距
        
        if ($align == 'C') {
            $x = ($pageWidth - $width) / 2 + 15;
        } elseif ($align == 'R') {
            $x = $pageWidth - $width + 15;
        }
        
        // 获取当前Y坐标
        $y = $this->pdf->GetY();
        
        // 绘制签名区域
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->Line($x, $y + $height, $x + $width, $y + $height);
        
        // 添加签名标签
        $this->pdf->SetXY($x, $y + $height + 1);
        $this->pdf->SetFont('stsongstd', '', 8);
        $this->pdf->Cell($width, 5, $content, 0, 1, 'C');
        
        // 恢复默认设置
        $this->pdf->SetFont('stsongstd', '', 10);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.1);
        
        // 添加一些空间
        $this->pdf->Ln(10);
    }
    
    /**
     * 渲染法律条款组件
     *
     * @param array $content 条款内容
     * @param array $properties 组件属性
     */
    protected function renderClauseComponent($content, $properties = [])
    {
        // 检查条款内容
        if (!is_array($content)) {
            $content = ['title' => '', 'text' => $content];
        }
        
        // 获取条款标题和内容
        $title = $content['title'] ?? '';
        $text = $content['text'] ?? '';
        
        // 渲染标题
        if (!empty($title)) {
            $this->pdf->SetFont('stsongstd', 'B', 11);
            $this->pdf->MultiCell(0, 6, $title, 0, 'L', false, 1);
        }
        
        // 渲染内容
        $this->pdf->SetFont('stsongstd', '', 10);
        $this->pdf->MultiCell(0, 5, $text, 0, 'L', false, 1);
        
        // 添加一些空间
        $this->pdf->Ln(3);
    }
    
    /**
     * 渲染变量组件
     *
     * @param string $content 变量名
     * @param array $properties 组件属性
     */
    protected function renderVariableComponent($content, $properties = [])
    {
        // 设置变量样式
        $this->pdf->SetFont('stsongstd', 'U', 10);
        $this->pdf->SetTextColor(0, 0, 255);
        
        // 获取变量显示文本
        $displayText = $properties['displayText'] ?? "{{$content}}";
        
        // 写入变量
        $this->pdf->Write(5, $displayText, '', false, '', false);
        
        // 恢复默认设置
        $this->pdf->SetFont('stsongstd', '', 10);
        $this->pdf->SetTextColor(0, 0, 0);
    }
    
    /**
     * 渲染分页符组件
     *
     * @param string $content 未使用
     * @param array $properties 组件属性
     */
    protected function renderPageBreakComponent($content, $properties = [])
    {
        $this->pdf->AddPage();
    }
    
    /**
     * 渲染列表组件
     *
     * @param array $content 列表项
     * @param array $properties 组件属性
     */
    protected function renderListComponent($content, $properties = [])
    {
        // 检查列表项
        if (!is_array($content) || empty($content)) {
            return;
        }
        
        // 获取列表类型
        $type = $properties['type'] ?? 'bullet';
        
        // 设置字体
        $this->pdf->SetFont('stsongstd', '', 10);
        
        // 渲染列表项
        $i = 1;
        foreach ($content as $item) {
            // 根据列表类型设置前缀
            $prefix = '';
            if ($type == 'bullet') {
                $prefix = '• ';
            } elseif ($type == 'numbered') {
                $prefix = $i . '. ';
                $i++;
            }
            
            // 写入列表项
            $this->pdf->MultiCell(0, 5, $prefix . $item, 0, 'L', false, 1);
        }
        
        // 添加一些空间
        $this->pdf->Ln(2);
    }
    
    /**
     * 渲染复选框组件
     *
     * @param bool $content 是否选中
     * @param array $properties 组件属性
     */
    protected function renderCheckboxComponent($content, $properties = [])
    {
        // 获取复选框标签
        $label = $properties['label'] ?? '';
        
        // 获取当前位置
        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();
        
        // 绘制复选框
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->Rect($x, $y, 4, 4);
        
        // 如果选中，绘制勾选标记
        if ($content) {
            $this->pdf->Line($x, $y, $x + 4, $y + 4);
            $this->pdf->Line($x + 4, $y, $x, $y + 4);
        }
        
        // 写入标签
        $this->pdf->SetXY($x + 6, $y);
        $this->pdf->Cell(0, 4, $label, 0, 1);
        
        // 恢复默认设置
        $this->pdf->SetLineWidth(0.1);
        
        // 添加一些空间
        $this->pdf->Ln(2);
    }
    
    /**
     * 重置PDF实例
     *
     * @return $this
     */
    public function reset()
    {
        $this->initPdf();
        $this->documentProperties = [];
        return $this;
    }
    
    /**
     * 渲染PDF
     *
     * @param string $outputPath 输出路径，为空则返回Base64编码
     * @return string PDF文件路径或Base64编码
     */
    public function render($outputPath = '')
    {
        try {
            if (empty($outputPath)) {
                // 返回Base64编码
                return base64_encode($this->pdf->Output('', 'S'));
            } else {
                // 保存到文件
                $this->pdf->Output($outputPath, 'F');
                return $outputPath;
            }
        } catch (Exception $e) {
            $this->logger->error("渲染PDF失败: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 将十六进制颜色转换为RGB
     *
     * @param string $hex 十六进制颜色
     * @return array RGB颜色
     */
    protected function hexToRgb($hex)
    {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return ['r' => $r, 'g' => $g, 'b' => $b];
    }
}