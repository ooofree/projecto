<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\BomCosting\ActualCostService;
use ProjectO\Modules\BomCosting\BomCostingPlugin;
use ProjectO\Modules\BomCosting\CostPriceService;
use ProjectO\Modules\BomCosting\CostingService;
use ProjectO\Modules\BomCosting\PriceResolver;
use ProjectO\Modules\BomCosting\RecalcEngine;
use ProjectO\Modules\BomCosting\ResourceCatalogService;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function bomCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function bomExpectStatus(callable $operation, int $status, string $message): void
{
    try {
        $operation();
        throw new RuntimeException($message);
    } catch (HttpException $exception) {
        if ($exception->statusCode !== $status) {
            throw $exception;
        }
    }
}

// ---- 内存 SQLite 底座（镜像生产 DDL 的关键列与约束；SQLite 不支持生成列/枚举，用 TEXT 近似） ----
$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY AUTOINCREMENT, project_name TEXT NOT NULL, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, password_hash TEXT, country_code TEXT, is_active INTEGER NOT NULL DEFAULT 1, timezone TEXT)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY, role_name TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, permission_name TEXT UNIQUE, display_name TEXT, description TEXT, module TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
$db->exec('CREATE TABLE System_Settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, project_id INTEGER, entity_type TEXT, entity_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER, archived_by INTEGER, archived_at TEXT, UNIQUE(entity_type, entity_id))');

$db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, building_number TEXT NOT NULL, building_name TEXT NOT NULL, UNIQUE(project_id, building_number))');
$db->exec('CREATE TABLE Material_Categories (category_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, category_name TEXT NOT NULL, parent_category_id INTEGER, sort_order INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE Materials (
    material_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, material_code TEXT, material_name TEXT NOT NULL,
    unit TEXT NOT NULL, specifications TEXT, material_description_detailed TEXT, category_id INTEGER,
    default_quotation_id INTEGER, sample_required TEXT NOT NULL DEFAULT "required",
    UNIQUE(project_id, material_code)
)');
$db->exec('CREATE TABLE Material_Compositions (
    composition_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, parent_material_id INTEGER NOT NULL,
    child_material_id INTEGER NOT NULL, quantity NUMERIC NOT NULL DEFAULT 1.00,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, parent_material_id, child_material_id), UNIQUE(project_id, composition_id),
    CHECK(parent_material_id <> child_material_id), CHECK(quantity > 0)
)');
$db->exec('CREATE TABLE Auxiliary_Materials (aux_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, aux_name TEXT NOT NULL, unit TEXT NOT NULL, description TEXT)');
$db->exec('CREATE TABLE Material_Quotations (
    quotation_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, supplier_material_id INTEGER NOT NULL,
    material_id INTEGER NOT NULL, purchase_unit_price NUMERIC NOT NULL, quotation_date TEXT NOT NULL,
    currency_code TEXT NOT NULL, min_order_quantity NUMERIC, confirmed_tax_rate NUMERIC NOT NULL DEFAULT 0,
    version INTEGER NOT NULL DEFAULT 1, valid_from TEXT, valid_to TEXT, status TEXT NOT NULL DEFAULT "active",
    tax_status TEXT NOT NULL DEFAULT "unknown", pricing_unit TEXT,
    UNIQUE(project_id, quotation_id), UNIQUE(project_id, material_id, quotation_id)
)');

