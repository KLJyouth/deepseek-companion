const algoliasearch = require('algoliasearch');
const fs = require('fs');
const path = require('path');

// 初始化客户端
const client = algoliasearch('YOUR_APP_ID', 'YOUR_API_KEY');
const index = client.initIndex('stanfai_docs');

// 读取文档内容
const docsPath = path.join(__dirname, '../');
const walk = (dir) => {
  let results = [];
  const list = fs.readdirSync(dir);
  
  list.forEach(file => {
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    
    if (stat && stat.isDirectory()) {
      results = results.concat(walk(fullPath));
    } else if (file.endsWith('.md')) {
      results.push(fullPath);
    }
  });
  
  return results;
};

// 构建索引记录
const records = walk(docsPath).map(file => {
  const content = fs.readFileSync(file, 'utf8');
  return {
    objectID: path.relative(docsPath, file),
    path: path.relative(docsPath, file),
    title: extractTitle(content),
    content: extractBody(content),
    lang: detectLanguage(file),
    lastModified: fs.statSync(file).mtime
  };
});

// 更新索引
index
  .saveObjects(records, { autoGenerateObjectIDIfNotExist: true })
  .then(({ objectIDs }) => {
    console.log('成功更新索引:', objectIDs.length, '条记录');
  })
  .catch(err => {
    console.error('索引更新失败:', err);
  });

// 辅助函数
function extractTitle(content) {
  const match = content.match(/^#\s+(.*)$/m);
  return match ? match[1] : '';
}

function extractBody(content) {
  return content.replace(/^#.*$/gm, '');
}

function detectLanguage(filepath) {
  if (filepath.includes('/i18n/en/')) return 'en';
  if (filepath.includes('/i18n/ja/')) return 'ja';
  return 'zh';
}