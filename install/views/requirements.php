<?php
$prevStep = 'welcome';
$nextButtonText = '继续';

// 系统要求检查
$requirements = [
    'php_version' => [
        'name' => 'PHP版本 >= 7.4',
        'passed' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ],
    'pdo' => [
        'name' => 'PDO扩展',
        'passed' => extension_loaded('pdo')
    ],
    'pdo_mysql' => [
        'name' => 'PDO MySQL驱动',
        'passed' => extension_loaded('pdo_mysql')
    ],
    'mbstring' => [
        'name' => 'mbstring扩展',
        'passed' => extension_loaded('mbstring')
    ],
    'json' => [
        'name' => 'JSON扩展',
        'passed' => extension_loaded('json')
    ]
];

// 目录权限检查
$writableDirs = ['storage', 'cache', 'logs'];
foreach ($writableDirs as $dir) {
    $requirements["dir_$dir"] = [
        'name' => "目录可写: $dir",
        'passed' => is_writable(__DIR__."/../../../$dir")
    ];
}

// 检查是否全部通过
$allPassed = true;
foreach ($requirements as $req) {
    if (!$req['passed']) {
        $allPassed = false;
        break;
    }
}
?>

<form id=\"install-form\" method=\"post\" action=\"?step=database\">
    <input type=\"hidden\" name=\"csrf_token\" value=\"<?= $csrfToken ?>\">
    
    <h2>系统环境检查</h2>
    
    <table class=\"requirements-table\">
        <?php foreach ($requirements as $id => $req): ?>
        <tr class=\"<?= $req['passed'] ? 'passed' : 'failed' ?>\">
            <td><?= htmlspecialchars($req['name']) ?></td>
            <td><?= $req['passed'] ? '' : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <?php if (!$allPassed): ?>
        <div class=\"alert alert-danger\">
            请先解决所有标有的问题后再继续安装
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class=\"alert alert-danger\"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</form>
