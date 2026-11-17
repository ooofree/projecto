<?php
// Menu items and permission gates stay owned by this plugin ($nav comes from
// LaborCrewPlugin). The shared partial only renders the structure.
$navItems = [];
foreach ([
    ['projects', 'projects', '项目首页'],
    ['crews', 'labor-crews', '班组联系人'],
    ['types', 'labor-crew-types', '工种目录'],
] as [$key, $page, $label]) {
    if (!empty($nav[$key])) {
        $navItems[] = ['page' => $page, 'label' => $label];
    }
}
$navCurrent = (string) ($_GET['page'] ?? '');
$navLabel = '施工班组';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';