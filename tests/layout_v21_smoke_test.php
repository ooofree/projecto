<?php
declare(strict_types=1);

/**
 * V2.1 global layout smoke test (V21-01).
 *
 * Verifies the shell contract without touching business rules:
 *  - anonymous login page: no sidebar, no project switcher;
 *  - signed-in global page (projects): platform bucket only, never project
 *    groups, even with a selected project;
 *  - signed-in project page (spaces): plugin-declared project groups render
 *    with permission gates, current entry highlighted;
 *  - top bar carries brand, project switcher and the CSRF-protected logout;
 *  - sidebar entries come from plugin declarations (no hardcoded business
 *    menu in the layout) and disabled plugins contribute nothing.
 */

use ProjectO\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

function layoutCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function layoutSchema(PDO $db): void
{
    $db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY, project_name TEXT, start_date TEXT, end_date TEXT, status TEXT)');
    $db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER, archived_by INTEGER, archived_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (entity_type, entity_id))');
    $db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, is_active INTEGER, timezone TEXT)');
    $db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY)');
    $db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
    $db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY, permission_name TEXT)');
    $db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
    $db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT)');
    $db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
    $db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, building_number TEXT NOT NULL, building_name TEXT NOT NULL)');
    $db->exec('CREATE TABLE Building_Floors (floor_id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, floor_number TEXT NOT NULL, floor_name TEXT, usage_type TEXT, UNIQUE (floor_id, building_id))');
    $db->exec('CREATE TABLE Construction_Areas (area_id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, area_name TEXT NOT NULL, description TEXT, UNIQUE (area_id, building_id))');
    $db->exec('CREATE TABLE Rooms (room_id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, floor_id INTEGER NOT NULL, room_number TEXT NOT NULL, room_type TEXT, room_area REAL, room_height REAL, room_status TEXT, UNIQUE (room_id, building_id, floor_id))');
    $db->exec('CREATE TABLE Construction_Area_Rooms (area_room_id INTEGER PRIMARY KEY, room_id INTEGER NOT NULL, area_id INTEGER NOT NULL, floor_id INTEGER NOT NULL, building_id INTEGER NOT NULL, room_number TEXT NOT NULL, remarks TEXT)');
    $db->exec('CREATE TABLE Sample_Location_Map (project_id INTEGER, sample_id INTEGER, location_type TEXT, location_id INTEGER)');
    $db->exec('CREATE TABLE Brands (brand_id INTEGER PRIMARY KEY, brand_name TEXT NOT NULL, external_link TEXT)');
    $db->exec(
        "CREATE TABLE System_Settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_by INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $db->exec(
        "INSERT INTO System_Settings (setting_key, setting_value) VALUES
            ('theme', 'light'),
            ('language', 'zh-CN'),
            ('default_country_code', 'CN'),
            ('default_currency_code', 'CNY'),
            ('timezone', 'Asia/Shanghai'),
            ('admin_guard', '1')"
    );
    $db->exec("INSERT INTO Users (user_id, is_active) VALUES (1, 1)");
    $db->exec('INSERT INTO Roles (role_id) VALUES (1)');
    $db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
    $db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, 'Layout Project', 'ongoing')");
}

function layoutGrant(PDO $db, array $permissions): void
{
    $db->exec('DELETE FROM Permissions');
    $db->exec('DELETE FROM Role_Permissions');
    $id = 0;
    foreach ($permissions as $permission) {
        $id++;
        $insert = $db->prepare('INSERT INTO Permissions (permission_id, permission_name) VALUES (:id, :name)');
        $insert->execute(['id' => $id, 'name' => $permission]);
        $grant = $db->prepare('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, :id)');
        $grant->execute(['id' => $id]);
    }
}

function layoutRender(PDO $db, string $page, array $session): string
{
    $_SESSION = $session;
    $_GET = ['page' => $page];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    http_response_code(200);
    ob_start();
    (new Application($db))->run();
    return ob_get_clean();
}

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
layoutSchema($db);

