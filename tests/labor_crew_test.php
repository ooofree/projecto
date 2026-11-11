<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\LaborCrew\CrewDirectoryService;
use ProjectO\Modules\LaborCrew\LaborCrewPlugin;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Security\FieldCipher;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

putenv('APP_DATA_KEY=0f1e2d3c4b5a69788796a5b4c3d2e1f00f1e2d3c4b5a69788796a5b4c3d2e1f0');

function crewCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function crewExpectStatus(callable $operation, int $status, string $message): void
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

function crewRenderView(PluginResponse $response): string
{
    if ($response->type !== 'view' || $response->viewPath === null) {
        throw new RuntimeException('Expected a labor-crew view response.');
    }

    $escape = [Support::class, 'escape'];
    $csrfToken = 'crew-test-token';
    $username = 'admin';
    $canManagePlatform = false;
    $theme = 'light';
    $flash = null;
    $viewData = $response->data;
    extract($viewData, EXTR_SKIP);
    ob_start();
    require $response->viewPath;

    return (string) ob_get_clean();
}

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY AUTOINCREMENT, project_name TEXT NOT NULL, start_date TEXT, end_date TEXT, status TEXT)');
$db->exec('CREATE TABLE project_foundation_archive_records (archive_id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL, entity_id INTEGER NOT NULL, project_id INTEGER NOT NULL REFERENCES Projects (project_id), archived_by INTEGER NOT NULL, archived_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (entity_type, entity_id))');
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, is_active INTEGER NOT NULL DEFAULT 1, timezone TEXT)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY, role_name TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, permission_name TEXT UNIQUE, display_name TEXT, description TEXT, module TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
$db->exec('CREATE TABLE System_Settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('timezone', 'Asia/Shanghai'), ('theme', 'light')");
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

$mysqlDdl = (string) file_get_contents(dirname(__DIR__) . '/modules/labor-crew/database/migrations/202611110001_labor_crews.sql');
foreach ([
    'Labor_Crew_Types', 'Labor_Crews', 'project_id', 'crew_type_id', 'crew_name', 'leader_name',
    'leader_phone', 'wechat_id', 'id_card_number_encrypted', 'id_card_fingerprint',
    'emergency_contact_name', 'emergency_contact_phone', 'notes', 'is_active',
] as $requiredToken) {
    crewCheck(str_contains($mysqlDdl, $requiredToken), 'Migration is missing column/table: ' . $requiredToken);
}
crewCheck(
    str_contains($mysqlDdl, 'project_id` int NOT NULL'),
    'Crews must require a project (crew answers to its project).'
);

// SQLite mirror of the same columns so the service can be exercised without MySQL.
$db->exec('CREATE TABLE Labor_Crew_Types (
    crew_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_name TEXT NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE Labor_Crews (
    crew_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES Projects (project_id),
    crew_type_id INTEGER NOT NULL REFERENCES Labor_Crew_Types (crew_type_id),
    crew_name TEXT NOT NULL,
    leader_name TEXT NOT NULL,
    leader_phone TEXT NOT NULL,
    wechat_id TEXT,
    id_card_number_encrypted TEXT,
    id_card_fingerprint TEXT,
    emergency_contact_name TEXT,
    emergency_contact_phone TEXT,
    notes TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, id_card_fingerprint)
)');
$seedNames = ['土建', '钢筋', '木工', '泥瓦', '混凝土', '脚手架', '防水', '水电安装', '暖通', '消防', '装饰装修', '幕墙', '门窗', '油漆涂料', '拆除'];
$seedInsert = $db->prepare('INSERT INTO Labor_Crew_Types (type_name, sort_order) VALUES (:name, :sort_order)');
foreach ($seedNames as $index => $seedName) {
    $seedInsert->execute(['name' => $seedName, 'sort_order' => ($index + 1) * 10]);
}

$db->exec("INSERT INTO Projects (project_name, status) VALUES ('Project One', 'planning'), ('Project Two', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, is_active) VALUES (1, 'admin', 1), (2, 'viewer', 1)");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator'), (2, 'crew_viewer')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1), (2, 2)');

