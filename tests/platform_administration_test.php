<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Application;
use ProjectO\PlatformAdministrationService;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function platformAdminCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function platformAdminExpectStatus(callable $operation, int $status, string $message): void
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

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec(
    'CREATE TABLE System_Settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_by INTEGER,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec(
    "INSERT INTO System_Settings (setting_key, setting_value) VALUES
        ('theme', 'light'),
        ('language', 'zh-CN'),
        ('default_country_code', 'CN'),
        ('default_currency_code', 'CNY'),
        ('admin_guard', '1')"
);
$db->exec(
    'CREATE TABLE Plugin_States (
        plugin_id TEXT PRIMARY KEY,
        is_enabled INTEGER NOT NULL,
        updated_by INTEGER,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec(
    'CREATE TABLE Users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        country_code TEXT NOT NULL,
        is_active INTEGER NOT NULL
    )'
);
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, role_name TEXT UNIQUE, description TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER, PRIMARY KEY (user_id, role_id))');
$db->exec(
    'CREATE TABLE Permissions (
        permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        permission_name TEXT UNIQUE,
        display_name TEXT,
        description TEXT,
        module TEXT
    )'
);
$db->exec(
    'CREATE TABLE Role_Permissions (
        role_id INTEGER,
        permission_id INTEGER,
        PRIMARY KEY (role_id, permission_id)
    )'
);
$db->exec(
    'CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        ip_address TEXT,
        user_agent TEXT
    )'
);
$db->exec(
    "INSERT INTO Users (user_id, username, password_hash, country_code, is_active)
     VALUES (1, 'admin', 'unused-test-hash', 'CN', 1), (2, 'viewer', 'unused-test-hash', 'CN', 1)"
);
$db->exec("INSERT INTO Roles (role_id, role_name, description) VALUES (1, 'administrator', 'Platform administrator'), (2, 'viewer', 'Read-only role')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1), (2, 2)');
$db->exec(
    "INSERT INTO Permissions (permission_id, permission_name, display_name, description, module)
     VALUES (1, 'manage_platform', '管理平台', 'Manage platform', '平台管理'),
            (2, 'view_projects', '查看项目', 'View projects', 'Project Foundation')"
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1), (1, 2)');

$_SESSION = ['user_id' => 1, 'username' => 'admin'];
$_SERVER = [];
$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$admin = new PlatformAdministrationService($db, $registry);

$pageData = $admin->pageData();
platformAdminCheck(count($pageData['plugins']) === 3, 'The plugin catalog should list all discovered plugins.');
platformAdminCheck($pageData['settings']['default_currency_code'] === 'CNY', 'The default currency should be loaded.');
platformAdminCheck(
    ($pageData['currencies']['CNY'] ?? null) === 'CNY - 人民币'
        && isset($pageData['currencies']['USD']),
    'Platform currency choices should come from the standard currency catalog.'
);
$samplePermissionId = (int) $db->query(
    "SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_samples'"
)->fetchColumn();
$quotationPermissionId = (int) $db->query(
    "SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_quotations'"
)->fetchColumn();
platformAdminCheck($samplePermissionId > 0, 'Declared sample permissions must be synchronized for existing installations.');
platformAdminCheck($quotationPermissionId > 0, 'Declared quotation permissions must be selectable for sample access.');
platformAdminCheck(
    in_array($samplePermissionId, array_column($pageData['permissions'], 'permission_id'), true),
    'The sample permission must appear in role permission choices.'
);

$admin->dispatch('admin.settings.save', [
    'theme' => 'dark',
    'default_country_code' => 'us',
    'default_currency_code' => 'usd',
]);
platformAdminCheck($db->query("SELECT setting_value FROM System_Settings WHERE setting_key = 'theme'")->fetchColumn() === 'dark', 'The theme should be saved.');
platformAdminCheck($db->query("SELECT setting_value FROM System_Settings WHERE setting_key = 'default_country_code'")->fetchColumn() === 'US', 'Country codes should be normalized.');
platformAdminCheck($admin->theme() === 'dark', 'The saved theme should be applied to subsequent renders.');

platformAdminExpectStatus(
    fn () => $admin->dispatch('admin.settings.save', [
        'theme' => 'solarized',
        'default_country_code' => 'USA',
        'default_currency_code' => 'US',
    ]),
    422,
    'Unsupported settings values should be rejected.'
);
platformAdminExpectStatus(
    fn () => $admin->dispatch('admin.settings.save', [
        'theme' => 'dark',
        'default_country_code' => 'CN',
        'default_currency_code' => 'ZZZ',
    ]),
    422,
    'Currencies outside the ISO 4217 catalog must be rejected.'
);
platformAdminCheck($admin->theme() === 'dark', 'Rejected settings must not be persisted.');

platformAdminExpectStatus(
    fn () => $admin->dispatch('admin.role.update', [
        'role_id' => '1',
        'role_name' => 'administrator',
        'description' => 'Platform administrator',
        'permission_ids' => [],
    ]),
    422,
    'The last platform administrator must not be removable.'
);
platformAdminCheck(
    (int) $db->query('SELECT COUNT(*) FROM Role_Permissions WHERE role_id = 1 AND permission_id = 1')->fetchColumn() === 1,
    'A rejected permission change must roll back.'
);

$admin->dispatch('admin.role.update', [
    'role_id' => '2',
    'role_name' => 'viewer',
    'description' => 'Delegated material sample viewer',
    'permission_ids' => ['1', (string) $samplePermissionId, (string) $quotationPermissionId],
]);
$_SESSION = ['user_id' => 2, 'username' => 'viewer'];
platformAdminCheck(Support::hasPermission($db, 'manage_platform'), 'The platform management permission should be delegable.');
platformAdminCheck(Support::hasPermission($db, 'view_material_samples'), 'The sample permission should be assignable to a role.');
platformAdminCheck(Support::hasPermission($db, 'view_material_quotations'), 'Sample viewers should be able to access their quotation choices.');
$_SESSION = ['user_id' => 1, 'username' => 'admin'];
$admin->dispatch('admin.role.update', [
    'role_id' => '2',
    'role_name' => 'viewer',
    'description' => 'Read-only role',
    'permission_ids' => [],
]);

platformAdminExpectStatus(
    fn () => $admin->dispatch('admin.user.update', [
        'user_id' => '1',
        'username' => 'admin',
        'country_code' => 'CN',
        'is_active' => '0',
        'role_ids' => ['1'],
    ]),
    422,
    'The currently logged-in account must not be deactivated.'
);

$newPassword = '  long-enough-password  ';
$admin->dispatch('admin.user.create', [
    'username' => 'new-user',
    'password' => $newPassword,
    'country_code' => 'CN',
    'role_ids' => ['2'],
]);
platformAdminCheck(
    (int) $db->query("SELECT COUNT(*) FROM Users WHERE username = 'new-user' AND is_active = 1")->fetchColumn() === 1,
    'A valid account should be created and activated.'
);
platformAdminCheck(
    password_verify(
        $newPassword,
        (string) $db->query("SELECT password_hash FROM Users WHERE username = 'new-user'")->fetchColumn()
    ),
    'User passwords must be stored exactly as provided.'
);

$admin->dispatch('admin.plugin.toggle', ['plugin_id' => 'document.management', 'enabled' => '0']);
platformAdminCheck(
    (int) $db->query("SELECT is_enabled FROM Plugin_States WHERE plugin_id = 'document.management'")->fetchColumn() === 0,
    'Plugin activation state should persist.'
);
platformAdminExpectStatus(
    fn () => $admin->dispatch('admin.plugin.toggle', ['plugin_id' => 'project.foundation', 'enabled' => '0']),
    422,
    'A required plugin must not be deactivated.'
);
$disabledRegistry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
platformAdminCheck($disabledRegistry->dispatch('GET', 'issues', []) === null, 'Disabled plugin routes must not be registered.');

$_SESSION = [
    'user_id' => 1,
    'username' => 'admin',
    'csrf_token' => 'test-token',
];
$_GET = ['page' => 'admin'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
(new Application($db))->run();
$adminHtml = ob_get_clean();
platformAdminCheck(str_contains($adminHtml, '<h1>后台管理</h1>'), 'The authorized admin page should render.');
platformAdminCheck(str_contains($adminHtml, 'PHP 版本'), 'The admin page should show safe runtime diagnostics.');
platformAdminCheck(
    str_contains($adminHtml, 'value="' . $samplePermissionId . '"')
        && str_contains($adminHtml, '查看材料样品'),
    'The admin page must render a selectable sample-management permission.'
);
platformAdminCheck(!str_contains($adminHtml, 'password_hash'), 'The admin page must not expose password data.');

$_SESSION = ['user_id' => 2, 'username' => 'viewer'];
platformAdminExpectStatus(
    fn () => $admin->pageData(),
    403,
    'Users without manage_platform must not access the administration data.'
);
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
(new Application($db))->run();
$deniedHtml = ob_get_clean();
platformAdminCheck(http_response_code() === 403, 'The admin route must reject users without the permission.');
platformAdminCheck(!str_contains($deniedHtml, '平台管理设置'), 'Denied users must not receive admin data.');

echo "Platform administration tests passed.\n";