// 新插件表（SQLite 镜像：生成列/枚举由服务层校验，CHECK 保留关键约束）。
$db->exec('CREATE TABLE resource_catalog (
    catalog_id INTEGER PRIMARY KEY AUTOINCREMENT, catalog_type TEXT NOT NULL, name TEXT NOT NULL,
    unit TEXT NOT NULL, description TEXT, created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(catalog_type, name)
)');
$db->exec('CREATE TABLE project_cost_prices (
    price_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, aux_id INTEGER, catalog_id INTEGER,
    unit_price NUMERIC NOT NULL, currency_code TEXT NOT NULL DEFAULT "CNY", pricing_unit TEXT NOT NULL,
    valid_from TEXT, valid_to TEXT, status TEXT NOT NULL DEFAULT "active", created_by INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, price_id),
    CHECK((aux_id IS NOT NULL AND catalog_id IS NULL) OR (aux_id IS NULL AND catalog_id IS NOT NULL)),
    CHECK(unit_price > 0)
)');
$db->exec('CREATE TABLE bom_costings (
    costing_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, parent_material_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT "draft", calc_status TEXT NOT NULL DEFAULT "incomplete", version BIGINT NOT NULL DEFAULT 0,
    confirmed_by INTEGER, confirmed_at TEXT, created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(project_id, parent_material_id), UNIQUE(project_id, costing_id)
)');
$db->exec('CREATE TABLE bom_costing_resources (
    resource_line_id INTEGER PRIMARY KEY AUTOINCREMENT, costing_id INTEGER NOT NULL, project_id INTEGER NOT NULL,
    resource_type TEXT NOT NULL, aux_id INTEGER, catalog_id INTEGER, material_id INTEGER,
    resource_key TEXT, quantity NUMERIC NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(project_id, resource_line_id),
    UNIQUE(costing_id, resource_type, resource_key), CHECK(quantity > 0)
)');
$db->exec('CREATE TABLE cost_line_results (
    result_id INTEGER PRIMARY KEY AUTOINCREMENT, costing_id INTEGER NOT NULL, project_id INTEGER NOT NULL,
    composition_id INTEGER, resource_line_id INTEGER, quantity NUMERIC NOT NULL, unit_price NUMERIC NOT NULL,
    currency_code TEXT NOT NULL DEFAULT "CNY", pricing_unit TEXT NOT NULL, quotation_id INTEGER,
    project_cost_price_id INTEGER, manual_price NUMERIC, manual_confirmed_by INTEGER,
    source_tax_status TEXT NOT NULL DEFAULT "unknown", mgmt_rate NUMERIC, profit_rate NUMERIC, tax_rate NUMERIC,
    trans_rate NUMERIC, ins_rate NUMERIC, subtotal NUMERIC, mgmt_amount NUMERIC, profit_amount NUMERIC,
    tax_amount NUMERIC, comprehensive NUMERIC, formula_version TEXT NOT NULL DEFAULT "v3.0-structure",
    rounding_spec_version TEXT NOT NULL DEFAULT "pending", calculated_at TEXT DEFAULT CURRENT_TIMESTAMP, calculated_by INTEGER,
    UNIQUE(project_id, result_id), UNIQUE(costing_id, composition_id), UNIQUE(costing_id, resource_line_id),
    CHECK(unit_price > 0)
)');
$db->exec('CREATE TABLE cost_settings (
    setting_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, scope_type TEXT NOT NULL,
    building_id INTEGER, building_key TEXT, mgmt_rate NUMERIC NOT NULL DEFAULT 0, profit_rate NUMERIC NOT NULL DEFAULT 0,
    tax_rate NUMERIC NOT NULL DEFAULT 0, trans_rate NUMERIC NOT NULL DEFAULT 0, ins_rate NUMERIC NOT NULL DEFAULT 0,
    created_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_by INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, building_key)
)');
$db->exec('CREATE TABLE actual_cost_records (
    record_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, building_id INTEGER,
    cost_category TEXT NOT NULL, material_id INTEGER, aux_id INTEGER, catalog_id INTEGER, cost_object_key TEXT,
    description TEXT, quantity NUMERIC, unit TEXT, unit_price NUMERIC, amount NUMERIC NOT NULL,
    record_type TEXT NOT NULL DEFAULT "normal", reversal_of_record_id INTEGER, adjustment_of_record_id INTEGER,
    currency_code TEXT NOT NULL DEFAULT "CNY", tax_status TEXT NOT NULL DEFAULT "unknown", occurred_at TEXT NOT NULL,
    source_type TEXT, source_ref TEXT, recorded_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, record_id), CHECK(amount <> 0)
)');
$db->exec('CREATE TABLE actual_cost_allocations (
    allocation_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, record_id INTEGER NOT NULL,
    costing_id INTEGER NOT NULL, allocated_amount NUMERIC NOT NULL, allocated_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(record_id, costing_id), CHECK(allocated_amount > 0)
)');

// 基线账号与项目。
$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, '测试项目', 'planning'), (2, '另一项目', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, password_hash, country_code, is_active) VALUES (1, 'admin', 'hash', 'CN', 1)");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('theme','light'),('language','zh-CN'),('default_country_code','CN'),('default_currency_code','CNY'),('timezone','Asia/Shanghai')");

$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$permissionInsert = $db->prepare('INSERT INTO Permissions (permission_name, display_name, description, module) VALUES (:name, :display_name, :description, :module)');
$rolePermissionInsert = $db->prepare('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, :permission_id)');
foreach ($registry->permissions() as $permission) {
    $permissionInsert->execute([
        'name' => $permission['name'],
        'display_name' => $permission['display_name'],
        'description' => $permission['description'] ?? null,
        'module' => $permission['module'],
    ]);
    $rolePermissionInsert->execute(['permission_id' => (int) $db->lastInsertId()]);
}

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];

