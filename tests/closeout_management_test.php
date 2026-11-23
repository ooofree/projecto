<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\CloseoutManagement\CloseoutService;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function closeoutCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function closeoutExpectStatus(callable $operation, int $status, string $message): void
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
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, is_active INTEGER NOT NULL DEFAULT 1, timezone TEXT)');
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
$db->exec('CREATE TABLE project_closeout_plans (
    plan_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_no TEXT NOT NULL,
    plan_name TEXT NOT NULL, description TEXT, status TEXT NOT NULL DEFAULT "draft",
    planned_start_date TEXT, planned_end_date TEXT, closed_at TEXT, closed_by INTEGER,
    reopened_at TEXT, reopened_by INTEGER, created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, plan_no), UNIQUE(plan_id, project_id)
)');
$db->exec('CREATE TABLE project_closeout_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_no TEXT NOT NULL, material_id INTEGER NOT NULL, coverage_mode TEXT NOT NULL DEFAULT "whole",
    coverage_root_id INTEGER, title TEXT NOT NULL, description TEXT, status TEXT NOT NULL DEFAULT "pending",
    responsible_supplier_id INTEGER, responsible_user_id INTEGER,
    planned_start_date TEXT, planned_end_date TEXT, submitted_at TEXT, actual_complete_date TEXT,
    risk_level TEXT NOT NULL DEFAULT "medium", cancel_reason TEXT, cancelled_by INTEGER, cancelled_at TEXT,
    created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, item_no), UNIQUE(item_id, project_id)
)');
$db->exec('CREATE UNIQUE INDEX uq_closeout_item_active ON project_closeout_items(project_id, material_id) WHERE status <> "cancelled"');
$db->exec('CREATE TABLE project_closeout_progress_logs (
    progress_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL, progress_type TEXT NOT NULL DEFAULT "note", progress_note TEXT NOT NULL,
    recorded_by INTEGER NOT NULL, recorded_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE project_closeout_acceptances (
    acceptance_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, plan_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL, result TEXT NOT NULL, remark TEXT, evidence_reference TEXT,
    accepted_by INTEGER NOT NULL, accepted_at TEXT NOT NULL
)');
$db->exec('CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, project_id INTEGER, entity_type TEXT,
    entity_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT
)');

$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, '测试项目', 'ongoing'), (2, '另一项目', 'ongoing'), (3, '无组成项目', 'ongoing')");
$db->exec("INSERT INTO Users (user_id, username, is_active) VALUES (1, 'admin', 1), (2, 'pm', 1), (3, 'alice', 1), (4, 'disabled', 0)");
$db->exec("INSERT INTO Suppliers (supplier_id, supplier_name) VALUES (1, '甲供单位'), (2, '乙供单位')");
$db->exec("INSERT INTO Material_Categories (category_id, project_id, category_name) VALUES (1, 1, '门类'), (2, 2, '玻璃')");
$db->exec("INSERT INTO Materials (material_id, project_id, material_code, material_name, unit, specifications, category_id) VALUES
    (101, 1, 'M-101', '成品门', '樘', 'M1526', 1),
    (102, 1, 'M-102', '门框', '套', '定制', 1),
    (103, 1, 'M-103', '五金件', '套', '哑光黑', 1),
    (104, 1, 'M-104', '合页', '个', '不锈钢', 1),
    (201, 1, 'M-201', '灯具', '套', 'LED', 1),
    (202, 1, 'M-202', '开关', '个', '86 型', 1),
    (205, 1, 'M-205', '灯带卡扣', '套', '配套', 1),
    (206, 1, 'M-206', '开关面板', '个', '86 型', 1),
    (207, 1, 'M-207', '独立辅材', '件', '无组成', 1),
    (301, 2, 'M-301', '玻璃门', '樘', '12mm', 2),
    (302, 2, 'M-302', '玻璃门五金', '套', '配套', 2),
    (401, 3, 'M-401', 'A独立材料', '件', '无组成', NULL),
    (402, 3, 'M-402', 'B独立材料', '件', '无组成', NULL),
    (403, 3, 'M-403', 'C独立材料', '件', '无组成', NULL)");
$db->exec("INSERT INTO Material_Compositions (project_id, parent_material_id, child_material_id, quantity) VALUES
    (1, 101, 102, 1), (1, 101, 103, 1), (1, 102, 104, 2),
    (1, 201, 205, 1), (1, 202, 206, 1), (2, 301, 302, 1)");

$service = new CloseoutService($db);
$today = Support::nowLocal('Y-m-d');

// ---------------------------------------------------- 候选只保留顶层父材料
$candidateIds = array_map(static fn (array $row): int => (int) $row['material_id'], $service->bomCandidates(1));
sort($candidateIds);
closeoutCheck($candidateIds === [101, 201, 202], 'BOM 候选只保留有下级组成的父材料');
closeoutCheck(!in_array(102, $candidateIds, true), '子材料门框不得出现在候选清单');
closeoutCheck(!in_array(103, $candidateIds, true), '子材料五金件不得出现在候选清单');
closeoutCheck(!in_array(104, $candidateIds, true), '更下层子材料合页不得出现在候选清单');
closeoutCheck(!in_array(207, $candidateIds, true), '无下级组成的独立材料不得出现在候选清单');
closeoutCheck($service->bomMaterialCount(1) === 9, '项目 1 应共有 9 项 BOM 材料');
closeoutCheck($service->bomMaterialCount(1) - count($candidateIds) === 6, '应隐藏 6 项非父材料');

// ---------------------------------------------------------------- 计划与编号
$planId = $service->savePlan(1, [
    'plan_name' => '竣工验收消项计划',
    'planned_start_date' => $today,
    'planned_end_date' => $today,
    'status' => 'active',
], 1);
$plan = $service->requirePlan(1, $planId);
closeoutCheck($plan['plan_no'] === 'P1-CLO-PLAN-001', '计划编号应从 P1-CLO-PLAN-001 起编');
closeoutCheck($plan['status'] === 'active', '新建计划应可按进行中创建');

$draftPlanId = $service->savePlan(1, ['plan_name' => '草稿计划', 'status' => 'draft'], 1);
$draftPlan = $service->requirePlan(1, $draftPlanId);
closeoutCheck($draftPlan['plan_no'] === 'P1-CLO-PLAN-002', '计划编号应顺序递增');
closeoutCheck($draftPlan['status'] === 'draft', '草稿计划状态应保留');

closeoutExpectStatus(
    static fn () => $service->createItems(1, $draftPlanId, [201], [], 1),
    422,
    '草稿计划不得纳入事项'
);

closeoutCheck($service->setPlanStatus(1, $draftPlanId, 'activate', 1)['after']['status'] === 'active', '草稿计划应可启动');

closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [102], [], 1),
    422,
    '子材料即便父材料未纳入也不得单独纳入'
);
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [207], [], 1),
    422,
    '无下级组成的独立材料不得纳入'
);