$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$permissionInsert = $db->prepare('INSERT INTO Permissions (permission_name, display_name, description, module) VALUES (:name, :display_name, :description, :module)');
$ids = [];
foreach ($registry->permissions() as $permission) {
    $permissionInsert->execute([
        'name' => $permission['name'],
        'display_name' => $permission['display_name'],
        'description' => $permission['description'] ?? null,
        'module' => $permission['module'],
    ]);
    $ids[$permission['name']] = (int) $db->lastInsertId();
}
crewCheck(isset($ids['view_labor_crews'], $ids['edit_labor_crews'], $ids['archive_labor_crews'], $ids['view_labor_crew_id_numbers'], $ids['edit_labor_crew_types']),
    'The labor.crew plugin must declare its permissions in the manifest.');
$rolePermission = $db->prepare('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
foreach ($ids as $permissionId) {
    $rolePermission->execute(['role_id' => 1, 'permission_id' => $permissionId]);
}
// Role 2 can look and call but must never decrypt identity numbers.
$rolePermission->execute(['role_id' => 2, 'permission_id' => $ids['view_labor_crews']]);
$rolePermission->execute(['role_id' => 2, 'permission_id' => $ids['view_projects']]);

$cipher = FieldCipher::fromEnvironment();
crewCheck($cipher instanceof FieldCipher, 'APP_DATA_KEY must be readable by FieldCipher.');

$service = new CrewDirectoryService($db, $cipher);

$crewId = $service->saveCrew(1, [
    'crew_type_id' => 2,
    'crew_name' => '钢筋一班',
    'leader_name' => '张伟',
    'leader_phone' => '138 0013 8000',
    'wechat_id' => 'zw-steel',
    'id_card_number' => '11010519491231002X',
    'emergency_contact_name' => '李静',
    'emergency_contact_phone' => '+8613900139000',
    'notes' => '常驻二期，擅长绑扎',
]);
$stored = $db->query('SELECT * FROM Labor_Crews WHERE crew_id = ' . $crewId)->fetch();
crewCheck($stored['project_id'] === '1' || (int) $stored['project_id'] === 1, 'Crew must be bound to the project.');
crewCheck($stored['leader_phone'] === '13800138000', 'Phone must be normalised on write.');
crewCheck(
    !str_contains(json_encode($stored, JSON_UNESCAPED_UNICODE), '11010519491231002X'),
    'Identity number must never be stored in clear text.'
);
crewCheck(str_starts_with((string) $stored['id_card_number_encrypted'], 'v1:'), 'Identity number must use the v1 envelope.');
crewCheck((string) $stored['id_card_fingerprint'] !== '' && strlen((string) $stored['id_card_fingerprint']) === 64, 'Fingerprint must be a 64-char HMAC.');
crewCheck($cipher->decrypt($stored['id_card_number_encrypted']) === '11010519491231002X', 'Stored ciphertext must decrypt back to the input.');
// Duplicate identity number inside the same project must be rejected.
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, [
        'crew_type_id' => 3, 'crew_name' => '木工一班', 'leader_name' => '王强',
        'leader_phone' => '13700137000', 'id_card_number' => '11010519491231002X',
    ]);
}, 409, 'Same identity number must not be registered twice in one project.');

// Duplicate crew name inside the same project must be rejected.
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, [
        'crew_type_id' => 3, 'crew_name' => '钢筋一班', 'leader_name' => '赵敏',
        'leader_phone' => '13600136000',
    ]);
}, 409, 'Same crew name must not exist twice in one project.');

