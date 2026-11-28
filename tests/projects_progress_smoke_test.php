<?php
declare(strict_types=1);

use ProjectO\Application;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY, project_name TEXT, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER, archived_by INTEGER, archived_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (entity_type, entity_id))');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, is_active INTEGER, timezone TEXT)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY, permission_name TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
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
$db->exec(
    "INSERT INTO Permissions (permission_id, permission_name) VALUES
        (1, 'view_projects'),
        (2, 'delete_projects'),
        (5, 'view_closeouts')"
);
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1), (1, 2), (1, 5)');

// Closeout tables the project-home progress aggregate reads (read-only).
$db->exec('CREATE TABLE project_closeout_plans (plan_id INTEGER PRIMARY KEY, project_id INTEGER, status TEXT)');
$db->exec('CREATE TABLE project_closeout_items (item_id INTEGER PRIMARY KEY, project_id INTEGER, plan_id INTEGER, material_id INTEGER, status TEXT)');
$db->exec('CREATE TABLE project_closeout_locations (location_id INTEGER PRIMARY KEY, project_id INTEGER, item_id INTEGER, status TEXT, baseline_qty NUMERIC)');

$today = Support::nowLocal('Y-m-d');
$start = (new DateTimeImmutable($today))->modify('-45 days')->format('Y-m-d');
$end = (new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');

// Project 1: 60% of the工期 elapsed but only 20% of the quantity verified →
// must be flagged red. Project 2: 70% verified against the same window → normal.
// Project 3: no closeout plan at all → no bar.
$db->exec("INSERT INTO Projects (project_id, project_name, start_date, end_date, status) VALUES
    (1, '火山引擎展厅', '{$start}', '{$end}', 'ongoing'),
    (2, 'Coding办公室装修', '{$start}', '{$end}', 'ongoing'),
    (3, '待启动项目', NULL, NULL, 'planning')");
$db->exec("INSERT INTO project_closeout_plans (plan_id, project_id, status) VALUES (1, 1, 'active'), (2, 2, 'active')");
$db->exec("INSERT INTO project_closeout_items (item_id, project_id, plan_id, material_id, status) VALUES
    (1, 1, 1, 10, 'closed'), (2, 1, 1, 11, 'closed'), (3, 1, 1, 12, 'pending_accept'),
    (4, 1, 1, 13, 'ongoing'), (5, 1, 1, 14, 'pending'), (6, 1, 1, 15, 'cancelled'),
    (7, 2, 2, 20, 'closed'), (8, 2, 2, 21, 'closed'), (9, 2, 2, 22, 'closed'), (10, 2, 2, 23, 'pending')");
$db->exec("INSERT INTO project_closeout_locations (location_id, project_id, item_id, status, baseline_qty) VALUES
    (1, 1, 1, 'closed', 20), (2, 1, 2, 'pending_accept', 8), (3, 1, 3, 'ongoing', 72),
    (4, 2, 7, 'closed', 70), (5, 2, 8, 'ongoing', 30)");

$_SESSION = [
    'user_id' => 1,
    'username' => 'admin',
    'active_project_id' => 1,
    'csrf_token' => 'smoke-test-token',
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['page' => 'projects'];
$_POST = [];

ob_start();
(new Application($db))->run();
$html = ob_get_clean();

$fail = static function (string $message): void {
    throw new RuntimeException($message);
};

if (substr_count($html, 'data-project-progress') !== 2) {
    $fail('Only the two projects with closeout data should render a progress control.');
}
if (!str_contains($html, 'class="project-progress tone-danger"')) {
    $fail('A project behind its工期 must render the danger tone.');
}
if (!str_contains($html, 'class="project-progress tone-ok"')) {
    $fail('A project ahead of its工期 must render the healthy tone.');
}
if (!str_contains($html, '20.0%')) {
    $fail('Verified quantity rate (20/100) should be shown on the late card.');
}
if (!str_contains($html, '70.0%')) {
    $fail('Verified quantity rate (70/100) should be shown on the healthy card.');
}
if (!str_contains($html, '已消项 2/5 项 · 工期已过 60% · 剩 30 天')) {
    $fail('The card should carry item counts,工期 share and days left on one meta line.');
}
if (!str_contains($html, '已消项 3/4 项 · 工期已过 60% · 剩 30 天')) {
    $fail('Cancelled items must stay out of the item denominator.');
}
if (!str_contains($html, 'name="return" value="closeouts"')) {
    $fail('Clicking the progress bar must select the project and land on the closeout workspace.');
}
if (!str_contains($html, 'aria-label="进入火山引擎展厅的工程消项工作台，当前进度 20.0%，预警"')) {
    $fail('The progress control needs a descriptive accessible name.');
}
if (!str_contains($html, 'project-progress-target')) {
    $fail('The card bar should mark the schedule position.');
}

// Without view_closeouts the aggregate is not even computed, so every card
// loses the progress block (no half-visible numbers).
$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 5');
ob_start();
(new Application($db))->run();
$hiddenHtml = ob_get_clean();
if (str_contains($hiddenHtml, 'data-project-progress') || str_contains($hiddenHtml, '消项进度')) {
    $fail('A viewer without view_closeouts must not see closeout progress.');
}

echo "Projects progress smoke test passed.\n";
