<?php
declare(strict_types=1);

// 文档管理首页（工程总览）冒烟：真实 Application 路由渲染，钉住三件事：
// 1) 首页比率与过程节点必须由真实记录推算（闭环率/推进目标/过程节点），不凭空出现；
// 2) 首页不再横向溢出（约束网格 + 标题省略的 CSS 契约仍在）；
// 3) 空项目仍能正常渲染、给出建单入口。
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
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, display_name TEXT, password_hash TEXT, country_code TEXT, is_active INTEGER, timezone TEXT)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY, role_name TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, permission_name TEXT UNIQUE, display_name TEXT, description TEXT, module TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
$db->exec('CREATE TABLE System_Settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT)');
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, project_id INTEGER, entity_type TEXT, entity_id INTEGER, action TEXT, details TEXT)');
$db->exec('CREATE TABLE Buildings (building_id INTEGER PRIMARY KEY, project_id INTEGER, building_number TEXT, building_name TEXT)');
$db->exec('CREATE TABLE Building_Floors (floor_id INTEGER PRIMARY KEY, building_id INTEGER, floor_number TEXT, floor_name TEXT, usage_type TEXT)');
$db->exec('CREATE TABLE Construction_Areas (area_id INTEGER PRIMARY KEY, building_id INTEGER, area_name TEXT, description TEXT)');
$db->exec('CREATE TABLE Rooms (room_id INTEGER PRIMARY KEY, building_id INTEGER, floor_id INTEGER, room_number TEXT, room_type TEXT, room_area REAL, room_height REAL, room_status TEXT)');
$db->exec('CREATE TABLE document_issues (issue_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, issue_number TEXT, description TEXT, severity TEXT DEFAULT \'medium\', status TEXT DEFAULT \'open\', location TEXT, location_type TEXT, location_building_id INTEGER, location_floor_id INTEGER, location_area_id INTEGER, location_room_id INTEGER, location_note TEXT, reported_by INTEGER, reported_at TEXT, resolution_result TEXT, verification_status TEXT, verification_note TEXT, evidence_reference TEXT, closed_at TEXT, closed_by INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE document_issue_status_history (history_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, issue_id INTEGER, old_status TEXT, new_status TEXT, reason TEXT, evidence_reference TEXT, transition_data TEXT, changed_by INTEGER, changed_at TEXT)');
$db->exec('CREATE TABLE document_issue_follow_ups (follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, issue_id INTEGER, title TEXT, is_completed INTEGER DEFAULT 0, created_by INTEGER, completed_by INTEGER, completed_at TEXT, created_at TEXT)');
$db->exec('CREATE TABLE document_issue_follow_up_history (history_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, follow_up_id INTEGER, old_status TEXT, new_status TEXT, reason TEXT, evidence_reference TEXT, changed_by INTEGER, changed_at TEXT)');
$db->exec('CREATE TABLE document_rfis (rfi_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, primary_issue_id INTEGER, rfi_number TEXT, assigned_to INTEGER, priority TEXT DEFAULT \'medium\', due_date TEXT, description TEXT, status TEXT DEFAULT \'draft\', submitted_by INTEGER, submitted_at TEXT, response_text TEXT, response_complete INTEGER DEFAULT 0, evidence_required INTEGER DEFAULT 0, evidence_reference TEXT, responded_at TEXT, closed_at TEXT, closed_by INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE document_rfi_status_history (history_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, rfi_id INTEGER, old_status TEXT, new_status TEXT, reason TEXT, evidence_reference TEXT, transition_data TEXT, changed_by INTEGER, changed_at TEXT)');
$db->exec('CREATE TABLE document_rfi_related_issues (project_id INTEGER, rfi_id INTEGER, issue_id INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE document_rfi_follow_ups (follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, rfi_id INTEGER, title TEXT, is_completed INTEGER DEFAULT 0, created_by INTEGER, completed_by INTEGER, completed_at TEXT, created_at TEXT)');
$db->exec('CREATE TABLE document_rfi_follow_up_history (history_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, follow_up_id INTEGER, old_status TEXT, new_status TEXT, reason TEXT, evidence_reference TEXT, changed_by INTEGER, changed_at TEXT)');
$db->exec('CREATE TABLE document_issue_images (image_id INTEGER PRIMARY KEY AUTOINCREMENT, issue_id INTEGER, project_id INTEGER, object_key TEXT, external_url TEXT, original_name TEXT, content_type TEXT, file_size INTEGER, uploaded_by INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE document_rfi_images (image_id INTEGER PRIMARY KEY AUTOINCREMENT, rfi_id INTEGER, project_id INTEGER, object_key TEXT, external_url TEXT, original_name TEXT, content_type TEXT, file_size INTEGER, uploaded_by INTEGER, created_at TEXT)');
$db->exec('CREATE TABLE document_number_sequences (project_id INTEGER, document_type TEXT, last_number INTEGER DEFAULT 0)');