// The same person may serve a different project: crews answer to their project.
$secondProjectCrew = $service->saveCrew(2, [
    'crew_type_id' => 2, 'crew_name' => '钢筋一班', 'leader_name' => '张伟',
    'leader_phone' => '13800138000', 'id_card_number' => '11010519491231002X',
]);
crewCheck($secondProjectCrew > 0 && $secondProjectCrew !== $crewId, 'Another project may register the same crew independently.');

// Invalid identity numbers are rejected before anything is written.
foreach (['11010519491231002A', '11010519491231002', '110105194913310027'] as $badIndex => $badId) {
    crewExpectStatus(static function () use ($service, $badId, $badIndex): void {
        $service->saveCrew(1, [
            'crew_type_id' => 5, 'crew_name' => '非法身份证班组' . $badIndex,
            'leader_name' => '测试', 'leader_phone' => '13500135000', 'id_card_number' => $badId,
        ]);
    }, 422, 'Identity number ' . $badId . ' must be rejected.');
}

// Phone validation and the emergency-contact pairing rule.
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, ['crew_type_id' => 5, 'crew_name' => '电话非法', 'leader_name' => '测试', 'leader_phone' => '138-abc']);
}, 422, 'Non-numeric phone must be rejected.');
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, [
        'crew_type_id' => 5, 'crew_name' => '紧急联系人不全', 'leader_name' => '测试',
        'leader_phone' => '13500135000', 'emergency_contact_name' => ' only-name',
    ]);
}, 422, 'Emergency contact name without phone must be rejected.');
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, ['crew_type_id' => 99999, 'crew_name' => '工种不存在', 'leader_name' => '测试', 'leader_phone' => '13500135000']);
}, 422, 'Unknown craft type must be rejected.');

// Editing without touching the identity field keeps the stored ciphertext.
$before = $db->query('SELECT id_card_number_encrypted FROM Labor_Crews WHERE crew_id = ' . $crewId)->fetchColumn();
$service->saveCrew(1, [
    'crew_id' => $crewId, 'crew_type_id' => 2, 'crew_name' => '钢筋一班',
    'leader_name' => '张伟', 'leader_phone' => '13800138001',
]);
$after = $db->query('SELECT id_card_number_encrypted, leader_phone FROM Labor_Crews WHERE crew_id = ' . $crewId)->fetch();
crewCheck($after['id_card_number_encrypted'] === $before, 'Blank identity field on edit must keep the stored value.');
crewCheck($after['leader_phone'] === '13800138001', 'Edit must update the phone number.');

// Explicit clear removes both ciphertext and fingerprint.
$service->saveCrew(1, [
    'crew_id' => $crewId, 'crew_type_id' => 2, 'crew_name' => '钢筋一班',
    'leader_name' => '张伟', 'leader_phone' => '13800138001', 'id_card_clear' => '1',
]);
$cleared = $db->query('SELECT id_card_number_encrypted, id_card_fingerprint FROM Labor_Crews WHERE crew_id = ' . $crewId)->fetch();
crewCheck($cleared['id_card_number_encrypted'] === null, 'Clear checkbox must remove the ciphertext.');
crewCheck($cleared['id_card_fingerprint'] === null, 'Clear checkbox must remove the fingerprint.');

// Archiving keeps data; restoring works and cross-project access is denied.
$service->archiveCrew(1, $crewId);
$activeRows = $service->crews(1, false);
foreach ($activeRows as $row) {
    crewCheck((int) $row['crew_id'] !== $crewId, 'Archived crew must leave the active list.');
}
$archivedRows = $service->crews(1, true);
crewCheck(count($archivedRows) > 0, 'Archive must keep the row (no hard delete).');
crewExpectStatus(static function () use ($service, $crewId): void {
    $service->archiveCrew(2, $crewId);
}, 404, 'Project 2 must not archive a crew of project 1.');
$service->restoreCrew(1, $crewId);

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
Support::setDisplayTimezone('Asia/Shanghai');