// ------------------------------------------------------------ BOM 快照与纳入
$created = $service->createItems(1, $planId, [101], [
    'planned_start_date' => $today,
    'planned_end_date' => $today,
    'responsible_supplier_id' => 1,
    'responsible_user_id' => 2,
    'risk_level' => 'high',
], 1);
closeoutCheck(count($created) === 1, '应纳入一条事项');
closeoutCheck($created[0]['item_no'] === 'P1-CLO-001', '事项编号应从 P1-CLO-001 起编');

$item = $service->requireItem(1, $created[0]['item_id']);
closeoutCheck($item['coverage_root_id'] === 101, '整体覆盖根应为自身');
closeoutCheck($item['status'] === 'pending', '新纳入事项应为待处理');
closeoutCheck($item['risk_level'] === 'high', '风险等级应保存');
closeoutCheck(!array_key_exists('snapshot', $item), '事项读取结果不应再包含 BOM 快照');
closeoutCheck($item['material_name'] === '成品门', '材料名称应实时读取当前 BOM');
closeoutCheck($item['title'] === '', '创建事项不应复制材料名称到 title');
closeoutCheck($item['display_name'] === '成品门', '未填自定义标签时应回退实时材料名称');
$relations = $service->materialRelations(1, (int) $item['material_id']);
$childIds = array_map(static fn (array $row): int => (int) $row['material_id'], $relations['children']);
closeoutCheck($childIds === [102, 103], '组成关系应实时读取当前 BOM');
$db->exec("UPDATE Materials SET material_name = '成品门（改名）' WHERE project_id = 1 AND material_id = 101");
closeoutCheck(
    $service->requireItem(1, (int) $item['item_id'])['material_name'] === '成品门（改名）',
    '材料改名后事项详情应实时反映'
);
closeoutCheck(
    $service->requireItem(1, (int) $item['item_id'])['display_name'] === '成品门（改名）',
    '材料改名后默认事项名称应实时跟随'
);
$db->exec("UPDATE Materials SET material_name = '成品门' WHERE project_id = 1 AND material_id = 101");

