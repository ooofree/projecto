<?php
// One flat workspace replaces the old overview/items split: a single nav entry
// keeps the sidebar, module nav and crumbs pointing at the same place.
$navItems = [];
if (!empty($nav['overview']) || !empty($nav['items'])) {
    $navItems[] = ['page' => 'closeouts', 'label' => '工程消项'];
}
$navCurrent = (string) ($_GET['page'] ?? '');
if ($navCurrent === 'closeout-items') {
    $navCurrent = 'closeouts';
}
$navLabel = '工程消项';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
