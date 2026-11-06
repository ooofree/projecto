<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\ProjectFoundation\ProjectFoundationPlugin;
use ProjectO\Plugin\PluginContext;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY AUTOINCREMENT, project_name TEXT, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, project_id INTEGER, archived_by INTEGER, archived_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (entity_type, entity_id))');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, is_active INTEGER, timezone TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY, permission_name TEXT)');
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT)');
$db->exec('INSERT INTO Users (user_id, is_active) VALUES (1, 1)');
$db->exec('INSERT INTO Roles (role_id) VALUES (1)');
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec("INSERT INTO Permissions (permission_id, permission_name) VALUES (1, 'view_projects'), (2, 'create_projects'), (3, 'delete_projects')");
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1), (1, 2), (1, 3)');

$_SESSION = ['user_id' => 1, 'username' => 'admin'];
$_SERVER = [];
$moduleDirectory = dirname(__DIR__) . '/modules/project-foundation';
$plugin = new ProjectFoundationPlugin(new PluginContext($db, $moduleDirectory));
$response = $plugin->dispatch('create', [
    'project_name' => 'Project O Site',
    'start_date' => '2026-03-01',
    'end_date' => '2026-11-30',
    'status' => 'planning',
]);

if ($response->type !== 'redirect' || $response->location !== '?page=projects') {
    throw new RuntimeException('Project creation should return to the project list.');
}
if ((int) $_SESSION['active_project_id'] !== 1) {
    throw new RuntimeException('Newly created project should become the active project.');
}
if ((int) $db->query('SELECT COUNT(*) FROM Projects')->fetchColumn() !== 1) {
    throw new RuntimeException('Project plugin should persist the created project.');
}
if ((int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() !== 2) {
    throw new RuntimeException('Project creation and selection should both be audited.');
}

$pageResponse = $plugin->dispatch('index', []);
if ($pageResponse->type !== 'view' || !is_file((string) $pageResponse->viewPath)) {
    throw new RuntimeException('Project plugin should resolve its own page template.');
}
$renderSpacesPage = static function (bool $archivedView): string {
    $escape = [Support::class, 'escape'];
    $currentProject = ['project_name' => 'Project O Site'];
    $buildings = [];
    $canManage = false;
    $csrfToken = 'test-token';
    $title = '项目空间';
    $theme = 'light';
    $username = '';
    $canManagePlatform = false;
    $flash = null;
    ob_start();
    require dirname(__DIR__) . '/modules/project-foundation/views/spaces.php';
    return (string) ob_get_clean();
};
$currentSpacesPage = $renderSpacesPage(false);
$archivedSpacesPage = $renderSpacesPage(true);
if (
    !str_contains($currentSpacesPage, 'href="?page=projects">项目首页</a>')
    || str_contains($currentSpacesPage, 'href="?page=spaces&amp;archived=1" aria-current="page"')
    || !str_contains($currentSpacesPage, 'href="?page=spaces" aria-current="page"')
    || !str_contains($archivedSpacesPage, 'href="?page=projects">项目首页</a>')
    || !str_contains($archivedSpacesPage, 'href="?page=spaces&amp;archived=1" aria-current="page"')
    || !str_contains($archivedSpacesPage, 'href="?page=spaces">当前空间</a>')
) {
    throw new RuntimeException('Project-space pages should expose project-home and in-module navigation.');
}

try {
    $plugin->dispatch('select', ['project_id' => ['1']]);
    throw new RuntimeException('Malformed project IDs should be rejected.');
} catch (HttpException $exception) {
    if ($exception->statusCode !== 422) {
        throw $exception;
    }
}
$deleteResponse = $plugin->dispatch('project.delete', ['project_id' => 1]);
if (
    $deleteResponse->type !== 'redirect'
    || (int) $db->query('SELECT COUNT(*) FROM Projects')->fetchColumn() !== 0
    || isset($_SESSION['active_project_id'])
) {
    throw new RuntimeException('An empty project should be deleted and removed from the current project context.');
}

echo "Project foundation plugin tests passed.\n";