// ---------------------------------------------------------- 唯一性与父子互斥
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [101], [], 1),
    422,
    '同一材料重复纳入应被拒绝'
);
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [102], [], 1),
    422,
    '父级已整体纳入时子材料应被拒绝'
);
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [104], [], 1),
    422,
    '更下层子材料同样应被拒绝'
);
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [101, 102], [], 1),
    422,
    '同一批选择父子材料应整体拒绝'
);

$second = $service->createItems(1, $planId, [201], [], 1);
closeoutCheck($second[0]['item_no'] === 'P1-CLO-002', '失败的选择不应消耗编号');

// 取消后可重新纳入，并保留取消记录
$itemA = $service->requireItem(1, $created[0]['item_id']);
closeoutExpectStatus(
    static fn () => $service->cancelItem(1, $itemA['item_id'], '', 1),
    422,
    '取消必须填写原因'
);
$service->cancelItem(1, $itemA['item_id'], '材料改甲供', 1);
$cancelled = $service->requireItem(1, $itemA['item_id']);
closeoutCheck($cancelled['status'] === 'cancelled', '取消后状态应为已取消');
closeoutCheck($cancelled['cancel_reason'] === '材料改甲供', '取消原因应保存');

closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [202], ['coverage_mode' => 'split'], 1),
    422,
    '父材料清单不再支持拆分覆盖方式'
);
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [104], [], 1),
    422,
    '任何层级的子材料都不得单独纳入'
);

$service->restoreItem(1, $itemA['item_id'], '现场仍需收口', 1);
$restored = $service->requireItem(1, $itemA['item_id']);
closeoutCheck($restored['status'] === 'pending', '恢复后应回到待处理');
closeoutCheck($restored['cancel_reason'] === null, '恢复后应清空取消字段');

// ------------------------------------------------------------- 状态机与验收
$service->startItem(1, $itemA['item_id'], 1);
closeoutCheck($service->requireItem(1, $itemA['item_id'])['status'] === 'ongoing', '开始后应为进行中');
closeoutExpectStatus(
    static fn () => $service->startItem(1, $itemA['item_id'], 1),
    422,
    '重复开始应被拒绝'
);

$service->submitItem(1, $itemA['item_id'], '门框安装完成', 1);
$submitted = $service->requireItem(1, $itemA['item_id']);
closeoutCheck($submitted['status'] === 'pending_accept', '提交后应为待验收');
closeoutCheck($submitted['submitted_at'] !== null, '提交时间应记录');
$progress = $service->listProgress(1, $itemA['item_id']);
closeoutCheck(count($progress) === 1 && $progress[0]['progress_type'] === 'submit', '提交应写入进度日志');

$service->acceptItem(1, $itemA['item_id'], ['result' => 'rejected', 'remark' => '收口不严'], 2);
closeoutCheck($service->requireItem(1, $itemA['item_id'])['status'] === 'ongoing', '不通过应退回进行中');
closeoutExpectStatus(
    static fn () => $service->acceptItem(1, $itemA['item_id'], ['result' => 'rejected', 'remark' => '再次退回'], 2),
    422,
    '非待验收状态不得验收'
);