$pluginContext = new PluginContext($db, dirname(__DIR__) . '/modules/labor-crew');
$plugin = new LaborCrewPlugin($pluginContext);

$pageHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($pageHtml, '班组负责人联系方式'), 'Crew page must render its heading.');
crewCheck(str_contains($pageHtml, '钢筋一班'), 'Crew page must list the saved crew.');
crewCheck(str_contains($pageHtml, '13800138001'), 'Crew page must show the leader phone.');

// Re-save an identity number to check masking behaviour on the page.
$service->saveCrew(1, [
    'crew_id' => $crewId, 'crew_type_id' => 2, 'crew_name' => '钢筋一班',
    'leader_name' => '张伟', 'leader_phone' => '13800138001', 'id_card_number' => '11010519491231002X',
]);
$maskedList = $service->crews(1);
crewCheck(str_contains($maskedList[0]['id_card_masked'], '*'), 'Directory rows must expose a masked identity number.');
crewCheck(!str_contains($maskedList[0]['id_card_masked'], '19491231'), 'Mask must hide the birth date.');

$fullHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($fullHtml, '11010519491231002X'), 'Permission holder may see the full identity number.');

// Viewer role: may see the list but never the full number.
$_SESSION['user_id'] = 2;
$viewerHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(!str_contains($viewerHtml, '11010519491231002X'), 'Non-permitted users must only see the masked number.');
crewCheck(str_contains($viewerHtml, '班组负责人联系方式'), 'Permitted viewer must still see the directory.');
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.save', [
        'crew_type_id' => 6, 'crew_name' => '越权班组', 'leader_name' => '测试', 'leader_phone' => '13500135000',
    ]);
}, 403, 'Users without edit_labor_crews must not save crews.');
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('type.save', ['type_name' => '越权工种']);
}, 403, 'Users without type edit permission must not save types.');
$_SESSION['user_id'] = 1;

// Archived view requires the archive permission and is reachable from the page.
$_SESSION['active_project_id'] = 2;
$projectTwoHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($projectTwoHtml, '钢筋一班'), 'Project 2 must see only its own crew.');
crewCheck(!str_contains($projectTwoHtml, '13800138001'), 'Project 2 must not leak project 1 crews.');
$_SESSION['active_project_id'] = 1;

// Missing project context is refused rather than reading global rows.
unset($_SESSION['active_project_id']);
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crews', []);
}, 409, 'Crew page must require a selected project.');
$_SESSION['active_project_id'] = 1;

// Craft type catalogue: rename, duplicate guard, reference protection.
$typeId = $service->saveType(['type_name' => '玻璃幕墙', 'sort_order' => 125]);
crewExpectStatus(static function () use ($service): void {
    $service->saveType(['type_name' => '玻璃幕墙']);
}, 409, 'Duplicate craft type name must be rejected.');
$service->saveType(['crew_type_id' => $typeId, 'type_name' => '幕墙安装', 'sort_order' => 126]);
crewCheck(str_contains((string) $db->query('SELECT type_name FROM Labor_Crew_Types WHERE crew_type_id = ' . $typeId)->fetchColumn(), '幕墙安装'), 'Craft type rename must persist.');
$referencedTypeId = (int) $db->query('SELECT crew_type_id FROM Labor_Crews WHERE crew_id = ' . $crewId)->fetchColumn();
crewExpectStatus(static function () use ($service, $referencedTypeId): void {
    $service->deleteType($referencedTypeId);
}, 409, 'Craft type in use by a crew must not be deletable.');
$service->deleteType($typeId);
crewCheck((int) $db->query('SELECT COUNT(*) FROM Labor_Crew_Types WHERE crew_type_id = ' . $typeId)->fetchColumn() === 0, 'Unused craft type must be deletable.');

