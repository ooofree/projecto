<?php
// 本插件导航与权限门控由 BomCostingPlugin 提供；共享 partial 只渲染结构。
$navItems = [];
foreach ([
    ['costings', 'costings', 'BOM 组价'],
    ['prices', 'cost-prices', '成本价'],
    ['catalog', 'cost-catalog', '资源目录'],
    ['actuals', 'actual-costs', '实际费用'],
] as [$key, $page, $label]) {
    if (!empty($nav[$key])) {
        $navItems[] = ['page' => $page, 'label' => $label];
    }
}
$navCurrent = (string) ($_GET['page'] ?? '');
$navLabel = 'BOM 组价与成本导航';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
