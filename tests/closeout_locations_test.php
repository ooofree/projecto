<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\CloseoutManagement\CloseoutService;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function locCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function locExpectStatus(callable $operation, int $status, string $message): void
{
    try {
        $operation();
        throw new RuntimeException($message);
    } catch (HttpException $exception) {
        if ($exception->statusCode !== $status) {
            throw new RuntimeException($message . '（实际 ' . $exception->statusCode . '：' . $exception->getMessage() . '）');
        }
    }
}

Support::setDisplayTimezone('Asia/Shanghai');

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY AUTOINCREMENT, project_name TEXT NOT NULL, status TEXT)');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, display_name TEXT, is_active INTEGER NOT NULL DEFAULT 1, timezone TEXT)');
$db->exec('CREATE TABLE Suppliers (supplier_id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_name TEXT NOT NULL)');
$db->exec('CREATE TABLE Materials (
    material_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, material_code TEXT, material_name TEXT NOT NULL,
    unit TEXT NOT NULL, specifications TEXT, material_description_detailed TEXT, category_id INTEGER,
    UNIQUE(project_id, material_id)
)');
$db->exec('CREATE TABLE Material_Categories (category_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, category_name TEXT)');
$db->exec('CREATE TABLE Material_Compositions (
    composition_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL,
    parent_material_id INTEGER NOT NULL, child_material_id INTEGER NOT NULL, quantity NUMERIC NOT NULL DEFAULT 1
)');
$db->exec('CREATE TABLE document_number_sequences (
    project_id INTEGER NOT NULL, document_type TEXT NOT NULL, last_number INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (project_id, document_type)
)');
$db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, building_number TEXT, building_name TEXT)');
$db->exec('CREATE TABLE Building_Floors (floor_id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, floor_number TEXT, floor_name TEXT, usage_type TEXT)');
$db->exec('CREATE TABLE Rooms (room_id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, floor_id INTEGER NOT NULL, room_number TEXT, room_type TEXT, room_area REAL)');
$db->exec('CREATE TABLE Initial_Cost_Estimate_Detail (
    estimate_detail_id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL,
    floor_id INTEGER, room_id INTEGER, material_id INTEGER NOT NULL,
    estimated_quantity NUMERIC NOT NULL DEFAULT 0, remarks TEXT
)');
$db->exec('CREATE TABLE project_closeout_plans (
    plan_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_no TEXT NOT NULL,
    plan_name TEXT NOT NULL, description TEXT, status TEXT NOT NULL DEFAULT "draft",
    planned_start_date TEXT, planned_end_date TEXT, closed_at TEXT, closed_by INTEGER,
    reopened_at TEXT, reopened_by INTEGER, created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, plan_no), UNIQUE(plan_id, project_id)
)');
$db->exec('CREATE TABLE Labor_Crew_Types (crew_type_id INTEGER PRIMARY KEY AUTOINCREMENT, type_name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE Labor_Crews (crew_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, crew_type_id INTEGER, crew_name TEXT NOT NULL, leader_name TEXT NOT NULL DEFAULT "", is_active INTEGER NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE project_closeout_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_no TEXT NOT NULL, material_id INTEGER NOT NULL, coverage_mode TEXT NOT NULL DEFAULT "whole",
    coverage_root_id INTEGER, title TEXT NOT NULL DEFAULT "", description TEXT, status TEXT NOT NULL DEFAULT "pending",
    responsible_supplier_id INTEGER, responsible_user_id INTEGER, responsible_crew_id INTEGER,
    planned_start_date TEXT, planned_end_date TEXT, submitted_at TEXT, actual_complete_date TEXT,
    risk_level TEXT NOT NULL DEFAULT "medium", cancel_reason TEXT, cancelled_by INTEGER, cancelled_at TEXT,
    created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, item_no), UNIQUE(item_id, project_id)
)');
$db->exec('CREATE UNIQUE INDEX uq_closeout_item_active ON project_closeout_items(project_id, material_id) WHERE status <> "cancelled"');
$db->exec('CREATE TABLE project_closeout_locations (
    location_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL, estimate_detail_id INTEGER, building_id INTEGER NOT NULL,
    floor_id INTEGER, room_id INTEGER, baseline_qty NUMERIC NOT NULL DEFAULT 0, baseline_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT "pending", submitted_at TEXT, accepted_at TEXT, accepted_by INTEGER,
    planned_start_date TEXT, planned_end_date TEXT, responsible_supplier_id INTEGER, responsible_user_id INTEGER, responsible_crew_id INTEGER,
    cancel_reason TEXT, cancelled_by INTEGER, cancelled_at TEXT, created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE UNIQUE INDEX uq_closeout_location_active ON project_closeout_locations(project_id, item_id, building_id, COALESCE(floor_id, 0), COALESCE(room_id, 0)) WHERE status <> "cancelled"');
$db->exec('CREATE TABLE project_closeout_progress_logs (
    progress_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL, location_id INTEGER, progress_type TEXT NOT NULL DEFAULT "note",
    progress_note TEXT NOT NULL, recorded_by INTEGER NOT NULL, recorded_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE project_closeout_acceptances (
    acceptance_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL, location_id INTEGER, result TEXT NOT NULL, remark TEXT,
    evidence_reference TEXT, accepted_by INTEGER NOT NULL, accepted_at TEXT NOT NULL
)');

// Project 10: parent 101 measured in 3 spaces (10 + 20 + 5). Parent 201 has no
// estimate row. Project 20 holds an estimate row for a different project to
// prove the Buildings-based isolation.
$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (10, '空间项目', 'ongoing'), (20, '别的项目', 'ongoing')");
$db->exec("INSERT INTO Users (user_id, username, is_active) VALUES (1, 'admin', 1), (2, 'pm', 1)");
$db->exec("INSERT INTO Suppliers (supplier_id, supplier_name) VALUES (1, '甲供单位')");
$db->exec("INSERT INTO Material_Categories (category_id, project_id, category_name) VALUES (1, 10, '门类')");
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, specifications, category_id) VALUES
    (101, 10, 'M-101', '成品门', '樘', 'M1526', 1),
    (102, 10, 'M-102', '门框', '套', '定制', 1),
    (201, 10, 'M-201', '灯具', '套', 'LED', 1),
    (205, 10, 'M-205', '灯带卡扣', '套', '配套', 1),
    (301, 20, 'M-301', '玻璃门', '樘', '12mm', NULL),
    (302, 20, 'M-302', '玻璃门五金', '套', '配套', NULL)");
$db->exec("INSERT INTO Material_Compositions (project_id, parent_material_id, child_material_id, quantity) VALUES
    (10, 101, 102, 1), (10, 201, 205, 1), (20, 301, 302, 1)");
$db->exec("INSERT INTO Buildings (building_id, project_id, building_number, building_name) VALUES
    (1, 10, 'A', 'A 栋'), (2, 10, 'B', 'B 栋'), (3, 20, 'C', 'C 栋')");
$db->exec("INSERT INTO Building_Floors (floor_id, building_id, floor_number, floor_name) VALUES
    (11, 1, '1', '一层'), (12, 2, '1', '一层'), (21, 3, '1', '一层')");
$db->exec("INSERT INTO Rooms (room_id, building_id, floor_id, room_number, room_area) VALUES
    (111, 1, 11, '101', 20), (112, 1, 11, '102', 40), (121, 2, 12, '201', 10), (211, 3, 21, '301', 10)");
$db->exec("INSERT INTO Initial_Cost_Estimate_Detail (building_id, floor_id, room_id, material_id, estimated_quantity) VALUES
    (1, 11, 111, 101, 10),
    (1, 11, 112, 101, 20),
    (2, 12, 121, 101, 5),
    (3, 21, 211, 301, 30)");

$service = new CloseoutService($db);
$today = Support::nowLocal('Y-m-d');
$planId = $service->savePlan(10, [
    'plan_name' => '空间消项计划',
    'planned_start_date' => $today,
    'planned_end_date' => $today,
    'status' => 'active',
], 1);

// ------------------------------------------------------------ 自动纳入（无需人工勾选）
$auto = $service->listItems(10);
locCheck(count($auto) === 1, '活跃计划应自动为已测算父材料建事项');
locCheck((int) $auto[0]['material_id'] === 101, '自动纳入的事项应锚定父材料 101');
$itemId = (int) $auto[0]['item_id'];
locCheck($auto[0]['location_total'] === 3, '自动纳入应同时挂出 3 个空间子项');

$again = $service->autoProvision(10, $planId, 1);
locCheck($again['created'] === [], '重复自动纳入不应重复建事项');
locCheck($again['existing'] === 1, '重复自动纳入应把已存在的事项计为 existing');

$locations = $service->listLocations(10, $itemId);
locCheck(count($locations) === 3, '三个测算空间应生成三条子项');
$baseline = 0.0;
foreach ($locations as $location) {
    $baseline += (float) $location['baseline_qty'];
}
locCheck(abs($baseline - 35.0) < 0.0001, '基线量应为 10+20+5=35');

$item = $service->requireItem(10, $itemId);
locCheck($item['has_locations'] === true, '事项应标记为已关联空间');
locCheck($item['location_total'] === 3, '事项应汇总子项数量');
locCheck($item['progress_verified'] === 0.0, '未验收时量加权进度应为 0');
locCheck($item['status'] === 'pending', '新建事项应为待处理');

// 父材料没有测算行 → 项目已启用测算，必须拒绝
locExpectStatus(
    static fn () => $service->createItems(10, $planId, [201], [], 1),
    422,
    '无测算行的父材料应被拒绝纳入'
);

// --------------------------------------------------------------- 状态派生（木桶）
$first = $locations[0];
$service->startLocation(10, (int) $first['location_id'], 1);
locCheck($service->requireItem(10, $itemId)['status'] === 'pending', '仍有空间未开始，事项应保持待处理');

$service->submitLocation(10, (int) $first['location_id'], 'A 栋 101 安装完成', 1);
locCheck($service->requireItem(10, $itemId)['status'] === 'pending', '只申报一个空间，事项不应进入待验收');

foreach (array_slice($locations, 1) as $location) {
    $service->submitLocation(10, (int) $location['location_id'], '其余空间申报', 1);
}
$item = $service->requireItem(10, $itemId);
locCheck($item['status'] === 'pending_accept', '全部空间申报后事项应进入待验收');
locCheck($item['progress_submitted'] === 100.0, '全部申报时已申报进度应为 100');
locCheck($item['progress_verified'] === 0.0, '未验收时已验收进度仍为 0');

// 逐空间验收：两空间通过，一空间驳回
$service->acceptLocation(10, (int) $locations[0]['location_id'], ['result' => 'passed', 'actual_complete_date' => $today], 2);
$service->acceptLocation(10, (int) $locations[1]['location_id'], ['result' => 'passed', 'actual_complete_date' => $today], 2);
locExpectStatus(
    static fn () => $service->acceptLocation(10, (int) $locations[2]['location_id'], ['result' => 'rejected'], 2),
    422,
    '驳回验收必须填写意见'
);
$service->acceptLocation(10, (int) $locations[2]['location_id'], ['result' => 'rejected', 'remark' => '门缝不平'], 2);

$item = $service->requireItem(10, $itemId);
locCheck($item['status'] === 'ongoing', '存在驳回空间时事项应回到进行中');
locCheck($item['progress_verified'] === 85.7, '已验收 30/35 应为 85.7%，而不是按条数 66.7%');
locCheck($item['actual_complete_date'] === null, '未全部通过时不得写实际完成日期');

$service->submitLocation(10, (int) $locations[2]['location_id'], '整改完成', 1);
$service->acceptLocation(10, (int) $locations[2]['location_id'], ['result' => 'passed', 'actual_complete_date' => $today], 2);
$item = $service->requireItem(10, $itemId);
locCheck($item['status'] === 'closed', '全部空间验收通过后事项才应已消项');
locCheck($item['progress_verified'] === 100.0, '全部通过后进度应为 100');
locCheck($item['actual_complete_date'] === $today, '实际完成日期应取最后一条验收日期');

$stats = $service->statistics(10);
locCheck($stats['completion_rate'] === 100.0, '事项条数完成率应为 100');
locCheck($stats['qty_completion_rate'] === 100.0, '工程量完成率应为 100');
locCheck(abs($stats['qty_total'] - 35.0) < 0.0001, '工程量分母应为 35');

// --------------------------------------------------------------- 事项级批量验收
$service->reopenItem(10, $itemId, '返工重做', 1);
locCheck($service->requireItem(10, $itemId)['status'] === 'ongoing', '重新打开后事项应回进行中');
locCheck(
    $service->listLocations(10, $itemId)[0]['status'] === 'ongoing',
    '重新打开应把已消项空间退回进行中'
);

$batchItem = (int) $service->createItems(10, $planId, [201], [], 1, null, true)[0]['item_id'];
locCheck($service->requireItem(10, $batchItem)['has_locations'] === false, '显式允许时应可创建未定位事项');
locExpectStatus(
    static fn () => $service->acceptItem(10, $itemId, ['result' => 'passed', 'actual_complete_date' => $today], 2),
    422,
    '事项没有待验收空间时批量验收应被拒绝'
);

// --------------------------------------------------------------- 空间取消与分母
$cancelLocation = $service->listLocations(10, $itemId)[2];
$service->cancelLocation(10, (int) $cancelLocation['location_id'], '甲方取消该空间施工', 1);
$item = $service->requireItem(10, $itemId);
locCheck($item['location_total'] === 3, '历史取消的空间仍应保留在列表');
locCheck(abs($item['qty_total'] - 30.0) < 0.0001, '取消空间后分母应从 35 缩到 30');
locCheck($item['progress_verified'] === 0.0, '取消空间后已验收量重算为 0');

// --------------------------------------------------------------- 重定基线
$rebaseline = $service->listLocations(10, $itemId)[0];
$db->exec('UPDATE Initial_Cost_Estimate_Detail SET estimated_quantity = 12 WHERE material_id = 101 AND room_id = 111');
locCheck(
    abs((float) $service->requireLocation(10, (int) $rebaseline['location_id'])['baseline_qty'] - 10.0) < 0.0001,
    '测算修改后基线不得自动变化'
);
$service->rebuildBaseline(10, (int) $rebaseline['location_id'], '现场实测调整', 1);
locCheck(
    abs((float) $service->requireLocation(10, (int) $rebaseline['location_id'])['baseline_qty'] - 12.0) < 0.0001,
    '重定基线后应取当前测算量'
);

// --------------------------------------------------------------- 项目隔离
$planOther = $service->savePlan(20, ['plan_name' => '别的项目计划', 'status' => 'active'], 1);
$otherItems = $service->listItems(20);
locCheck(count($otherItems) === 1, '另一项目的活跃计划也应自动纳入自己的父材料');
locCheck(count($service->listLocations(20, (int) $otherItems[0]['item_id'])) === 1, '另一项目应只看到自己的测算空间');

// ------------------------------------------------------- 存量（V1）事项空间补齐
// V1 items were created before the space table existed: rows exist, spaces do
// not. Backfill must rebuild spaces from the same estimate rows, be idempotent,
// and report — never invent — the cases it cannot fill.
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id)
    VALUES (103, 10, 'M-103', '踢脚线', 'm', 1)");
$db->exec('INSERT INTO Initial_Cost_Estimate_Detail (building_id, floor_id, room_id, material_id, estimated_quantity) VALUES
    (1, 11, 111, 103, 8),
    (1, 11, 112, 103, 4)');

$draftPlan = $service->savePlan(10, ['plan_name' => '草稿计划', 'status' => 'draft'], 1);
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id)
    VALUES (104, 10, 'M-104', '墙面漆', '㎡', 1)");
$db->exec('INSERT INTO Initial_Cost_Estimate_Detail (building_id, floor_id, room_id, material_id, estimated_quantity) VALUES
    (1, 11, 111, 104, 6)');
$db->exec("INSERT INTO project_closeout_items (project_id, plan_id, item_no, material_id, title, status, created_by)
    VALUES (10, $planId, 'P10-CLO-L1', 103, '踢脚线（旧）', 'ongoing', 1),
           (10, $planId, 'P10-CLO-L2', 102, '门框（旧，子材料）', 'pending', 1),
           (10, $draftPlan, 'P10-CLO-L3', 205, '灯带卡扣（草稿计划）', 'pending', 1),
           (10, $planId, 'P10-CLO-L4', 104, '墙面漆（已消项）', 'closed', 1)");

$backfill = $service->backfillLocations(10, 1);
locCheck(count($backfill['filled']) === 1, '存量补齐应只处理可补的那一条事项');
locCheck($backfill['filled'][0]['spaces'] === 2, '103 在两个空间有测算，应补 2 个空间');
locCheck($backfill['filled'][0]['status'] === 'ongoing', '补齐回报里应带原事项状态');
locCheck($backfill['locations_created'] === 2, '并列报创建的空间总数');
locCheck($backfill['existing'] === 1, '已有空间的事项应计入 existing');
locCheck($backfill['missing_estimates'] !== [], '缺测算的事项应被点名，不能凭空造量');
locCheck($backfill['not_parent'] !== [], '建在子材料上的旧事项应报为非父材料');
locCheck($backfill['plan_inactive'] !== [], '草稿计划下的事项不应补齐');
locCheck($backfill['closed_skipped'] !== [], '已消项的事项不应被补空间（会被木桶回算重新打开）');

$closedItem = null;
foreach ($service->listItems(10) as $row) {
    if ((string) $row['item_no'] === 'P10-CLO-L4') {
        $closedItem = $row;
        break;
    }
}
locCheck($closedItem !== null && $closedItem['status'] === 'closed', '已消项事项补齐后仍应保持已消项');
locCheck($closedItem['location_total'] === 0, '已消项事项不应被补空间');

$l1 = null;
foreach ($service->listItems(10) as $row) {
    if ((string) $row['item_no'] === 'P10-CLO-L1') {
        $l1 = $row;
        break;
    }
}
locCheck($l1 !== null && $l1['has_locations'] === true, '补齐后旧事项应带空间');
locCheck($l1['location_total'] === 2, '补齐后应挂出 2 个空间');
locCheck(abs((float) $l1['qty_total'] - 12.0) < 0.0001, '补齐后分母应为 8+4=12');
locCheck($l1['progress_verified'] === 0.0, '补齐不追认历史进度，已验收量应为 0');
locCheck($l1['status'] === 'ongoing', '补齐不得重算事项状态，进行中应保留');
locCheck(
    $service->listLocations(10, (int) $l1['item_id'])[0]['status'] === 'ongoing',
    '进行中的旧事项，其补出的空间初态应为进行中'
);

$backfillAgain = $service->backfillLocations(10, 1);
locCheck($backfillAgain['filled'] === [], '二次补齐不应重复建空间');
locCheck($backfillAgain['existing'] === 2, '二次补齐应把已补的事项计入 existing');

// ------------------------------------------------- 开始执行（空间跟踪事项）
// Item-level "start" on a space-tracked item means: start every pending space.
// It must not throw the old "请逐个空间开始执行" error.
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, category_id)
    VALUES (106, 10, 'M-106', '收边条', 'm', 1)");
$db->exec("INSERT INTO project_closeout_items (project_id, plan_id, item_no, material_id, title, status, created_by)
    VALUES (10, $planId, 'P10-CLO-L5', 106, '收边条（待开始）', 'pending', 1)");
$startItemId = (int) $db->query("SELECT item_id FROM project_closeout_items WHERE item_no = 'P10-CLO-L5'")->fetchColumn();
$db->exec("INSERT INTO project_closeout_locations (project_id, plan_id, item_id, building_id, floor_id, room_id,
    baseline_qty, baseline_at, status, created_by)
    VALUES (10, $planId, $startItemId, 1, 11, 111, 5, '2026-11-27 09:00:00', 'pending', 1)");
locCheck($service->listLocations(10, $startItemId)[0]['status'] === 'pending', '新建空间应为待处理');

$startResult = $service->startItem(10, $startItemId, 1);
locCheck($startResult['spaces_started'] === 1, '开始执行应返回置为进行中的空间数');
locCheck($service->listLocations(10, $startItemId)[0]['status'] === 'ongoing', '开始执行应把待处理空间置为进行中');
locCheck($service->requireItem(10, $startItemId)['status'] === 'ongoing', '空间全部开始后事项应进入进行中');
locExpectStatus(
    static fn () => $service->startItem(10, $startItemId, 1),
    422,
    '已进入执行的事项不应能再次开始'
);

// ------------------------------------------------------ 责任班组（2026-11-27）
// The responsible party is a crew (project-scoped), not a system account: the
// Users table only holds management logins.
$db->exec("INSERT INTO Labor_Crew_Types (crew_type_id, type_name, sort_order) VALUES (1, '装饰装修', 110)");
$db->exec("INSERT INTO Labor_Crews (crew_id, project_id, crew_type_id, crew_name, leader_name, is_active) VALUES
    (1, 10, 1, '木工班组', '熊诗军', 1),
    (2, 11, 1, '外项目班组', '张三', 1),
    (3, 10, 1, '已归档班组', '李四', 0)");
$crewOptions = $service->activeCrews(10);
locCheck(count($crewOptions) === 1, '班组下拉只应取本项目在用班组');
locCheck($crewOptions[0]['name'] === '木工班组（熊诗军）', '班组选项应显示「班组（负责人）」');

$service->updateItem(10, $startItemId, ['responsible_crew_id' => 1], 1);
$crewItem = $service->requireItem(10, $startItemId);
locCheck((int) $crewItem['responsible_crew_id'] === 1, '事项应保存责任班组');
locCheck($crewItem['responsible_label'] === '木工班组（熊诗军）', '事项应显示班组与负责人');
locExpectStatus(
    static fn () => $service->updateItem(10, $startItemId, ['responsible_crew_id' => 2], 1),
    422,
    '跨项目的班组不应能指派'
);
locExpectStatus(
    static fn () => $service->updateItem(10, $startItemId, ['responsible_crew_id' => 3], 1),
    422,
    '已归档的班组不应能指派'
);

echo "closeout location tests passed\n";
