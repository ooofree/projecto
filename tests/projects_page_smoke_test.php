<?php
declare(strict_types=1);

use ProjectO\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY, project_name TEXT, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER, archived_by INTEGER, archived_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (entity_type, entity_id))');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, is_active INTEGER)');
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
        ('admin_guard', '1')"
);
$db->exec("INSERT INTO Users (user_id, is_active) VALUES (1, 1)");
$db->exec('INSERT INTO Roles (role_id) VALUES (1)');
$db->exec(
    "INSERT INTO Permissions (permission_id, permission_name) VALUES
        (1, 'view_projects'),
        (2, 'view_material_samples'),
        (3, 'view_material_quotations'),
        (4, 'delete_projects')"
);
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1), (1, 2), (1, 3), (1, 4)');
$db->exec(
    "INSERT INTO Projects (project_id, project_name, start_date, end_date, status)
     VALUES (1, '<script>alert(1)</script>', NULL, NULL, 'planning')"
);

$_SESSION = [
    'user_id' => 1,
    'username' => 'admin',
    'active_project_id' => 1,
    'csrf_token' => 'smoke-test-token',
];
$_GET = ['page' => 'projects'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
(new Application($db))->run();
$html = ob_get_clean();

if (
    !str_contains($html, '当前工作项目')
    || !str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
    || str_contains($html, '<script>alert(1)</script>')
    || !str_contains($html, '全部项目')
    || !str_contains($html, 'class="project-card is-current"')
    || !str_contains($html, 'href="?page=samples"')
    || !str_contains($html, '样品跟踪')
    || !str_contains($html, '删除 / 归档')
    || str_contains($html, 'href="?page=spaces"')
) {
    throw new RuntimeException('Projects page context, deletion controls, escaping, or permission display check failed.');
}

http_response_code(200);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'smoke-test-token',
    'action' => 'project.create',
    'project_name' => 'Unauthorized project',
    'status' => 'planning',
];
ob_start();
(new Application($db))->run();
$deniedHtml = ob_get_clean();

if (http_response_code() !== 403 || !str_contains($deniedHtml, '没有访问权限')) {
    throw new RuntimeException('Project creation without its permission should be denied.');
}
if ((int) $db->query('SELECT COUNT(*) FROM Projects')->fetchColumn() !== 1) {
    throw new RuntimeException('A denied project creation request must not write data.');
}

echo "Projects page smoke test passed.\n";
