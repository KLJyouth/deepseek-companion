#!/usr/bin/env node
/**
 * 并行翻译处理脚本
 * 用法: node translate.js --parallel --workers=4
 */

const { Worker } = require('worker_threads');
const os = require('os');
const path = require('path');

// 配置参数
const args = process.argv.slice(2);
const parallel = args.includes('--parallel');
const workers = parseInt(args.find(arg => arg.startsWith('--workers='))?.split('=')[1]) || os.cpus().length;
const files = getTranslationFiles();

// 主处理函数
async function main() {
    if (parallel && workers > 1) {
        console.log(`🚀 启动 ${workers} 个并行工作线程`);
        await processParallel();
    } else {
        console.log('⏳ 顺序处理翻译文件');
        await processSequential();
    }
}

// 并行处理
function processParallel() {
    return new Promise((resolve) => {
        let completed = 0;
        const workerPath = path.join(__dirname, 'translate-worker.js');

        for (let i = 0; i < Math.min(workers, files.length); i++) {
            const worker = new Worker(workerPath, {
                workerData: {
                    files: distributeFiles(i),
                    config: loadConfig()
                }
            });

            worker.on('message', (msg) => {
                console.log(`[Worker ${i}] ${msg}`);
            });

            worker.on('error', (err) => {
                console.error(`[Worker ${i}] 错误:`, err);
            });

            worker.on('exit', () => {
                if (++completed === workers) {
                    resolve();
                }
            });
        }
    });
}

// 顺序处理
async function processSequential() {
    const { processFile } = require('./translate-worker');
    for (const file of files) {
        await processFile(file, loadConfig());
    }
}

// 辅助函数
function getTranslationFiles() {
    // 实际项目中应扫描locales目录
    return [
        'locales/zh/common.yml',
        'locales/en/common.yml',
        'locales/ja/common.yml'
    ];
}

function distributeFiles(workerId) {
    return files.filter((_, index) => index % workers === workerId);
}

function loadConfig() {
    try {
        return require('../config/translation.json');
    } catch {
        return { batchSize: 10, timeout: 5000 };
    }
}

main().catch(console.error);