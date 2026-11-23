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
$db->exec('CREATE TABLE Material_Categories (category_id INTEGER PRIMARY KEY AUTOINCREMENT, category_name TEXT NOT NULL, parent_category_id INTEGER, sort_order INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE Materials (material_id INTEGER PRIMARY KEY, project_id INTEGER, material_code TEXT, material_name TEXT NOT NULL, unit TEXT NOT NULL, category_id INTEGER, default_quotation_id INTEGER, UNIQUE(project_id, material_code))');
$db->exec('CREATE TABLE Material_Compositions (composition_id INTEGER PRIMARY KEY, project_id INTEGER, parent_material_id INTEGER, child_material_id INTEGER, quantity NUMERIC NOT NULL, UNIQUE(project_id, parent_material_id, child_material_id), UNIQUE(project_id, composition_id))');
$db->exec('CREATE TABLE Auxiliary_Materials (aux_id INTEGER PRIMARY KEY, project_id INTEGER, aux_name TEXT NOT NULL, unit TEXT NOT NULL)');
$db->exec('CREATE TABLE Material_Quotations (quotation_id INTEGER PRIMARY KEY, project_id INTEGER, supplier_material_id INTEGER, material_id INTEGER, purchase_unit_price NUMERIC NOT NULL, quotation_date TEXT, currency_code TEXT, confirmed_tax_rate NUMERIC NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, valid_from TEXT, valid_to TEXT, status TEXT, tax_status TEXT, pricing_unit TEXT, UNIQUE(project_id, quotation_id))');
$db->exec('CREATE TABLE resource_catalog (catalog_id INTEGER PRIMARY KEY, catalog_type TEXT, name TEXT NOT NULL, unit TEXT NOT NULL, description TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(catalog_type, name))');
$db->exec('CREATE TABLE project_cost_prices (price_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, aux_id INTEGER, catalog_id INTEGER, unit_price NUMERIC NOT NULL, currency_code TEXT, pricing_unit TEXT, valid_from TEXT, valid_to TEXT, status TEXT, created_by INTEGER, UNIQUE(project_id, price_id))');
$db->exec('CREATE TABLE bom_costings (costing_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, parent_material_id INTEGER NOT NULL, status TEXT, calc_status TEXT, version BIGINT NOT NULL DEFAULT 0, confirmed_by INTEGER, confirmed_at TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(project_id, parent_material_id), UNIQUE(project_id, costing_id))');
$db->exec('CREATE TABLE bom_costing_resources (resource_line_id INTEGER PRIMARY KEY, costing_id INTEGER, project_id INTEGER, resource_type TEXT, aux_id INTEGER, catalog_id INTEGER, material_id INTEGER, resource_key TEXT, quantity NUMERIC NOT NULL, UNIQUE(project_id, resource_line_id))');
$db->exec('CREATE TABLE cost_line_results (result_id INTEGER PRIMARY KEY, costing_id INTEGER, project_id INTEGER, composition_id INTEGER, resource_line_id INTEGER, quantity NUMERIC NOT NULL, unit_price NUMERIC NOT NULL, currency_code TEXT, pricing_unit TEXT, quotation_id INTEGER, project_cost_price_id INTEGER, manual_price NUMERIC, manual_confirmed_by INTEGER, source_tax_status TEXT, mgmt_rate NUMERIC, profit_rate NUMERIC, tax_rate NUMERIC, trans_rate NUMERIC, ins_rate NUMERIC, subtotal NUMERIC, mgmt_amount NUMERIC, profit_amount NUMERIC, tax_amount NUMERIC, comprehensive NUMERIC, formula_version TEXT, rounding_spec_version TEXT, calculated_at TEXT, calculated_by INTEGER, UNIQUE(project_id, result_id), UNIQUE(costing_id, composition_id), UNIQUE(costing_id, resource_line_id))');
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

