<?php
// Menu items and permission gates stay owned by this plugin ($nav comes from
// MaterialManagementPlugin). The shared partial only renders the structure.
$navItems = [];
foreach ([
    ['projects', 'projects', '项目首页'],
    ['materials', 'materials', '材料管理'],
    ['brands', 'brands', '品牌'],
    ['auxiliaries', 'auxiliary-materials', '辅材'],
    ['suppliers', 'suppliers', '供应商'],
    ['supplierMaterials', 'supplier-materials', '供应商材料'],
    ['quotations', 'quotations', '报价'],
    ['tenders', 'tender-list', '招标清单'],
    ['samples', 'samples', '样品跟踪'],
    ['estimates', 'estimates', '测算明细'],
    ['demandSummary', 'demand-summary', '子材料汇总'],
] as [$key, $page, $label]) {
    if (!empty($nav[$key])) {
        $navItems[] = ['page' => $page, 'label' => $label];
    }
}
$navCurrent = (string) ($_GET['page'] ?? '');
$navLabel = '材料管理';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';