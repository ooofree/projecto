<?php
declare(strict_types=1);

// Web 入口冒烟：经真实 Application 路由渲染 BOM 组价/目录/实际费用三个 GET 页面。
// 内存 SQLite 底座镜像关键表结构；不连生产库。
use ProjectO\Application;
use ProjectO\Plugin\PluginRegistry;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY, project_name TEXT, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER)');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, password_hash TEXT, country_code TEXT, is_active INTEGER, timezone TEXT)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY, role_name TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, permission_name TEXT UNIQUE, display_name TEXT, description TEXT, module TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
$db->exec('CREATE TABLE System_Settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT)');
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, project_id INTEGER, entity_type TEXT, entity_id INTEGER, action TEXT, details TEXT)');
$db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY, project_id INTEGER, building_number TEXT, building_name TEXT)');
$db->exec('CREATE TABLE Materials (material_id INTEGER PRIMARY KEY, project_id INTEGER, material_code TEXT, material_name TEXT NOT NULL, unit TEXT NOT NULL, default_quotation_id INTEGER, UNIQUE(project_id, material_code))');
$db->exec('CREATE TABLE Material_Compositions (composition_id INTEGER PRIMARY KEY, project_id INTEGER, parent_material_id INTEGER, child_material_id INTEGER, quantity NUMERIC NOT NULL, UNIQUE(project_id, parent_material_id, child_material_id), UNIQUE(project_id, composition_id))');
$db->exec('CREATE TABLE Auxiliary_Materials (aux_id INTEGER PRIMARY KEY, project_id INTEGER, aux_name TEXT NOT NULL, unit TEXT NOT NULL)');
$db->exec('CREATE TABLE Material_Quotations (quotation_id INTEGER PRIMARY KEY, project_id INTEGER, supplier_material_id INTEGER, material_id INTEGER, purchase_unit_price NUMERIC NOT NULL, quotation_date TEXT, currency_code TEXT, confirmed_tax_rate NUMERIC NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, valid_from TEXT, valid_to TEXT, status TEXT, tax_status TEXT, pricing_unit TEXT, UNIQUE(project_id, quotation_id))');
$db->exec('CREATE TABLE resource_catalog (catalog_id INTEGER PRIMARY KEY, catalog_type TEXT, name TEXT NOT NULL, unit TEXT NOT NULL, description TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(catalog_type, name))');
$db->exec('CREATE TABLE project_cost_prices (price_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, aux_id INTEGER, catalog_id INTEGER, unit_price NUMERIC NOT NULL, currency_code TEXT, pricing_unit TEXT, valid_from TEXT, valid_to TEXT, status TEXT, created_by INTEGER, UNIQUE(project_id, price_id))');
$db->exec('CREATE TABLE bom_costings (costing_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, parent_material_id INTEGER NOT NULL, status TEXT, calc_status TEXT, version BIGINT NOT NULL DEFAULT 0, confirmed_by INTEGER, confirmed_at TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(project_id, parent_material_id), UNIQUE(project_id, costing_id))');
$db->exec('CREATE TABLE bom_costing_resources (resource_line_id INTEGER PRIMARY KEY, costing_id INTEGER, project_id INTEGER, resource_type TEXT, aux_id INTEGER, catalog_id INTEGER, material_id INTEGER, resource_key TEXT, quantity NUMERIC NOT NULL, UNIQUE(project_id, resource_line_id))');
$db->exec('CREATE TABLE cost_line_results (result_id INTEGER PRIMARY KEY, costing_id INTEGER, project_id INTEGER, composition_id INTEGER, resource_line_id INTEGER, quantity NUMERIC NOT NULL, unit_price NUMERIC NOT NULL, currency_code TEXT, pricing_unit TEXT, quotation_id INTEGER, project_cost_price_id INTEGER, manual_price NUMERIC, source_tax_status TEXT, UNIQUE(project_id, result_id), UNIQUE(costing_id, composition_id), UNIQUE(costing_id, resource_line_id))');
$db->exec('CREATE TABLE cost_settings (setting_id INTEGER PRIMARY KEY, project_id INTEGER, scope_type TEXT, building_id INTEGER, building_key TEXT, mgmt_rate NUMERIC DEFAULT 0, profit_rate NUMERIC DEFAULT 0, tax_rate NUMERIC DEFAULT 0, trans_rate NUMERIC DEFAULT 0, ins_rate NUMERIC DEFAULT 0, UNIQUE(project_id, building_key))');
$db->exec('CREATE TABLE actual_cost_records (record_id INTEGER PRIMARY KEY, project_id INTEGER, building_id INTEGER, cost_category TEXT, material_id INTEGER, aux_id INTEGER, catalog_id INTEGER, cost_object_key TEXT, description TEXT, quantity NUMERIC, unit TEXT, unit_price NUMERIC, amount NUMERIC NOT NULL, record_type TEXT, reversal_of_record_id INTEGER, adjustment_of_record_id INTEGER, currency_code TEXT, tax_status TEXT, occurred_at TEXT, recorded_by INTEGER, UNIQUE(project_id, record_id))');
$db->exec('CREATE TABLE actual_cost_allocations (allocation_id INTEGER PRIMARY KEY, project_id INTEGER, record_id INTEGER, costing_id INTEGER, allocated_amount NUMERIC NOT NULL, allocated_by INTEGER, UNIQUE(record_id, costing_id))');