function renderPage(PDO $db, string $page, array $params = []): string
{
    $_GET = ['page' => $page] + $params;
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

// 资源目录编辑：每行带「编辑」按钮与预填对话框，提交 catalog_id 复用 cost.catalog.save 更新。
$db->exec("INSERT INTO resource_catalog (catalog_id, catalog_type, name, unit, description) VALUES (301, 'labor', '木工台班', '台班', '模板支设')");
$catalogHtml = renderPage($db, 'cost-catalog');
foreach ([
    'data-dialog-open="catalog-edit-301"',
    'id="catalog-edit-301"',
    'name="catalog_id" value="301"',
    'value="木工台班"',
    'value="台班"',
    '<option value="labor" selected>人工</option>',
    '>保存更改<',
] as $needle) {
    if (!str_contains($catalogHtml, $needle)) {
        throw new RuntimeException("资源目录行编辑缺少「{$needle}」。");
    }
}
echo "PASS  GET cost-catalog 行内编辑按钮与预填对话框渲染正常\n";

// 右栏成本预览冒烟：造一份“材料组成”组价，验证选中后真的渲染出环形占比图、
// 成本汇总（含占比）与成本明细，而不是只有空状态。
$db->exec("INSERT INTO Material_Categories (category_id, category_name, sort_order) VALUES (1, '灯具', 10)");
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id) VALUES
    (1, 1, 'DG-001', '筒灯', '套', 1), (2, 1, 'PZ-001', '灯珠', '个', 1)");
// 4 是已维护组成的定制项（可组价），5 是它的基本材料子件（不进父材料候选）。
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id) VALUES
    (4, 1, 'DG-002', '灯箱', '个', 1), (5, 1, 'DG-003', '灯布', '个', 1)");
$db->exec('INSERT INTO Material_Compositions (composition_id, project_id, parent_material_id, child_material_id, quantity) VALUES (1, 1, 1, 2, 3), (2, 1, 4, 5, 1)');
$db->exec("INSERT INTO bom_costings (costing_id, project_id, parent_material_id, status, calc_status, version) VALUES (1, 1, 1, 'draft', 'complete', 1)");
$db->exec("INSERT INTO cost_line_results (result_id, costing_id, project_id, composition_id, resource_line_id, quantity, unit_price, currency_code, pricing_unit, manual_price, source_tax_status)
    VALUES (1, 1, 1, 1, NULL, 3, 12.5, 'CNY', '个', 12.5, 'taxable')");

$previewHtml = renderPage($db, 'costings', ['costing_id' => 1]);
foreach (['cost-donut', '材料组成', '37.50', '100.00%', '成本明细'] as $needle) {
    if (!str_contains($previewHtml, $needle)) {
        throw new RuntimeException("组价列表右栏成本预览缺少「{$needle}」。");
    }
}
echo "PASS  GET costings?costing_id=1 右栏成本预览（环形图 + 汇总 + 明细）渲染正常\n";

// 新建组价的父材料必须挂共享搜索选择器：data-combo 外壳 + 文本搜索输入，
// 原生 select 仍以 name="material_id" 提交。旧实现只有裸 select。
foreach (['data-combo', 'data-combo-input', 'name="material_id"'] as $needle) {
    if (!str_contains($previewHtml, $needle)) {
        throw new RuntimeException("新建组价的父材料缺少搜索选择器「{$needle}」。");
    }
}
// 父材料候选只含定制项：灯箱（有组成）在，基本材料灯布（无子件）不在。
if (!str_contains($previewHtml, '灯箱')) {
    throw new RuntimeException('已维护组成的定制项应出现在新建组价候选。');
}
if (str_contains($previewHtml, '灯布')) {
    throw new RuntimeException('没有组成子件的基本材料不应出现在父材料候选。');
}
echo "PASS  新建组价的父材料选择带搜索组件\n";

// 追加一个尚未进入组成的材料，使「添加子材料」对话框渲染出候选下拉。
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id) VALUES (3, 1, 'PZ-002', '灯罩', '个', 1)");

$detailHtml = renderPage($db, 'costing-detail', ['costing_id' => 1]);
if (!str_contains($detailHtml, '成本预览') || !str_contains($detailHtml, '成本项小计占比')) {
    throw new RuntimeException('组价详情页缺少成本预览区块。');
}
foreach (['data-combo', 'data-combo-input', 'name="child_material_id"'] as $needle) {
    if (!str_contains($detailHtml, $needle)) {
        throw new RuntimeException("添加子材料对话框缺少搜索选择器「{$needle}」。");
    }
}
echo "PASS  GET costing-detail 详情页带成本预览区块\n";
echo "PASS  添加子材料对话框带搜索组件\n";

echo "BOM costing web smoke passed.\n";
