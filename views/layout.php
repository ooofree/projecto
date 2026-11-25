<?php
$platformName = (string) ($platformName ?? 'Project O');
$brandMark = (string) ($brandMark ?? mb_strtoupper(mb_substr($platformName, 0, 1, 'UTF-8'), 'UTF-8'));

/**
 * Cache-busting for the shared assets. Chat in-app browsers (WeChat especially)
 * keep aggressive caches and ignore revalidation, so a stale app.js can run
 * against fresh markup and misbehave. A content fingerprint in the URL makes
 * every asset change a new URL, which no cache can serve stale.
 */
$assetVersion = static function (string $relative): string {
    static $cache = [];
    if (!isset($cache[$relative])) {
        $path = dirname(__DIR__) . '/public/' . $relative;
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $cache[$relative] = substr(sha1(($mtime === false ? 0 : $mtime) . ':' . ($size === false ? 0 : $size)), 0, 10);
    }

    return $relative . '?v=' . $cache[$relative];
};

/**
 * Cache-busting for plugin-owned assets. A business page declares the module
 * assets it needs, so they load only on that page instead of every request.
 *
 * Module directories live outside the web root, so the file is streamed by the
 * plugin's own asset route rather than exposed as a browsable path. The route
 * validates the module and file name against its manifest-declared assets.
 */
$moduleAssetVersion = static function (array $asset): string {
    $module = (string) ($asset['module'] ?? '');
    $file = (string) ($asset['file'] ?? '');
    $version = (string) ($asset['version'] ?? '0');
    if (preg_match('/^[a-z0-9.-]+$/', $module) !== 1 || preg_match('/^[a-z0-9._-]+$/', $file) !== 1) {
        throw new RuntimeException('Invalid module asset declaration.');
    }

    return '?page=module-asset&module=' . rawurlencode($module)
        . '&file=' . rawurlencode($file) . '&v=' . rawurlencode($version);
};

/*
 * V2.1 global layout (C plan): top global bar + project-level sidebar.
 *
 * Sidebar entries are aggregated from plugin declarations (PluginRegistry::
 * sidebarItems); the core never hardcodes business menus. Project groups only
 * render inside a valid current-project context — outside a project (project
 * list, profile, admin, login, error) the sidebar carries platform entries only.
 * Permission gates stay owned by the plugins; hiding an entry is UX only and
 * every route keeps its server-side authorization.
 */
$sidebarItems = is_array($sidebarItems ?? null) ? $sidebarItems : [];
$layoutProject = is_array($currentProject ?? null) ? $currentProject : null;
$layoutPage = (string) ($_GET['page'] ?? 'projects');
// Project-level groups render whenever a valid current project exists in the
// session — including the home page, whose cards enter that project. Without
// a current project (or on anonymous pages) only the platform bucket shows.
$layoutProjectScoped = $layoutProject !== null;
$projectStatusLabels = [
    'planning' => '计划中',
    'ongoing' => '进行中',
    'completed' => '已完成',
    'on-hold' => '已暂停',
];
/*
 * Information architecture (user directive 2026-11-23): seven business areas
 * in a fixed order. The map owns the order and the Chinese label; plugins still
 * declare which entries exist and whether the viewer may see them. Items carry
 * an optional 'order' so a group can interleave contributions from different
 * plugins (e.g. 招标清单 belongs to the material plugin but sits in 项目管理).
 */
