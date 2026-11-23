<?php
declare(strict_types=1);

// Web 入口冒烟：经真实 Application 路由渲染工程消项四个 GET 页面。
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
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, project_id INTEGER, entity_type TEXT, entity_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT)');
$db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY, project_id INTEGER, building_number TEXT, building_name TEXT)');
$db->exec('CREATE TABLE Material_Categories (category_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, category_name TEXT NOT NULL, parent_category_id INTEGER, sort_order INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE Materials (material_id INTEGER PRIMARY KEY, project_id INTEGER, material_code TEXT, material_name TEXT NOT NULL, unit TEXT NOT NULL, specifications TEXT, material_description_detailed TEXT, category_id INTEGER, default_quotation_id INTEGER, sample_required TEXT, UNIQUE(project_id, material_code))');
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
$db->exec('CREATE TABLE Suppliers (supplier_id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_name TEXT NOT NULL)');
$db->exec('CREATE TABLE document_number_sequences (project_id INTEGER NOT NULL, document_type TEXT NOT NULL, last_number INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (project_id, document_type))');
$db->exec('CREATE TABLE project_closeout_plans (
    plan_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_no TEXT NOT NULL, plan_name TEXT NOT NULL,
    description TEXT, status TEXT NOT NULL DEFAULT "draft", planned_start_date TEXT, planned_end_date TEXT,
    closed_at TEXT, closed_by INTEGER, reopened_at TEXT, reopened_by INTEGER, created_by INTEGER NOT NULL,
    created_at TEXT, updated_at TEXT
)');
$db->exec('CREATE TABLE project_closeout_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL, item_no TEXT NOT NULL,
    material_id INTEGER NOT NULL, coverage_mode TEXT NOT NULL DEFAULT "whole", coverage_root_id INTEGER,
    title TEXT NOT NULL, description TEXT, status TEXT NOT NULL DEFAULT "pending",
    responsible_supplier_id INTEGER, responsible_user_id INTEGER, planned_start_date TEXT, planned_end_date TEXT,
    submitted_at TEXT, actual_complete_date TEXT, risk_level TEXT NOT NULL DEFAULT "medium",
    cancel_reason TEXT, cancelled_by INTEGER, cancelled_at TEXT,
    created_by INTEGER NOT NULL, created_at TEXT, updated_at TEXT
)');
$db->exec('CREATE TABLE project_closeout_progress_logs (progress_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, plan_id INTEGER, item_id INTEGER, progress_type TEXT, progress_note TEXT, recorded_by INTEGER, recorded_at TEXT)');
$db->exec('CREATE TABLE project_closeout_acceptances (acceptance_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, plan_id INTEGER, item_id INTEGER, result TEXT, remark TEXT, evidence_reference TEXT, accepted_by INTEGER, accepted_at TEXT)');
$db->exec('CREATE TABLE project_closeout_images (image_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, item_id INTEGER, object_key TEXT, original_name TEXT, content_type TEXT, file_size INTEGER, uploaded_by INTEGER, created_at TEXT)');

$db->exec("INSERT INTO Projects (project_id, project_name, start_date, end_date, status) VALUES (1, '测试项目', '2026-03-01', '2026-12-31', 'ongoing')");
$db->exec("INSERT INTO Users (user_id, username, password_hash, country_code, is_active, timezone) VALUES (1, 'admin', 'hash', 'CN', 1, 'Asia/Shanghai')");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('theme','light'),('language','zh-CN'),('default_country_code','CN'),('default_currency_code','CNY'),('timezone','Asia/Shanghai')");
$db->exec("INSERT INTO Suppliers (supplier_id, supplier_name) VALUES (1, '甲供单位')");
$db->exec("INSERT INTO Material_Categories (category_id, project_id, category_name, sort_order) VALUES (1, 1, '门类', 10)");
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, specifications, material_description_detailed, category_id, sample_required) VALUES
    (101, 1, 'M-101', '成品门', '樘', 'M1526', '现场安装', 1, 'required'),
    (102, 1, 'M-102', '门框', '套', '定制', '门框收口', 1, 'required')");
$db->exec("INSERT INTO Material_Compositions (composition_id, project_id, parent_material_id, child_material_id, quantity) VALUES (1, 1, 101, 102, 1)");

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