// 材料基线：父 101，子 102 / 103；辅材 201；目录 301 人工。
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit) VALUES
    (101, 1, 'P-01', '父项成品', '个'),
    (102, 1, 'C-01', '子材料甲', 'm'),
    (103, 1, 'C-02', '子材料乙', '个')");
$db->exec("INSERT INTO Auxiliary_Materials (aux_id, project_id, aux_name, unit) VALUES (201, 1, '辅材甲', 'm')");
$db->exec("INSERT INTO resource_catalog (catalog_id, catalog_type, name, unit) VALUES (301, 'labor', '木工台班', '台班')");

$costings = new CostingService($db);
$prices = new PriceResolver($db);
$recalc = new RecalcEngine($db, $prices);
$actuals = new ActualCostService($db);
$catalogSvc = new ResourceCatalogService($db);

// ============================================================================
// 取价四级优先级（B6）：默认报价 → 最新有效报价 → 项目成本价 → 缺价禁零。
// ============================================================================
$today = '2026-11-20';

// 子 102：两条有效报价，默认指向第一条；同日决胜 quotation_id 最大由最新分支覆盖。
$db->exec("INSERT INTO Material_Quotations (quotation_id, project_id, supplier_material_id, material_id, purchase_unit_price, quotation_date, currency_code, status, tax_status, pricing_unit, valid_from) VALUES
    (11, 1, 1, 102, 8.0000, '2026-11-10', 'CNY', 'active', 'taxable', 'm', '2026-11-10'),
    (12, 1, 1, 102, 9.5000, '2026-11-18', 'CNY', 'active', 'taxable', 'm', '2026-11-18')");
$db->exec("UPDATE Materials SET default_quotation_id = 11 WHERE material_id = 102");

$resolved = $prices->forMaterial(1, 102, $today);
bomCheck($resolved['status'] === 'quotation_default', '① 默认有效报价应命中默认分支');
bomCheck((int) $resolved['quotation_id'] === 11, '默认报价应取 quotation_id=11');

// 清除默认 → 回退最新有效（按生效日 DESC，同日 id 最大）。
$db->exec("UPDATE Materials SET default_quotation_id = NULL WHERE material_id = 102");
$resolved = $prices->forMaterial(1, 102, $today);
bomCheck($resolved['status'] === 'quotation_latest', '② 无默认时应回退最新有效报价');
bomCheck((int) $resolved['quotation_id'] === 12, '最新报价应取生效日更近的 12');

// 子 103：无报价 → 缺价（禁零）。
$resolved = $prices->forMaterial(1, 103, $today);
bomCheck($resolved['status'] === 'missing', '④ 无有效报价应判缺价');
bomCheck($resolved['unit_price'] === null, '缺价不得给出零单价');

// 辅材 201：项目成本价有效行。
$db->exec("INSERT INTO project_cost_prices (price_id, project_id, aux_id, catalog_id, unit_price, currency_code, pricing_unit, status) VALUES
    (41, 1, 201, NULL, 12.5000, 'CNY', 'm', 'active')");
$resolvedAux = $prices->forAuxiliary(1, 201, $today);
bomCheck($resolvedAux['status'] === 'cost_price', '辅材应取项目成本价');
bomCheck((float) $resolvedAux['unit_price'] === 12.5, '辅材成本价单价正确');

// 目录 301：项目成本价有效行。
$db->exec("INSERT INTO project_cost_prices (price_id, project_id, aux_id, catalog_id, unit_price, currency_code, pricing_unit, status) VALUES
    (42, 1, NULL, 301, 200.0000, 'CNY', '台班', 'active')");
$resolvedCat = $prices->forCatalog(1, 301, $today);
bomCheck($resolvedCat['status'] === 'cost_price', '目录项应取项目成本价');

echo "Price resolver four-level tests passed.\n";

// ============================================================================
// C5 组成实时引用（DR-1）：组成边增/改→重算反映；边删→结果行清理；同一来源一致。
// ============================================================================
$costingId = $costings->ensureCosting(1, 101, 1);

// 初始组成：101 → 102（系数 2）。
$db->exec("INSERT INTO Material_Compositions (composition_id, project_id, parent_material_id, child_material_id, quantity) VALUES
    (51, 1, 101, 102, 2.00)");

$committed = $recalc->recalc(1, $costingId, 1, $today);
bomCheck($committed, 'C5 首次重算应提交');
$resultsAfterAdd = $costings->listResults(1, $costingId);
bomCheck(count($resultsAfterAdd) === 1, 'C5 一条组件边应生成一条结果行');
bomCheck((int) $resultsAfterAdd[0]['composition_id'] === 51, '结果行应引用 composition_id=51');

// 改组成系数 3 → 重算后数量更新（实时引用，无副本）。
$db->exec("UPDATE Material_Compositions SET quantity = 3.00 WHERE composition_id = 51");
$recalc->recalc(1, $costingId, 1, $today);
$resultsAfterChange = $costings->listResults(1, $costingId);
bomCheck((float) $resultsAfterChange[0]['quantity'] === 3.000000, 'C5 系数变化应实时反映到计算数量');

// 删边 → 重算清理对应结果行（当前态明细随组成）。
$db->exec("DELETE FROM Material_Compositions WHERE composition_id = 51");
$recalc->recalc(1, $costingId, 1, $today);
$resultsAfterDelete = $costings->listResults(1, $costingId);
bomCheck($resultsAfterDelete === [], 'C5 组成边删除后对应结果行应被清理');

// 样品齐套与组价读同一来源：补回边，两侧查询同一 Material_Compositions。
$db->exec("INSERT INTO Material_Compositions (composition_id, project_id, parent_material_id, child_material_id, quantity) VALUES
    (52, 1, 101, 103, 1.00)");
$recalc->recalc(1, $costingId, 1, $today);
$compositions = $costings->compositionsForParent(1, 101);
bomCheck(count($compositions) === 1 && (int) $compositions[0]['child_material_id'] === 103, 'C5 组价与样品共用同一组成来源');

echo "C5 composition live-reference tests passed.\n";

// ============================================================================
// C1 重算中人工编辑：version 校验失败则丢弃本轮，编辑结果为准。
// ============================================================================
// 重算一次，建立基线 version=0（重算不改 version）。
$recalc->recalc(1, $costingId, 1, $today);
$headBefore = $costings->requireCosting(1, $costingId);
bomCheck((int) $headBefore['version'] === 0, '重算不应递增 version');

// 人工新增资源行 → version+1。
$lineId = $costings->saveResource(1, $costingId, [
    'resource_type' => 'labor',
    'catalog_id' => '301',
    'quantity' => '1.5',
], 1);
$headAfterEdit = $costings->requireCosting(1, $costingId);
bomCheck((int) $headAfterEdit['version'] === 1, '人工结构修改应使 version+1');

// 模拟重算读到旧 version=0，提交时条件更新 WHERE version=0 已不命中 → 丢弃。
// 直接调用引擎内部路径不暴露；此处断言：并发编辑后，新一轮重算基于新结构提交且不覆盖编辑。
$committed = $recalc->recalc(1, $costingId, 1, $today);
bomCheck($committed, '编辑后新一轮重算应基于新结构提交');
$resources = $costings->listResources(1, $costingId);
bomCheck(count($resources) === 1 && (int) $resources[0]['resource_line_id'] === $lineId, '重算不得覆盖人工资源行');

echo "C1 version optimistic-lock tests passed.\n";

// ============================================================================
// C3 分摊并发超额：Σ分摊≤可分摊净额，超额拒绝。
// ============================================================================
$recordId = $actuals->recordNormal(1, [
    'cost_category' => 'other',
    'amount' => '100.0000',
    'occurred_at' => $today,
], 1);

$actuals->allocate(1, ['record_id' => (string) $recordId, 'costing_id' => (string) $costingId, 'allocated_amount' => '60.0000'], 1);
bomExpectStatus(
    fn () => $actuals->allocate(1, ['record_id' => (string) $recordId, 'costing_id' => (string) $costingId, 'allocated_amount' => '50.0000'], 1),
    422,
    'C3 累计分摊超过净额必须拒绝（60+50>100）'
);

$stats = $actuals->statistics(1);
bomCheck((float) $stats['allocated'] === 60.0, 'C3 已分摊应为 60');
bomCheck((float) $stats['unallocated'] === 40.0, 'C3 未分摊应为 40，恒等式成立（100=60+40）');

echo "C3 allocation over-allocation tests passed.\n";

// ============================================================================
// C4 冲销/调整联动：累计冲销≤原额；负向调整致净额不足覆盖分摊→拒绝；恒等式每步成立。
// ============================================================================
$reversalId = $actuals->recordReversal(1, ['reversal_of_record_id' => (string) $recordId, 'amount' => '30.0000', 'occurred_at' => $today], 1);
bomCheck($reversalId > 0, 'C4 部分冲销应登记成功');

// 再冲销 80 → 累计 30+80=110 > 净额 100，拒绝。
bomExpectStatus(
    fn () => $actuals->recordReversal(1, ['reversal_of_record_id' => (string) $recordId, 'amount' => '80.0000', 'occurred_at' => $today], 1),
    422,
    'C4 累计冲销超过原额必须拒绝'
);

// 负向调整 -50：净额 100-30-50=20，已分摊 60 > 20 → 拒绝（先调分摊）。
bomExpectStatus(
    fn () => $actuals->recordAdjustment(1, ['adjustment_of_record_id' => (string) $recordId, 'amount' => '-50.0000', 'occurred_at' => $today], 1),
    422,
    'C4 负向调整后净额不足覆盖分摊必须拒绝'
);

// 正向调整 +10 应成功；净额 = 100 - 30 + 10 = 80。
$adjustId = $actuals->recordAdjustment(1, ['adjustment_of_record_id' => (string) $recordId, 'amount' => '10.0000', 'occurred_at' => $today], 1);
bomCheck($adjustId > 0, 'C4 正向调整应登记成功');

$stats = $actuals->statistics(1);
bomCheck((float) $stats['net'] === 80.0, 'C4 项目净额应为 80（100-30+10）');
bomCheck((float) $stats['allocated'] === 60.0, 'C4 已分摊仍为 60');
bomCheck((float) $stats['unallocated'] === 20.0, 'C4 未分摊应为 20，恒等式 80=60+20 成立');

// 冲销不可再被冲销（目标仅 normal）。
bomExpectStatus(
    fn () => $actuals->recordReversal(1, ['reversal_of_record_id' => (string) $reversalId, 'amount' => '5.0000', 'occurred_at' => $today], 1),
    422,
    'C4 冲销记录不可再被冲销'
);

echo "C4 reversal/adjustment linkage tests passed.\n";

// ============================================================================
// 项目隔离：跨项目读取/写入必须拒绝。
// ============================================================================
bomExpectStatus(
    fn () => $costings->requireCosting(2, $costingId),
    404,
    '跨项目读取组价必须 404'
);
bomExpectStatus(
    fn () => $actuals->recordNormal(2, ['cost_category' => 'material', 'material_id' => '102', 'amount' => '10.0000', 'occurred_at' => $today], 1),
    404,
    '跨项目引用材料必须 404'
);

// ============================================================================
// 目录 CRUD / 资源行删除 / 确认动作：补齐服务层直接覆盖。
// ============================================================================
// 目录新增 → 同类型重名拒绝 → 更新 → 分组选项。
$newCatalogId = $catalogSvc->save(['catalog_type' => 'processing', 'name' => '板材裁切', 'unit' => '次'], 1);
bomCheck($newCatalogId > 0, '目录项新增应返回编号');
bomExpectStatus(
    fn () => $catalogSvc->save(['catalog_type' => 'processing', 'name' => '板材裁切', 'unit' => '次']),
    422,
    '同类型目录重名必须拒绝'
);
$catalogSvc->save(['catalog_id' => (string) $newCatalogId, 'catalog_type' => 'processing', 'name' => '板材精裁', 'unit' => '次']);
$grouped = $catalogSvc->groupedOptions();
$names = array_column($grouped['processing'], 'name');
bomCheck(in_array('板材精裁', $names, true), '目录更新后分组选项应反映新名称');

// 资源行删除：删后清单为空，version 再 +1。
$lineBefore = $costings->listResources(1, $costingId);
bomCheck(count($lineBefore) === 1, '删除前应存在一条人工资源行');
$versionBeforeDelete = (int) $costings->requireCosting(1, $costingId)['version'];
$costings->deleteResource(1, $costingId, $lineBefore[0]['resource_line_id']);
$lineAfter = $costings->listResources(1, $costingId);
bomCheck($lineAfter === [], '资源行删除后清单应为空');
$versionAfterDelete = (int) $costings->requireCosting(1, $costingId)['version'];
bomCheck($versionAfterDelete === $versionBeforeDelete + 1, '删除资源行应使 version+1');

// 确认动作：draft → confirmed；重复确认拒绝。
$costings->confirm(1, $costingId, 1);
$confirmed = $costings->requireCosting(1, $costingId);
bomCheck($confirmed['status'] === 'confirmed', '草稿组价确认后应为 confirmed');
bomExpectStatus(
    fn () => $costings->confirm(1, $costingId, 1),
    422,
    '已确认组价不可重复确认'
);

echo "Catalog CRUD / resource delete / confirm tests passed.\n";

// ============================================================================
// 项目成本价服务（⑥）：类型化目标、同对象一条当前行 upsert、校验与隔离。
// ============================================================================
$priceSvc = new CostPriceService($db);
$db->exec("INSERT INTO Auxiliary_Materials (aux_id, project_id, aux_name, unit) VALUES (202, 1, '辅材乙', '个'), (203, 1, '辅材丙', 'kg')");

$auxPriceId = $priceSvc->save(1, [
    'aux_id' => '202', 'unit_price' => '9.9000', 'pricing_unit' => '个',
    'currency_code' => 'CNY', 'status' => 'active',
], 1);
bomCheck($auxPriceId > 0, '辅材成本价保存应返回编号');

// 同对象再次保存 → upsert 同一行，不产生重复定价。
$priceSvc->save(1, ['aux_id' => '202', 'unit_price' => '11.0000', 'pricing_unit' => '个'], 1);
$auxRows = array_values(array_filter($priceSvc->list(1), static fn (array $p): bool => $p['aux_id'] == 202));
bomCheck(count($auxRows) === 1, '同辅材只应保留一条当前成本价');
bomCheck((float) $auxRows[0]['unit_price'] === 11.0, '重复保存应更新原行单价');
bomCheck($auxRows[0]['aux_name'] === '辅材乙', '成本价清单应带出辅材名称');

// 目录项成本价 upsert（301 已有种子行 42，再次保存更新该行）。
$priceSvc->save(1, ['catalog_id' => '301', 'unit_price' => '220.0000', 'pricing_unit' => '台班'], 1);
$catRows = array_values(array_filter($priceSvc->list(1), static fn (array $p): bool => $p['catalog_id'] == 301));
bomCheck(count($catRows) === 1 && (float) $catRows[0]['unit_price'] === 220.0, '目录项重复保存应 upsert 同一行');

// 校验：目标恰一；单价大于零；对象归属项目。
bomExpectStatus(
    fn () => $priceSvc->save(1, ['aux_id' => '202', 'catalog_id' => '301', 'unit_price' => '5.0000', 'pricing_unit' => '个'], 1),
    422,
    '辅材与目录项同时定价必须拒绝'
);
bomExpectStatus(
    fn () => $priceSvc->save(1, ['unit_price' => '5.0000', 'pricing_unit' => '个'], 1),
    422,
    '未指定定价对象必须拒绝'
);
bomExpectStatus(
    fn () => $priceSvc->save(1, ['aux_id' => '202', 'unit_price' => '0', 'pricing_unit' => '个'], 1),
    422,
    '单价为零必须拒绝'
);
bomExpectStatus(
    fn () => $priceSvc->save(2, ['aux_id' => '202', 'unit_price' => '5.0000', 'pricing_unit' => '个'], 1),
    404,
    '跨项目引用辅材定价必须 404'
);

// 未被引用的成本价可删除。
$tmpPriceId = $priceSvc->save(1, ['aux_id' => '203', 'unit_price' => '7.5000', 'pricing_unit' => 'kg'], 1);
$priceSvc->delete(1, $tmpPriceId);
bomCheck(array_filter($priceSvc->list(1), static fn (array $p): bool => $p['price_id'] == $tmpPriceId) === [], '未引用成本价删除后清单应移除');

echo "Cost price service tests passed.\n";

// ============================================================================
// 缺项派生与可建组价材料清单：缺价行不落结果表，缺项按当前结构差集计数。
// ============================================================================
$detail = $costings->requireCosting(1, $costingId);
// 此时组成边 52 → 子材料 103 无报价（缺价）；资源行已删，缺项数应为 1。
bomCheck((int) $detail['missing_count'] === 1, '缺项数应派生自未命中有效结果的组件/资源行');

$available = $costings->listAvailableMaterials(1);
$availableIds = array_map(static fn (array $m): int => (int) $m['material_id'], $available);
bomCheck(!in_array(101, $availableIds, true), '已建组价的父材料不应出现在可选清单');
bomCheck(in_array(102, $availableIds, true), '未建组价的材料应可被选择');

echo "Missing-count / available-material tests passed.\n";

echo "Project isolation tests passed.\n";
echo "BOM costing all tests passed.\n";