$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, '测试项目', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, password_hash, country_code, is_active) VALUES (1, 'admin', 'hash', 'CN', 1)");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('theme','light'),('language','zh-CN'),('default_country_code','CN'),('default_currency_code','CNY'),('timezone','Asia/Shanghai')");

// 注册全部插件权限并授予角色 1（含 core 的 view_projects）。
$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$permIns = $db->prepare('INSERT INTO Permissions (permission_name, display_name, description, module) VALUES (:n, :d, :desc, :m)');
$roleIns = $db->prepare('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, :pid)');
$granted = [];
foreach ($registry->permissions() as $p) {
    $permIns->execute(['n' => $p['name'], 'd' => $p['display_name'], 'desc' => $p['description'] ?? '', 'm' => $p['module'] ?? '']);
    $roleIns->execute(['pid' => (int) $db->lastInsertId()]);
    $granted[] = $p['name'];
}
if (!in_array('view_projects', $granted, true)) {
    $permIns->execute(['n' => 'view_projects', 'd' => '查看项目', 'desc' => '', 'm' => 'project.foundation']);
    $roleIns->execute(['pid' => (int) $db->lastInsertId()]);
}

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1, 'csrf_token' => 'web-smoke-token'];

function renderPage(PDO $db, string $page): string
{
    $_GET = ['page' => $page];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    (new Application($db))->run();
    return (string) ob_get_clean();
}

$pages = [
    'costings' => 'BOM 组价',
    'cost-prices' => '项目成本价',
    'cost-catalog' => '资源目录',
    'actual-costs' => '实际费用',
];
foreach ($pages as $page => $heading) {
    $html = renderPage($db, $page);
    if (str_contains($html, '没有访问权限') || str_contains($html, '页面不存在')) {
        throw new RuntimeException("页面 $page 被权限/不存在拦截，未正常渲染。");
    }
    if (!str_contains($html, $heading)) {
        throw new RuntimeException("页面 {$page} 缺少标题「{$heading}」。");
    }
    // 布局外壳必须存在：顶栏/侧边栏由 layout.php 输出；裸片段不视为正常页面。
    if (!str_contains($html, 'app-sidebar') || !str_contains($html, '<html')) {
        throw new RuntimeException("页面 $page 未套全局布局（缺侧边栏/HTML 外壳）。");
    }
    echo 'PASS  GET ' . $page . ' 渲染正常（' . $heading . "，布局完整）\n";
}

echo "BOM costing web smoke passed.\n";