$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, '杭州测试项目', 'ongoing'), (2, '空项目', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, password_hash, country_code, is_active) VALUES (1, 'admin', 'hash', 'CN', 1)");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('theme','light'),('language','zh-CN'),('default_country_code','CN'),('default_currency_code','CNY'),('timezone','Asia/Shanghai')");
$db->exec("INSERT INTO Buildings (building_id, project_id, building_number, building_name) VALUES (1, 1, 'A', 'A 座')");
$db->exec("INSERT INTO Building_Floors (floor_id, building_id, floor_number, floor_name, usage_type) VALUES (1, 1, '03', '三层', 'office')");
$db->exec("INSERT INTO Rooms (room_id, building_id, floor_id, room_number) VALUES (1, 1, 1, '301')");

// 注册全部插件权限并授予角色 1。
$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$permIns = $db->prepare('INSERT INTO Permissions (permission_name, display_name, description, module) VALUES (:n, :d, :desc, :m)');
$roleIns = $db->prepare('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, :pid)');
$granted = [];
foreach ($registry->permissions() as $p) {
    $permIns->execute(['n' => $p['name'], 'd' => $p['display_name'], 'desc' => $p['description'] ?? '', 'm' => $p['module'] ?? '']);
    $roleIns->execute(['pid' => (int) $db->lastInsertId()]);
    $granted[] = $p['name'];
}
foreach (['view_projects', 'view_issues', 'create_issues', 'manage_issues'] as $extra) {
    if (!in_array($extra, $granted, true)) {
        $permIns->execute(['n' => $extra, 'd' => $extra, 'desc' => '', 'm' => 'document.management']);
        $roleIns->execute(['pid' => (int) $db->lastInsertId()]);
    }
}

$issueIns = $db->prepare('INSERT INTO document_issues (project_id, issue_number, description, severity, status, location_room_id, reported_by, reported_at, closed_at, created_at, updated_at) VALUES (1,?,?,?,?,1,1,?,?,?,?)');
$issueRows = [
    // number, description, severity, status, closed_at, updated_at
    ['P1-ISS-001', '幕墙预埋件与结构梁位置冲突，需设计确认补强方案。', 'high', 'open', null, '2026-11-23 09:20:00'],
    ['P1-ISS-002', '三层茶水间给排水点位与橱柜深化图不一致。', 'medium', 'closed', '2026-11-22 10:00:00', '2026-11-22 10:00:00'],
    ['P1-ISS-003', '石材门套基层受潮变形，需更换并排查渗水。', 'low', 'closed', '2026-11-23 16:00:00', '2026-11-23 16:00:00'],
    ['P1-ISS-004', '消防喷淋头与灯槽位置重叠，需二次深化。', 'high', 'resolved', null, '2026-11-21 16:10:00'],
];
foreach ($issueRows as [$number, $description, $severity, $status, $closedAt, $updatedAt]) {
    $issueIns->execute([$number, $description, $severity, $status, $updatedAt, $closedAt, $updatedAt, $updatedAt]);
}
$db->exec("INSERT INTO document_issue_follow_ups (project_id, issue_id, title, is_completed, created_by, completed_at) VALUES (1, 2, '复查排水立管位置', 1, 1, '2026-11-22 10:30:00')");