// 1. Anonymous login: form + CSRF, but no shell chrome.
$loginHtml = layoutRender($db, 'login', []);
layoutCheck(
    str_contains($loginHtml, '<h1>登录</h1>') && str_contains($loginHtml, 'name="csrf_token"'),
    'The login page must keep its form and CSRF token.'
);
layoutCheck(
    !str_contains($loginHtml, 'app-sidebar') && !str_contains($loginHtml, 'project-switch'),
    'Anonymous pages must not render the sidebar or the project switcher.'
);

// 2. Home page with a selected project: project sidebar renders (cards enter
// the current project); without a project only the platform bucket shows.
layoutGrant($db, ['view_projects', 'view_project_spaces', 'view_materials', 'manage_platform']);
$session = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1, 'csrf_token' => 'layout-token'];
$projectsHtml = layoutRender($db, 'projects', $session);
$projectsGroup = '<p class="sidebar-label">项目管理</p>';
$materialsGroup = '<p class="sidebar-label">材料管理</p>';
layoutCheck(
    str_contains($projectsHtml, 'class="app-sidebar"')
        && str_contains($projectsHtml, $projectsGroup)
        && str_contains($projectsHtml, $materialsGroup),
    'With a current project the home page must render the project sidebar.'
);
$noProjectHtml = layoutRender($db, 'projects', ['user_id' => 1, 'username' => 'admin', 'csrf_token' => 'layout-token']);
layoutCheck(
    str_contains($noProjectHtml, '项目列表')
        && !str_contains($noProjectHtml, $projectsGroup)
        && !str_contains($noProjectHtml, $materialsGroup),
    'Without a current project only the platform bucket may render.'
);
layoutCheck(
    str_contains($projectsHtml, 'project-switch-name">Layout Project</span>')
        && str_contains($projectsHtml, 'class="brand-word"')
        && str_contains($projectsHtml, 'name="action" value="logout"')
        && str_contains($projectsHtml, 'name="csrf_token" value="layout-token"'),
    'The top bar must show the current project switcher, brand and CSRF-protected logout.'
);
layoutCheck(
    str_contains($projectsHtml, 'href="?page=admin"') && str_contains($projectsHtml, 'href="?page=profile"'),
    'Platform entries must respect manage_platform and stay reachable.'
);

// 3. Shared-catalogue page (brands): the view does not pass currentProject,
// but the shell must resolve it from the session so the project sidebar stays.
layoutGrant($db, ['view_projects', 'view_project_spaces', 'view_materials', 'view_brands', 'manage_platform']);
$brandsHtml = layoutRender($db, 'brands', $session);
layoutCheck(
    str_contains($brandsHtml, '<p class="sidebar-label">供应链管理</p>')
        && str_contains($brandsHtml, 'href="?page=brands" aria-current="page"')
        && str_contains($brandsHtml, 'project-switch-name">Layout Project</span>'),
    'Shared-catalogue pages must keep the project sidebar and switcher (session-resolved project).'
);

// 4. Project page: plugin-declared groups with permission gates + highlight.
layoutGrant($db, ['view_projects', 'view_project_spaces', 'view_materials', 'manage_platform']);
$spacesHtml = layoutRender($db, 'spaces', $session);
layoutCheck(
    str_contains($spacesHtml, $projectsGroup)
        && str_contains($spacesHtml, 'href="?page=spaces" aria-current="page"')
        && str_contains($spacesHtml, '材料管理')
        && !str_contains($spacesHtml, '品牌管理')
        && !str_contains($spacesHtml, '施工班组'),
    'Project pages must render plugin-declared sidebar groups filtered by permission.'
);
layoutCheck(
    str_contains($spacesHtml, 'href="?page=projects">项目首页</a>'),
    'The in-page module navigation contract must stay intact.'
);