$service->submitItem(1, $itemA['item_id'], '整改完成', 1);
$service->acceptItem(1, $itemA['item_id'], [
    'result' => 'passed',
    'remark' => '现场复核通过',
    'actual_complete_date' => $today,
    'evidence_reference' => 'YS-001',
], 2);
$closed = $service->requireItem(1, $itemA['item_id']);
closeoutCheck($closed['status'] === 'closed', '验收通过应为已消项');
closeoutCheck($closed['actual_complete_date'] === $today, '实际完成日期应保存');
$acceptances = $service->listAcceptances(1, $itemA['item_id']);
closeoutCheck(count($acceptances) === 2, '两次验收都应留痕');

closeoutExpectStatus(
    static fn () => $service->cancelItem(1, $itemA['item_id'], '试图取消已消项', 1),
    422,
    '已消项事项不得取消'
);
closeoutExpectStatus(
    static fn () => $service->updateItem(1, $itemA['item_id'], ['title' => '改名'], 1),
    422,
    '已消项事项不得修改管理信息'
);
closeoutExpectStatus(
    static fn () => $service->addProgress(1, $itemA['item_id'], '补记', 1),
    422,
    '已消项事项不得再记进度'
);

closeoutExpectStatus(
    static fn () => $service->reopenItem(1, $itemA['item_id'], '', 1),
    422,
    '重新打开必须填写原因'
);
$service->reopenItem(1, $itemA['item_id'], '验收后发现返工', 1);
closeoutCheck($service->requireItem(1, $itemA['item_id'])['status'] === 'ongoing', '重新打开应回到进行中');
closeoutCheck($service->requireItem(1, $itemA['item_id'])['actual_complete_date'] === null, '重新打开应清空实际完成日期');

// 实际完成日期早于提交时间应拒绝
$service->submitItem(1, $itemA['item_id'], '再次提交', 1);
closeoutExpectStatus(
    static fn () => $service->acceptItem(1, $itemA['item_id'], [
        'result' => 'passed',
        'actual_complete_date' => '2000-01-01',
    ], 2),
    422,
    '实际完成日期不得早于提交验收日期'
);
$service->acceptItem(1, $itemA['item_id'], ['result' => 'passed', 'actual_complete_date' => $today], 2);

// ---------------------------------------------------------------- 批量调整
$service->startItem(1, (int) $second[0]['item_id'], 1);
$batch = $service->batchUpdate(1, [$itemA['item_id'], (int) $second[0]['item_id']], [
    'planned_end_date' => $today,
    'responsible_user_id' => 3,
], 1);
closeoutCheck($batch['updated'] === 1 && $batch['skipped'] === 1, '批量调整应跳过已消项');
closeoutCheck(
    (int) $service->requireItem(1, (int) $second[0]['item_id'])['responsible_user_id'] === 3,
    '批量责任人应生效'
);
closeoutExpectStatus(
    static fn () => $service->updateItem(1, (int) $second[0]['item_id'], [
        'title' => '灯具收口',
        'responsible_user_id' => 4,
    ], 1),
    422,
    '停用账号不得作为责任人'
);
closeoutExpectStatus(
    static fn () => $service->updateItem(1, (int) $second[0]['item_id'], [
        'title' => '灯具收口',
        'responsible_supplier_id' => 99,
    ], 1),
    422,
    '不存在的责任单位应被拒绝'
);
$service->updateItem(1, (int) $second[0]['item_id'], [
    'title' => '灯具收口',
    'planned_start_date' => $today,
    'planned_end_date' => $today,
    'risk_level' => 'low',
    'responsible_supplier_id' => 2,
], 1);
closeoutCheck($service->requireItem(1, (int) $second[0]['item_id'])['title'] === '灯具收口', '管理信息应更新');