$rfiIns = $db->prepare('INSERT INTO document_rfis (project_id, primary_issue_id, rfi_number, assigned_to, priority, due_date, description, status, submitted_by, submitted_at, created_at, updated_at) VALUES (1,1,?,1,?,?,?,?,1,?,?,?)');
$rfiRows = [
    ['P1-RFI-001', 'high', '2026-11-01', '预埋件偏差能否按节点补强处理？', 'submitted', '2026-11-20 09:00:00'],
    ['P1-RFI-002', 'medium', '2026-11-30', '排水立管可否平移 150mm？', 'responded', '2026-11-23 11:00:00'],
    ['P1-RFI-003', 'low', '2026-12-05', '喷淋与灯槽点位如何取舍？', 'draft', '2026-11-21 15:20:00'],
];
foreach ($rfiRows as [$number, $priority, $dueDate, $description, $status, $updatedAt]) {
    $rfiIns->execute([$number, $priority, $dueDate, $description, $status, $updatedAt, $updatedAt, $updatedAt]);
}

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1, 'csrf_token' => 'docs-home-token'];

function renderDocsHome(PDO $db, int $projectId = 1): string
{
    $_SESSION['active_project_id'] = $projectId;
    $_GET = ['page' => 'documents'];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    (new Application($db))->run();

    return (string) ob_get_clean();
}