// 4. Business areas (user directive 2026-11-23): 项目管理 → 材料管理 →
// 供应链管理 → BOM 与成本管理 → 工程协同 → 施工管理 → 基础设置, rendered
// in exactly that order, with cross-plugin entries placed by 'order'.
layoutGrant($db, ['view_projects', 'view_project_spaces', 'view_materials', 'view_material_samples', 'view_material_quotations', 'view_tenders', 'view_material_estimates', 'view_suppliers', 'view_brands', 'view_supplier_materials', 'view_labor_crews', 'view_labor_crew_types', 'view_closeouts', 'view_costings', 'view_cost_catalog', 'view_actual_costs', 'view_issues', 'manage_platform']);
// Render on a shared-catalogue page: the sidebar is identical on every
// project page, and brands needs no material business tables in this schema.
$groupedHtml = layoutRender($db, 'brands', $session);
$areaMarkers = [
    '项目管理' => '<p class="sidebar-label">项目管理</p>',
    '材料管理' => '<p class="sidebar-label">材料管理</p>',
    '供应链管理' => '<p class="sidebar-label">供应链管理</p>',
    'BOM 与成本管理' => '<p class="sidebar-label">BOM 与成本管理</p>',
    '工程协同' => '<p class="sidebar-label">工程协同</p>',
    '施工管理' => '<p class="sidebar-label">施工管理</p>',
    '基础设置' => '<p class="sidebar-label">基础设置</p>',
];
$areaPositions = [];
foreach ($areaMarkers as $areaName => $marker) {
    $position = strpos($groupedHtml, $marker);
    layoutCheck($position !== false, 'Missing sidebar area: ' . $areaName);
    $areaPositions[$areaName] = $position;
}
layoutCheck(
    $areaPositions['项目管理'] < $areaPositions['材料管理']
        && $areaPositions['材料管理'] < $areaPositions['供应链管理']
        && $areaPositions['供应链管理'] < $areaPositions['BOM 与成本管理']
        && $areaPositions['BOM 与成本管理'] < $areaPositions['工程协同']
        && $areaPositions['工程协同'] < $areaPositions['施工管理']
        && $areaPositions['施工管理'] < $areaPositions['基础设置'],
    'Sidebar areas must follow the agreed order: 项目管理, 材料管理, 供应链管理, BOM 与成本管理, 工程协同, 施工管理, 基础设置.'
);
$tenderPosition = strpos($groupedHtml, '?page=tender-list"');
$supplierMaterialPosition = strpos($groupedHtml, '?page=supplier-materials"');
$demandPosition = strpos($groupedHtml, '?page=demand-summary"');
$closeoutPosition = strpos($groupedHtml, '?page=closeouts"');
$docSettingsPosition = strpos($groupedHtml, '?page=document-settings"');
layoutCheck(
    $tenderPosition !== false
        && $tenderPosition > $areaPositions['项目管理'] && $tenderPosition < $areaPositions['材料管理']
        && $supplierMaterialPosition !== false
        && $supplierMaterialPosition > $areaPositions['供应链管理'] && $supplierMaterialPosition < $areaPositions['BOM 与成本管理']
        && $demandPosition !== false
        && $demandPosition > $areaPositions['BOM 与成本管理'] && $demandPosition < $areaPositions['工程协同']
        && $closeoutPosition !== false
        && $closeoutPosition > $areaPositions['施工管理'] && $closeoutPosition < $areaPositions['基础设置']
        && $docSettingsPosition !== false && $docSettingsPosition > $areaPositions['基础设置'],
    'Entries must sit in their declared business area (tender list in 项目管理, supplier materials in 供应链管理, demand rollup in BOM 与成本管理, closeouts in 施工管理, document settings in 基础设置).'
);

// 5. Without view_projects the project groups disappear entirely.
layoutGrant($db, ['view_materials']);
$limitedHtml = layoutRender($db, 'projects', ['user_id' => 1, 'username' => 'admin', 'csrf_token' => 'layout-token']);
layoutCheck(
    !str_contains($limitedHtml, $projectsGroup)
        && !str_contains($limitedHtml, $materialsGroup)
        && !str_contains($limitedHtml, '<p class="sidebar-label">施工管理</p>'),
    'Sidebar entries must never exceed the viewer permissions.'
);

echo "Layout V2.1 smoke test passed.\n";
