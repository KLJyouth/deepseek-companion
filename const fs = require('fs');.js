const fs = require('fs');
const path = require('path');

// 404.html 文件路径
const htmlFilePath = 'c:/Users/KLJyouth/Desktop/Stanfai_all/Stanfai_php/views/errors/404.html';

// 读取 404.html 文件内容
fs.readFile(htmlFilePath, 'utf8', (err, data) => {
    if (err) {
        console.error('读取文件失败:', err);
        return;
    }

    // 正则表达式匹配文件引用
    const cssRegex = /<link[^>]+href="([^"]+)"/g;
    const jsRegex = /<script[^>]+src="([^"]+)"/g;
    const videoRegex = /<source[^>]+src="([^"]+)"/g;

    const matches = [
        ...data.matchAll(cssRegex),
        ...data.matchAll(jsRegex),
        ...data.matchAll(videoRegex)
    ];

    // 检查每个引用文件是否存在
    matches.forEach(match => {
        const filePath = match[1].startsWith('/') ? 
            path.join('c:/Users/KLJyouth/Desktop/Stanfai_all/Stanfai_php/public', match[1].substring(1)) : 
            path.join(path.dirname(htmlFilePath), match[1]);

        fs.access(filePath, fs.constants.F_OK, (err) => {
            if (err) {
                console.log(`文件 ${filePath} 不存在`);
            } else {
                console.log(`文件 ${filePath} 存在`);
            }
        });
    });
});