// 保留一条父材料取消历史，用于统计口径
$cancelledRoot = $service->createItems(1, $planId, [202], [], 1);
$service->cancelItem(1, (int) $cancelledRoot[0]['item_id'], '改为甲供', 1);

// -------------------------------------------------------- 计划关闭与继续执行
$service->setPlanStatus(1, $planId, 'close', 1);
closeoutCheck($service->requirePlan(1, $planId)['status'] === 'closed', '计划应可关闭');
closeoutExpectStatus(
    static fn () => $service->createItems(1, $planId, [202], [], 1),
    422,
    '已关闭计划不得新增事项'
);
$closedPlanContinue = $service->createItems(1, $draftPlanId, [202], [], 1);
$service->setPlanStatus(1, $draftPlanId, 'close', 1);
$service->startItem(1, (int) $closedPlanContinue[0]['item_id'], 1);
closeoutCheck(
    $service->requireItem(1, (int) $closedPlanContinue[0]['item_id'])['status'] === 'ongoing',
    '已关闭计划下未完成事项仍可继续执行'
);
closeoutExpectStatus(
    static fn () => $service->savePlan(1, ['plan_id' => $planId, 'plan_name' => '改名'], 1),
    422,
    '已关闭计划不得修改'
);
closeoutCheck($service->setPlanStatus(1, $planId, 'reopen', 1)['after']['status'] === 'active', '计划应可重新打开');

// ----------------------------------------------------------------- 统计口径
$stats = $service->statistics(1, $planId);
closeoutCheck($stats['counts']['closed'] === 1, '计划内应有一项已消项');
closeoutCheck($stats['counts']['cancelled'] === 1, '计划内应保留一条已取消历史');
closeoutCheck($stats['denominator'] === 2, '完成率分母应剔除已取消');
closeoutCheck($stats['completion_rate'] === 50.0, '完成率应为 50%');
closeoutCheck($stats['total'] === 3, '计划内事项总数应为 3');

// -------------------------------------------------------------- 项目数据隔离
closeoutExpectStatus(
    static fn () => $service->requireItem(2, $itemA['item_id']),
    404,
    '跨项目读取事项应 404'
);
$plan2 = $service->savePlan(2, ['plan_name' => '另一个项目的计划', 'status' => 'active'], 2);
closeoutCheck($service->requirePlan(2, $plan2)['plan_no'] === 'P2-CLO-PLAN-001', '项目编号应独立起编');
$item2 = $service->createItems(2, $plan2, [301], [], 2);
closeoutCheck($item2[0]['item_no'] === 'P2-CLO-001', '另一项目编号应独立');
closeoutExpectStatus(
    static fn () => $service->createItems(2, $planId, [302], [], 2),
    404,
    '跨项目计划不得使用'
);
closeoutExpectStatus(
    static fn () => $service->createItems(2, $plan2, [101], [], 2),
    404,
    '跨项目材料不得纳入'
);

$project2Stats = $service->statistics(2);
closeoutCheck($project2Stats['total'] === 1, '项目 2 统计不得混入项目 1 数据');

// ------------------------------------------------- 无组成关系项目回退顶层材料
closeoutCheck($service->hasParentMaterials(1), '项目 1 有父材料应走父材料口径');
closeoutCheck(!$service->hasParentMaterials(3), '项目 3 无父材料应走顶层材料回退');
$plan3 = $service->savePlan(3, ['plan_name' => '无组成项目消项计划', 'status' => 'active'], 3);
$candidateIds3 = array_map(
    static fn (array $row): int => (int) $row['material_id'],
    $service->bomCandidates(3)
);
closeoutCheck($candidateIds3 === [401, 402, 403], '无组成关系项目应列出全部顶层材料');
$items3 = $service->createItems(3, $plan3, [401, 403], [], 3);
closeoutCheck(count($items3) === 2, '无组成关系项目应可纳入顶层材料');
closeoutCheck($service->statistics(3)['total'] === 2, '回退原则下纳入的事项应正常统计');

echo "closeout service tests passed\n";