$db->exec("INSERT INTO project_closeout_plans (plan_id, project_id, plan_no, plan_name, status, created_by, created_at, updated_at)
    VALUES (1, 1, 'P1-CLO-PLAN-001', '竣工验收消项计划', 'active', 1, '2026-11-22 09:00:00', '2026-11-22 09:00:00')");
$db->exec("INSERT INTO project_closeout_items (item_id, project_id, plan_id, item_no, material_id, title, status, risk_level, planned_end_date, created_by, created_at, updated_at)
    VALUES (1, 1, 1, 'P1-CLO-001', 101, '成品门收口', 'pending', 'high', '2026-11-30', 1, '2026-11-22 09:10:00', '2026-11-22 09:10:00')");
$db->exec("INSERT INTO project_closeout_progress_logs (project_id, plan_id, item_id, progress_type, progress_note, recorded_by, recorded_at)
    VALUES (1, 1, 1, 'note', '门框已到场', 1, '2026-11-22 09:20:00')");

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1, 'csrf_token' => 'web-smoke-token'];

function renderCloseoutPage(PDO $db, string $page, array $params = []): string
{
    $_GET = ['page' => $page] + $params;
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    (new Application($db))->run();
    return (string) ob_get_clean();
}

$pages = [
    'closeouts' => ['工程消项总览', ['工程消项']],
    'closeout-items' => ['消项清单', ['P1-CLO-001', '成品门收口']],
    'closeout-select' => ['从 BOM 纳入消项事项', ['成品门', '竣工验收消项计划', '已隐藏 1 项非父材料']],
    'closeout-item' => ['验收记录', ['P1-CLO-001', '附件照片', '成品门收口', '门框已到场', 'BOM 组成', '门框']],
];
foreach ($pages as $page => [$heading, $needles]) {
    $params = $page === 'closeout-select' ? ['plan_id' => 1] : ($page === 'closeout-item' ? ['item_id' => 1] : []);
    $html = renderCloseoutPage($db, $page, $params);
    if (str_contains($html, '没有访问权限') || str_contains($html, '页面不存在') || str_contains($html, '未找到对应操作')) {
        throw new RuntimeException("页面 {$page} 被权限或路由拦截，未正常渲染。");
    }
    if (!str_contains($html, $heading)) {
        throw new RuntimeException("页面 {$page} 缺少标题「{$heading}」。");
    }
    if (!str_contains($html, 'app-sidebar') || !str_contains($html, '<html')) {
        throw new RuntimeException("页面 {$page} 未套全局布局（缺侧边栏/HTML 外壳）。");
    }
    foreach ($needles as $needle) {
        if (!str_contains($html, $needle)) {
            throw new RuntimeException("页面 {$page} 缺少「{$needle}」。");
        }
    }
    if ($page === 'closeout-select' && str_contains($html, '门框')) {
        throw new RuntimeException('选择页不应列出子材料门框。');
    }
    if ($page === 'closeout-item' && str_contains($html, '纳入时 BOM 快照')) {
        throw new RuntimeException('详情页不应再显示 BOM 快照。');
    }
    echo "PASS  GET {$page} 渲染正常（{$heading}）\n";
}

$html = renderCloseoutPage($db, 'closeouts');
foreach ([
    'name="planned_start_date" value="2026-03-01"',
    'name="planned_end_date" value="2026-12-31"',
] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException("新建计划弹窗未按项目周期预填「{$needle}」。");
    }
}
echo "PASS  新建消项计划默认带出项目开始/结束日期\n";

// 存储复用：未设置 APP_OBJECT_STORAGE_ADAPTER 时，消项必须自动读取文件模块
// 「系统设置」里的 cloud_storage.* 配置（与问题管理、样品管理同源）。
$html = renderCloseoutPage($db, 'closeout-item', ['item_id' => 1]);
if (!str_contains($html, 'R2 图片存储尚未配置')) {
    throw new RuntimeException('未配置 R2 时，消项详情页缺少存储未配置提示。');
}
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES
    ('cloud_storage.enabled', '1'),
    ('cloud_storage.r2_account_id', 'd8f4d2fa4b3c4d5e8f9012345678abcd'),
    ('cloud_storage.bucket_name', 'project-o-images'),
    ('cloud_storage.access_key_id', 'r2-access-key-id-example'),
    ('cloud_storage.secret_access_key', 'r2-secret-access-key-example')");
$db->exec("INSERT INTO project_closeout_images (image_id, project_id, item_id, object_key, original_name, content_type, file_size, uploaded_by, created_at)
    VALUES (1, 1, 1, 'closeouts/1/1/abc.png', '现场收口照片.png', 'image/png', 1024, 1, '2026-11-22 09:30:00')");
$html = renderCloseoutPage($db, 'closeout-item', ['item_id' => 1]);
foreach (['?page=closeout-image&amp;item_id=1&amp;image_id=1', '现场收口照片.png', 'closeout.image.upload'] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException("配置 R2 后，消项详情页缺少「{$needle}」。");
    }
}
if (str_contains($html, 'r2-secret-access-key-example') || str_contains($html, 'R2 图片存储尚未配置')) {
    throw new RuntimeException('配置 R2 后附件区仍显示未配置，或页面泄露了密钥。');
}
$db->exec("DELETE FROM System_Settings WHERE setting_key LIKE 'cloud_storage.%'");
echo "PASS  消项附件复用系统 R2 图片存储（未配置/已配置两种状态）\n";

$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (2, '无组成项目', 'ongoing')");
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, specifications, category_id) VALUES
    (201, 2, 'M-201', '独立灯具', '套', 'LED', NULL),
    (202, 2, 'M-202', '独立开关', '个', '86 型', NULL)");
$db->exec("INSERT INTO project_closeout_plans (plan_id, project_id, plan_no, plan_name, status, created_by, created_at, updated_at)
    VALUES (2, 2, 'P2-CLO-PLAN-001', '无组成项目消项计划', 'active', 1, '2026-11-22 09:00:00', '2026-11-22 09:00:00')");
$_SESSION['active_project_id'] = 2;
$html = renderCloseoutPage($db, 'closeout-select', ['plan_id' => 2]);
foreach (['当前项目 BOM 中没有父材料', '全部顶层材料均可纳入', '独立灯具', '独立开关'] as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException("回退页缺少「{$needle}」。");
    }
}
if (str_contains($html, '已隐藏')) {
    throw new RuntimeException('无组成关系项目不应显示隐藏材料计数。');
}
echo "PASS  GET closeout-select 渲染正常（无父材料项目回退顶层材料）\n";

echo "Closeout web smoke passed.\n";
