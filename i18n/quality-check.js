#!/usr/bin/env node
/**
 * 术语一致性检查脚本
 * 用法: node quality-check.js --terminology
 */

const fs = require('fs').promises;
const path = require('path');
const yaml = require('js-yaml');
const { program } = require('commander');

program
  .option('--terminology', '检查术语一致性')
  .option('--report <file>', '生成报告文件')
  .parse(process.argv);

// 加载术语库
async function loadGlossary() {
  const file = await fs.readFile('i18n/glossary.yml', 'utf8');
  return yaml.load(file);
}

// 检查文件术语
async function checkFile(filePath, glossary) {
  const content = await fs.readFile(filePath, 'utf8');
  const data = yaml.load(content);
  
  const errors = [];
  const terms = glossary.terms || [];

  // 简化的术语检查逻辑
  terms.forEach(term => {
    if (term.key && !content.includes(term.key)) {
      errors.push({
        type: 'missing_term',
        term: term.key,
        file: path.basename(filePath)
      });
    }
  });

  return {
    file: path.basename(filePath),
    totalTerms: terms.length,
    errors,
    score: 100 - (errors.length / terms.length * 100)
  };
}

// 生成报告
async function generateReport(results) {
  if (program.opts().report) {
    const report = {
      date: new Date().toISOString(),
      summary: {
        totalFiles: results.length,
        avgScore: results.reduce((sum, r) => sum + r.score, 0) / results.length
      },
      details: results
    };
    
    await fs.writeFile(
      program.opts().report,
      JSON.stringify(report, null, 2)
    );
  }

  // 控制台输出
  console.log('术语检查结果:');
  results.forEach(r => {
    console.log(`- ${r.file}: ${r.score.toFixed(1)}% (${r.errors.length}个错误)`);
  });
}

// 主函数
async function main() {
  const glossary = await loadGlossary();
  const files = [
    'i18n/locales/en/common.yml',
    'i18n/locales/ja/common.yml'
  ];

  const results = [];
  for (const file of files) {
    results.push(await checkFile(file, glossary));
  }

  await generateReport(results);
  
  // 如果有严重错误则退出
  if (results.some(r => r.score < 90)) {
    process.exit(1);
  }
}

main().catch(err => {
  console.error('检查失败:', err);
  process.exit(1);
});