$sidebarGroups = [
    'projects' => '项目管理',
    'materials' => '材料管理',
    'supply' => '供应链管理',
    'bom' => 'BOM 与成本管理',
    'collab' => '工程协同',
    'construction' => '施工管理',
    'settings' => '基础设置',
];
// Detail and recycle-bin sub-views keep their parent module entry highlighted.
$sidebarAliases = [
    'costing-detail' => 'costings',
    'closeout-items' => 'closeouts',
    'closeout-select' => 'closeouts',
    'closeout-item' => 'closeouts',
    'labor-crew-share' => 'labor-crews',
    'labor-crew-share-image' => 'labor-crews',
];
$sidebarActivePage = $sidebarAliases[$layoutPage] ?? $layoutPage;
// 基础设置 carries the core admin entry next to plugin-declared settings
// pages, so it renders outside a project as well.
$sidebarMenuItems = $sidebarItems;
if ($canManagePlatform) {
    $sidebarMenuItems[] = ['group' => 'settings', 'page' => 'admin', 'label' => '系统设置', 'icon' => '⚙', 'order' => 10];
}
usort($sidebarMenuItems, static function (array $a, array $b): int {
    return ((int) ($a['order'] ?? 500)) <=> ((int) ($b['order'] ?? 500));
});
$groupedSidebar = [];
foreach ($sidebarMenuItems as $sidebarItem) {
    $group = (string) ($sidebarItem['group'] ?? '');
    if (!isset($sidebarGroups[$group]) || (!$layoutProjectScoped && $group !== 'settings')) {
        continue;
    }
    $groupedSidebar[$group][] = $sidebarItem;
}
$layoutCrumb = '';
foreach ($sidebarMenuItems as $sidebarItem) {
    if (($sidebarItem['page'] ?? '') === $layoutPage) {
        $layoutCrumb = (string) $sidebarItem['label'];
        break;
    }
}
if ($layoutCrumb === '') {
    $layoutCrumb = match ($layoutPage) {
        'projects' => '项目首页',
        'profile' => '个人设置',
        'admin' => '系统设置',
        default => (string) ($title ?? $platformName),
    };
}
$accountName = trim((string) ($displayName ?? '')) !== '' ? trim((string) $displayName) : $username;
$avatarMark = mb_strtoupper(mb_substr($accountName !== '' ? $accountName : $platformName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title ?? $platformName) ?> · <?= $escape($platformName) ?></title>
    <link rel="stylesheet" href="<?= $escape($assetVersion('assets/app.css')) ?>">
    <?php foreach (($pageStyles ?? []) as $pageStyle): ?>
        <link rel="stylesheet" href="<?= $escape($moduleAssetVersion($pageStyle)) ?>">
    <?php endforeach; ?>
</head>
<body class="theme-<?= $escape($theme) ?><?= $username === '' ? ' is-anonymous' : '' ?>">
<header class="topbar">
    <?php if ($username !== ''): ?>
        <button class="topbar-menu" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="展开或收起导航">☰</button>
    <?php endif; ?>
    <a class="brand" href="?page=projects"><span class="brand-mark" aria-hidden="true"><?= $escape($brandMark) ?></span><span class="brand-word"><?= $escape($platformName) ?></span></a>
    <?php if ($username !== ''): ?>
        <span class="topbar-divider" aria-hidden="true"></span>
        <?php
        $switcherEntries = is_array($projectDirectory ?? null) ? $projectDirectory : [];
        // Entry landing mirrors the home cards: first module the viewer may open.
        $switcherReturn = 'projects';
        foreach ([
            ['spaces', 'view_project_spaces'],
            ['materials', 'view_materials'],
            ['documents', 'view_issues'],
        ] as [$switcherPage, $switcherPermission]) {
            if (isset($db) && \ProjectO\Support::hasPermission($db, $switcherPermission)) {
                $switcherReturn = $switcherPage;
                break;
            }
        }
        ?>
        <?php if ($switcherEntries !== []): ?>
            <details class="project-switcher">
                <summary class="project-switch<?= $layoutProject === null ? ' is-empty' : '' ?>" title="切换项目">
                    <span class="project-switch-dot<?= $layoutProject !== null ? ' status-' . $escape((string) ($layoutProject['status'] ?? '')) : '' ?>" aria-hidden="true"></span>
                    <span class="project-switch-name"><?= $escape($layoutProject !== null ? (string) $layoutProject['project_name'] : '选择项目') ?></span>
                    <span class="project-switch-caret" aria-hidden="true">⌄</span>
                </summary>
                <div class="project-menu" role="menu" aria-label="切换项目">
                    <?php foreach ($switcherEntries as $switcherEntry): ?>
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="project.select">
                            <input type="hidden" name="project_id" value="<?= (int) $switcherEntry['project_id'] ?>">
                            <input type="hidden" name="return" value="<?= $escape($switcherReturn) ?>">
                            <button class="project-menu-item<?= $layoutProject !== null && (int) $switcherEntry['project_id'] === (int) $layoutProject['project_id'] ? ' is-current' : '' ?>" type="submit">
                                <span class="project-switch-dot status-<?= $escape((string) $switcherEntry['status']) ?>" aria-hidden="true"></span>
                                <span class="project-menu-name"><?= $escape((string) $switcherEntry['project_name']) ?></span>
                                <?php if ($layoutProject !== null && (int) $switcherEntry['project_id'] === (int) $layoutProject['project_id']): ?>
                                    <span class="project-menu-check" aria-hidden="true">✓</span>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <a class="project-menu-foot" href="?page=projects">管理项目列表 →</a>
                </div>
            </details>
        <?php else: ?>
            <a class="project-switch is-empty" href="?page=projects">
                <span class="project-switch-dot" aria-hidden="true"></span>
                <span class="project-switch-name">选择项目</span>
                <span class="project-switch-caret" aria-hidden="true">⌄</span>
            </a>
        <?php endif; ?>
        <div class="account">
            <span class="account-avatar" aria-hidden="true"><?= $escape($avatarMark) ?></span>
            <span class="account-name"><?= $escape($accountName) ?></span>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="button button-quiet" type="submit">退出</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<?php if ($username !== ''): ?>
<div class="app-shell">
    <aside class="app-sidebar" id="app-sidebar" data-sidebar>
        <nav class="sidebar-nav" aria-label="主导航">
            <?php foreach ($sidebarGroups as $groupKey => $groupLabel): ?>
                <?php if (empty($groupedSidebar[$groupKey])) { continue; } ?>
                <div class="sidebar-group">
                    <p class="sidebar-label"><?= $escape($groupLabel) ?></p>
                    <?php foreach ($groupedSidebar[$groupKey] as $sidebarItem): ?>
                        <?php $isSidebarCurrent = ($sidebarItem['page'] ?? '') === $sidebarActivePage; ?>
                        <a class="sidebar-item<?= $isSidebarCurrent ? ' is-active' : '' ?>" href="?page=<?= $escape((string) $sidebarItem['page']) ?>"<?= $isSidebarCurrent ? ' aria-current="page"' : '' ?>>
                            <span class="sidebar-icon" aria-hidden="true"><?= $escape((string) ($sidebarItem['icon'] ?? '·')) ?></span>
                            <span class="sidebar-text"><?= $escape((string) $sidebarItem['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$layoutProjectScoped): ?>
                <div class="sidebar-group">
                    <p class="sidebar-label">平台</p>
                    <a class="sidebar-item<?= $layoutPage === 'projects' ? ' is-active' : '' ?>" href="?page=projects"<?= $layoutPage === 'projects' ? ' aria-current="page"' : '' ?>>
                        <span class="sidebar-icon" aria-hidden="true">⌂</span>
                        <span class="sidebar-text">项目列表</span>
                    </a>
                </div>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <a class="sidebar-item<?= $layoutPage === 'profile' ? ' is-active' : '' ?>" href="?page=profile"<?= $layoutPage === 'profile' ? ' aria-current="page"' : '' ?>>
                <span class="sidebar-icon" aria-hidden="true">◉</span>
                <span class="sidebar-text">个人设置</span>
            </a>
            <button class="sidebar-item sidebar-collapse" type="button" data-sidebar-collapse aria-label="收起侧边栏">
                <span class="sidebar-icon" aria-hidden="true">«</span>
                <span class="sidebar-text">收起侧栏</span>
            </button>
        </div>
    </aside>
    <div class="sidebar-scrim" data-sidebar-scrim hidden></div>
    <main class="container">
        <p class="crumb">工作区 <span aria-hidden="true">›</span> <span><?= $escape($layoutCrumb) ?></span></p>
        <?php if (is_array($flash ?? null)): ?>
            <div class="notice notice-<?= $escape($flash['type'] ?? 'success') ?>" role="status">
                <?= $escape($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>
<?php else: ?>
<main class="container">
    <?php if (is_array($flash ?? null)): ?>
        <div class="notice notice-<?= $escape($flash['type'] ?? 'success') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</main>
<?php endif; ?>
<script src="<?= $escape($assetVersion('assets/app.js')) ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $pageScript): ?>
    <script src="<?= $escape($moduleAssetVersion($pageScript)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