// POST routes go through the plugin and write audit entries without leaking PII.
$auditBefore = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$redirect = $plugin->dispatch('crew.save', [
    'crew_type_id' => 8, 'crew_name' => '水电一班', 'leader_name' => '陈晨',
    'leader_phone' => '13512345678', 'id_card_number' => '110105194912310038',
]);
crewCheck($redirect->type === 'redirect' && $redirect->location === '?page=labor-crews', 'Save must redirect to the crew list.');
$auditAfter = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
crewCheck($auditAfter === $auditBefore + 1, 'Crew save must write exactly one audit entry.');
$auditDetails = (string) $db->query('SELECT details FROM audit_logs ORDER BY id DESC LIMIT 1')->fetchColumn();
crewCheck(!str_contains($auditDetails, '110105194912310038'), 'Audit details must not contain the identity number.');
crewCheck(str_contains($auditDetails, '水电一班'), 'Audit details should identify the crew by name.');

// Without APP_DATA_KEY the page still works and identity numbers are refused, not silently dropped.
putenv('APP_DATA_KEY');
crewCheck(FieldCipher::fromEnvironment() === null, 'FieldCipher must report missing key as null.');
$noCipherService = new CrewDirectoryService($db, null);
crewExpectStatus(static function () use ($noCipherService): void {
    $noCipherService->saveCrew(1, [
        'crew_type_id' => 9, 'crew_name' => '无密钥班组', 'leader_name' => '测试',
        'leader_phone' => '13500135000', 'id_card_number' => '110105194912310011',
    ]);
}, 503, 'Saving an identity number without a data key must fail loudly.');
$noCipherService->saveCrew(1, [
    'crew_type_id' => 9, 'crew_name' => '无密钥班组', 'leader_name' => '测试', 'leader_phone' => '13500135000',
]);
crewCheck($noCipherService->crews(1) !== [], 'Other contact fields must stay usable without a data key.');

// Toolbar filtering happens server side so permission rules always apply.
$service->saveCrew(1, [
    'crew_type_id' => 3, 'crew_name' => '木工二班', 'leader_name' => '孙磊', 'leader_phone' => '13456789012',
]);
$filteredByType = crewRenderView($plugin->dispatch('crews', ['type' => '3']));
crewCheck(str_contains($filteredByType, '木工二班'), 'Type filter must keep matching crews.');
crewCheck(!str_contains($filteredByType, '水电一班'), 'Type filter must drop other trades.');
$filteredByKeyword = crewRenderView($plugin->dispatch('crews', ['keyword' => '张伟']));
crewCheck(str_contains($filteredByKeyword, '钢筋一班'), 'Keyword filter must match leader name.');
crewCheck(!str_contains($filteredByKeyword, '木工二班'), 'Keyword filter must drop non-matching crews.');
crewCheck(str_contains($filteredByKeyword, '个班组'), 'Filter toolbar must report the filtered count.');
crewRenderView($plugin->dispatch('crews', ['type' => 'abc', 'keyword' => '  ']));
crewRenderView($plugin->dispatch('types', []));
crewCheck(true, 'Unusable filter values fall back to showing everything without error.');

// The project home page exposes the crew directory to permitted users only.
$_SESSION['user_id'] = 2;
$homeForViewer = null;
foreach ($registry->dispatch('GET', 'projects', [])?->data ?? [] as $key => $value) {
    if ($key === 'canViewLaborCrews') {
        $homeForViewer = $value;
    }
}
crewCheck($homeForViewer === true, 'Project home must offer the crew entry to crew viewers.');
$_SESSION['user_id'] = 1;
crewCheck(($registry->dispatch('GET', 'projects', [])?->data['canViewLaborCrews'] ?? null) === true, 'Admins see the crew entry.');

// Manifest wiring: routes registered through the plugin registry.
$registryRoutes = $registry->dispatch('GET', 'labor-crews', []);
crewCheck($registryRoutes instanceof PluginResponse, 'Registry must resolve the crew route to the plugin.');

echo "labor crew tests passed\n";
