#!/usr/bin/env node
/**
 * 实时质量监控脚本
 * 用法: node monitor.js --live --threshold=90
 */

const fs = require('fs');
const path = require('path');
const chokidar = require('chokidar');
const { program } = require('commander');
const WebSocket = require('ws');

program
  .option('--live', '实时监控模式')
  .option('--threshold <n>', '质量告警阈值', 90)
  .parse(process.argv);

// 监控配置
const config = {
  files: [
    'i18n/locales/**/*.yml',
    'i18n/glossary.yml'
  ],
  pollInterval: 5000,
  wsPort: 8081
};

// 质量状态
let qualityMetrics = {
  terminology: 100,
  completeness: 100,
  lastUpdated: null
};

// 启动WebSocket服务器
function startWebSocket() {
  const wss = new WebSocket.Server({ port: config.wsPort });
  
  wss.on('connection', (ws) => {
    ws.send(JSON.stringify(qualityMetrics));
    
    const interval = setInterval(() => {
      ws.send(JSON.stringify(qualityMetrics));
    }, 3000);

    ws.on('close', () => clearInterval(interval));
  });

  console.log(`WebSocket 服务器运行在端口 ${config.wsPort}`);
}

// 检查文件质量
async function checkQuality() {
  try {
    // 简化的质量检查逻辑
    qualityMetrics = {
      terminology: Math.floor(Math.random() * 20) + 80, // 模拟数据
      completeness: Math.floor(Math.random() * 10) + 90,
      lastUpdated: new Date().toISOString()
    };

    // 触发告警
    if (qualityMetrics.terminology < program.opts().threshold) {
      triggerAlert('terminology', qualityMetrics.terminology);
    }

    return qualityMetrics;
  } catch (err) {
    console.error('质量检查失败:', err);
    return null;
  }
}

// 触发告警
function triggerAlert(type, value) {
  console.warn(`⚠️ 告警: ${type} 质量下降至 ${value}%`);
  
  // 实际项目中应集成到通知系统
  if (process.env.SLACK_WEBHOOK) {
    // 发送Slack通知
  }
}

// 实时监控模式
if (program.opts().live) {
  startWebSocket();
  
  const watcher = chokidar.watch(config.files, {
    ignored: /(^|[\/\\])\../,
    persistent: true,
    interval: config.pollInterval
  });

  watcher
    .on('change', checkQuality)
    .on('error', error => console.error('监控错误:', error));

  console.log(`监控 ${config.files.length} 个翻译文件...`);
  checkQuality();
} else {
  // 单次检查模式
  checkQuality().then(metrics => {
    console.log('当前质量指标:', metrics);
    process.exit(0);
  });
}