function assertDocsHome(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectDocsHome(string $html, string $needle, string $message): void
{
    if (!str_contains($html, $needle)) {
        throw new RuntimeException($message . '（缺「' . $needle . '」）');
    }
}

$html = renderDocsHome($db);
assertDocsHome(str_contains($html, 'doc-rate'), '首页缺少综合闭环率卡（doc-rate）');
assertDocsHome(str_contains($html, 'app-sidebar') && str_contains($html, '<html'), '首页未套全局布局');
assertDocsHome(!str_contains($html, 'Warning:') && !str_contains($html, 'Fatal error'), '首页渲染出现 PHP 警告');

// 综合闭环率 =（2 条已关闭问题 + 0 份已关闭 RFI）/（4 条问题 + 3 份 RFI）= 2/7 ≈ 29%。
expectDocsHome($html, 'role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="29"', '综合闭环率不是由真实记录推算的 2/7');
expectDocsHome($html, '已闭环 2 / 7 条记录', '闭环率副文案与真实记录不一致');
expectDocsHome($html, '综合闭环率', '缺少「综合闭环率」标题');

// 页面不出现游戏化命名（等级、经验值、成就、冲刺、下一手）。
foreach (['XP', 'Lv.'] as $gameWord) {
    assertDocsHome(!str_contains($html, $gameWord), '首页仍残留游戏化命名：' . $gameWord);
}
expectDocsHome($html, '推进目标', '缺少「推进目标」区块标题');
expectDocsHome($html, '优先待办', '缺少「优先待办」区块标题');

// 三张推进目标卡：问题 2/4、RFI 1/3、复核收口 2/3。
expectDocsHome($html, '2 / 4', '问题闭环目标未按真实数据计数');
expectDocsHome($html, '1 / 3', 'RFI 应答目标未按真实数据计数');
expectDocsHome($html, '2 / 3', '复核收口目标未按真实数据计数');

// 过程节点：只应达成「首次闭环」，同日各闭 1 条不应误判「单日闭环」。
expectDocsHome($html, '过程节点', '缺少「过程节点」区块');
expectDocsHome($html, '1/5 已完成', '过程节点达成数量与真实记录不符');
expectDocsHome($html, '首次闭环', '过程节点缺少首次闭环');
expectDocsHome($html, '单日闭环', '过程节点缺少单日闭环');

// 「优先待办」排序：逾期 RFI 必须排在紧急问题之前，并显式标注逾期。
$queueHtml = '';
if (preg_match('#<ul class="doc-next">(.*?)</ul>#s', $html, $match) === 1) {
    $queueHtml = $match[1];
}
assertDocsHome($queueHtml !== '', '首页缺少「优先待办」队列');
assertDocsHome(str_contains($queueHtml, '逾期'), '逾期 RFI 未被标注');
assertDocsHome(
    strpos($queueHtml, 'P1-RFI-001') !== false && strpos($queueHtml, 'P1-RFI-001') < strpos($queueHtml, 'P1-ISS-001'),
    '队列未把逾期 RFI 排在前面'
);
// 待关闭问题也要进「优先待办」，否则复核收口目标只能靠用户自己想起来。
assertDocsHome(str_contains($queueHtml, 'P1-ISS-004'), '待关闭问题未进入「优先待办」队列');
assertDocsHome(str_contains($queueHtml, '待复核'), '待关闭问题缺少复核提示');

// 最近动态只展示 6 条以内，且问题/RFI 混合。
assertDocsHome(substr_count($html, 'doc-feed-row') <= 6, '最近动态超过 6 条，首页又被流水账撑高');

// 横向溢出契约：所有队列/列表的网格必须能收缩，标题必须可换行/省略。
// 旧版首页正是 <ul> 的 auto 轨道按 nowrap 文案取 max-content，把页面顶宽 233px。
$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
assertDocsHome(
    preg_match('/\.doc-next\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/', $css) === 1,
    'doc-next 缺少 minmax(0, 1fr) 轨道约束，长标题会再次顶宽页面'
);
assertDocsHome(
    preg_match('/\.doc-feed-row\s*\{[^}]*flex-wrap:\s*wrap/', $css) === 1,
    'doc-feed-row 未允许换行，窄屏会溢出'
);
assertDocsHome(
    preg_match('/\.doc-goals\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/', $css) === 1,
    'doc-goals 缺少 minmax(0, 1fr) 轨道约束'
);
assertDocsHome(!str_contains($css, '.work-queue'), '旧的 work-queue 样式仍残留，可能被误用');
$view = (string) file_get_contents(dirname(__DIR__) . '/modules/document-management/views/documents.php');
assertDocsHome(!str_contains($view, 'work-queue'), '首页视图仍引用旧的 work-queue 结构');

// 空项目：不出现假进度、假节点，并给出建单入口。
$emptyHtml = renderDocsHome($db, 2);
expectDocsHome($emptyHtml, '暂无记录', '空项目目标卡未显示暂无记录');
expectDocsHome($emptyHtml, '当前没有待推进的问题或 RFI。', '空项目队列空状态缺失');
expectDocsHome($emptyHtml, '登记问题', '空项目缺少建单入口');
assertDocsHome(str_contains($emptyHtml, '0/5 已完成'), '空项目不应达成任何过程节点');
assertDocsHome(!str_contains($emptyHtml, '已闭环 0 / 0 条记录'), '空项目不应显示 0/0 的闭环率副文案');

// 无权限：没有 view_issues 的账号不得看到工程总览，也不得泄露项目记录。
$db->exec("INSERT INTO Users (user_id, username, password_hash, country_code, is_active) VALUES (2, 'viewer', 'hash', 'CN', 1)");
$_SESSION = ['user_id' => 2, 'username' => 'viewer', 'active_project_id' => 1, 'csrf_token' => 'docs-home-token'];
$_GET = ['page' => 'documents'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    (new Application($db))->run();
} catch (\Throwable) {
    // 403 由全局异常处理渲染；CLI 下也可能直接抛出，两种情况都不应泄露数据。
}
$deniedHtml = (string) ob_get_clean();
assertDocsHome(
    !str_contains($deniedHtml, 'P1-ISS-001') && !str_contains($deniedHtml, 'P1-RFI-001'),
    '无权限账号仍能读到项目文档记录'
);

echo "Documents home smoke passed.\n";
