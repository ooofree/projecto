<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\LaborCrew\CrewDirectoryService;
use ProjectO\Modules\LaborCrew\CrewShareService;
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

function crewXlsxSheet(PluginResponse $response): string
{
    $body = $response->data['body'] ?? null;
    if ($response->type !== 'download' || !is_string($body)) {
        throw new RuntimeException('Expected an xlsx download response.');
    }
    $path = tempnam(sys_get_temp_dir(), 'crew-xlsx-');
    file_put_contents($path, $body);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        @unlink($path);
        throw new RuntimeException('Export is not a readable xlsx zip package.');
    }
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);
    if (!is_string($xml) || $xml === '') {
        throw new RuntimeException('Export is missing its worksheet part.');
    }

    return $xml;
}
function crewRenderView(PluginResponse $response): string
{
    if ($response->type !== 'view' || $response->viewPath === null) {
        throw new RuntimeException('Expected a labor-crew view response.');
    }

    $escape = [Support::class, 'escape'];
    // Mirror Application::render(): the anonymous public share view gets no
    // CSRF token; every other page (including login) keeps one.
    $viewData = $response->data;
    $csrfToken = ($viewData['publicShareView'] ?? false) === true ? '' : 'crew-test-token';
    $username = isset($_SESSION['user_id']) ? 'admin' : '';
    $canManagePlatform = false;
    $theme = 'light';
    $flash = null;
    extract($viewData, EXTR_SKIP);
    ob_start();
    require $response->viewPath;

    return (string) ob_get_clean();
}

function crewPublicHtml(PluginResponse $response): string
{
    if ($response->type !== 'raw') {
        throw new RuntimeException('Expected a raw static share response, got ' . $response->type . '.');
    }

    return (string) ($response->data['body'] ?? '');
}

function crewShareFilePath(string $token): string
{
    return dirname(__DIR__) . '/storage/labor-crew-shares/' . $token . '.html';
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
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER NOT NULL, updated_by INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE System_Settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO System_Settings (setting_key, setting_value) VALUES ('timezone', 'Asia/Shanghai'), ('theme', 'light')");
$db->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

$migrationFiles = glob(dirname(__DIR__) . '/modules/labor-crew/database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
$mysqlDdl = '';
foreach ($migrationFiles as $migrationFile) {
    $mysqlDdl .= (string) file_get_contents($migrationFile);
}
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
crewCheck(
    str_contains($mysqlDdl, 'ADD COLUMN `sort_order`'),
    'Crews must gain an editable sort order column through a migration.'
);
crewCheck(
    str_contains($mysqlDdl, 'CREATE TABLE IF NOT EXISTS `Labor_Crew_Shares`')
        && str_contains($mysqlDdl, 'UNIQUE KEY `uq_crew_share_token` (`token`)')
        && str_contains($mysqlDdl, 'KEY `idx_crew_share_project_revoked` (`project_id`, `revoked_at`)')
        && str_contains($mysqlDdl, '`revoked_at` timestamp NULL DEFAULT NULL'),
    'Share tokens must live in their own table: unique token, per-project lookup, soft revocation.'
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
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, id_card_fingerprint)
)');
$db->exec('CREATE TABLE Labor_Crew_Shares (
    share_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES Projects (project_id),
    token TEXT NOT NULL UNIQUE,
    created_by INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    revoked_at TEXT,
    revoked_by INTEGER
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

// Manual ordering: the per-crew sort order drives list sequence (smaller first).
$firstSortedId = $service->saveCrew(1, [
    'crew_type_id' => 9, 'crew_name' => '排序首位班组', 'leader_name' => '排序乙',
    'leader_phone' => '13500135002', 'sort_order' => 5,
]);
$service->saveCrew(1, [
    'crew_type_id' => 9, 'crew_name' => '排序末位班组', 'leader_name' => '排序甲',
    'leader_phone' => '13500135001', 'sort_order' => 900,
]);
$sortedNames = array_column($service->crews(1), 'crew_name');
$firstPosition = array_search('排序首位班组', $sortedNames, true);
$lastPosition = array_search('排序末位班组', $sortedNames, true);
crewCheck(
    is_int($firstPosition) && is_int($lastPosition) && $firstPosition < $lastPosition,
    'Manual sort numbers must order the list from small to large.'
);
$service->saveCrew(1, [
    'crew_id' => $firstSortedId, 'crew_type_id' => 9, 'crew_name' => '排序首位班组',
    'leader_name' => '排序乙', 'leader_phone' => '13500135002', 'sort_order' => 950,
]);
$resortedNames = array_column($service->crews(1), 'crew_name');
$firstPosition = array_search('排序首位班组', $resortedNames, true);
$lastPosition = array_search('排序末位班组', $resortedNames, true);
crewCheck(
    is_int($firstPosition) && is_int($lastPosition) && $lastPosition < $firstPosition,
    'Editing the sort number must move the crew in the list.'
);
crewCheck(
    $resortedNames[0] === '排序末位班组',
    'Crews with a sort number must come before crews left at the default 0.'
);
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, [
        'crew_type_id' => 9, 'crew_name' => '排序非法班组', 'leader_name' => '测试',
        'leader_phone' => '13500135003', 'sort_order' => 10000,
    ]);
}, 422, 'Sort order above 9999 must be rejected.');
crewExpectStatus(static function () use ($service): void {
    $service->saveCrew(1, [
        'crew_type_id' => 9, 'crew_name' => '排序非法班组', 'leader_name' => '测试',
        'leader_phone' => '13500135003', 'sort_order' => -1,
    ]);
}, 422, 'Negative sort order must be rejected.');

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
Support::setDisplayTimezone('Asia/Shanghai');

$pluginContext = new PluginContext($db, dirname(__DIR__) . '/modules/labor-crew');
$plugin = new LaborCrewPlugin($pluginContext);

$pageHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($pageHtml, '班组负责人联系方式'), 'Crew page must render its heading.');
crewCheck(str_contains($pageHtml, 'name="sort_order"'), 'Crew forms must expose a manual sort field.');
crewCheck(str_contains($pageHtml, '钢筋一班'), 'Crew page must list the saved crew.');
crewCheck(str_contains($pageHtml, '13800138001'), 'Crew page must show the leader phone.');

// Re-save an identity number to check masking behaviour on the page.
$service->saveCrew(1, [
    'crew_id' => $crewId, 'crew_type_id' => 2, 'crew_name' => '钢筋一班',
    'leader_name' => '张伟', 'leader_phone' => '13800138001', 'id_card_number' => '11010519491231002X',
]);
$maskedRow = null;
foreach ($service->crews(1) as $row) {
    if ((int) $row['crew_id'] === $crewId) {
        $maskedRow = $row;
        break;
    }
}
crewCheck($maskedRow !== null, 'The edited crew must stay in the project directory.');
crewCheck(str_contains((string) $maskedRow['id_card_masked'], '*'), 'Directory rows must expose a masked identity number.');
crewCheck(!str_contains((string) $maskedRow['id_card_masked'], '19491231'), 'Mask must hide the birth date.');

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


// Excel export: report layout like the other modules, project-named title,
// sequence number / craft type / crew name / leader / phone / remarks columns,
// active crews only, and no identity numbers or WeChat ids in the file.
$exportRemarkCrewId = $service->saveCrew(1, [
    'crew_type_id' => 4, 'crew_name' => '导出备注班组', 'leader_name' => '备注联系人',
    'leader_phone' => '13500135009', 'wechat_id' => 'export-wx-id', 'notes' => '导出备注内容-防水重点',
]);
$exportResponse = $plugin->dispatch('crew.export', []);
crewCheck($exportResponse->type === 'download', 'Export must return a download response.');
$exportName = (string) ($exportResponse->data['filename'] ?? '');
crewCheck(
    preg_match('/^施工班组联系表_Project One_[0-9]{8}_[0-9]+\.xlsx$/u', $exportName) === 1,
    'Export file name must follow table name + project name + date + daily sequence, got: ' . $exportName
);
crewCheck(
    ($exportResponse->data['content_type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Export must use the xlsx content type.'
);
$sheetXml = crewXlsxSheet($exportResponse);
crewCheck(str_contains($sheetXml, 'Project One施工班组负责人联系表'), 'Title row must carry the project name.');
crewCheck(str_contains($sheetXml, '导出时间:') && str_contains($sheetXml, '记录总数:'), 'Meta rows must show export time and totals.');
foreach (['序号', '工种', '班组名称', '负责人姓名', '负责人电话', '备注'] as $header) {
    crewCheck(str_contains($sheetXml, $header), 'Header row must contain ' . $header . '.');
}
crewCheck(
    str_contains($sheetXml, '钢筋一班') && str_contains($sheetXml, '13800138001'),
    'Active crews must appear with leader and phone.'
);
crewCheck(str_contains($sheetXml, '导出备注内容-防水重点'), 'Remarks must be exported.');
crewCheck(str_contains($sheetXml, '<c r="A7" s="3"><v>1</v></c>'), 'The first data row must carry sequence number 1.');
crewCheck(
    str_contains($sheetXml, '<col min="2" max="2" width="16" customWidth="1"/>')
        && str_contains($sheetXml, '<col min="3" max="3" width="30" customWidth="1"/>'),
    'Craft type and crew name columns must be widened.'
);
crewCheck(
    strpos($sheetXml, '排序末位班组') !== false
        && strpos($sheetXml, '排序末位班组') < strpos($sheetXml, '排序首位班组'),
    'Export rows must keep the manual sort order.'
);
crewCheck(!str_contains($sheetXml, '11010519491231002X'), 'Identity numbers must never be exported.');
crewCheck(!str_contains($sheetXml, 'export-wx-id'), 'WeChat ids must never be exported.');
crewCheck(!str_contains($sheetXml, 'Project Two'), 'The export must stay inside the current project.');
$db->exec('DELETE FROM Labor_Crews WHERE crew_id = ' . $exportRemarkCrewId);

// The project name joins the file name; characters that cannot travel in a
// download header or on disk are replaced instead of failing the export.
$db->exec("INSERT INTO Projects (project_name, status) VALUES ('A/B:C*D', 'planning')");
$unsafeProjectId = (int) $db->lastInsertId();
$service->saveCrew($unsafeProjectId, [
    'crew_type_id' => 2, 'crew_name' => '安全班组', 'leader_name' => '测试', 'leader_phone' => '13500135010',
]);
$_SESSION['active_project_id'] = $unsafeProjectId;
$unsafeExportName = (string) ($plugin->dispatch('crew.export', [])->data['filename'] ?? '');
$_SESSION['active_project_id'] = 1;
crewCheck(
    preg_match('/^施工班组联系表_A_B_C_D_[0-9]{8}_[0-9]+\.xlsx$/u', $unsafeExportName) === 1,
    'Unsafe project-name characters must be sanitised in the export file name, got: ' . $unsafeExportName
);
crewCheck(
    mb_strlen($unsafeExportName, 'UTF-8') <= 150,
    'The export file name must stay within the download length limit.'
);

// A +86 style phone must stay text so Excel cannot mangle it.
$service->saveCrew(1, [
    'crew_type_id' => 4, 'crew_name' => '导出国际号班组', 'leader_name' => '跨境', 'leader_phone' => '+8613800001234',
]);
$plusSheet = crewXlsxSheet($plugin->dispatch('crew.export', []));
crewCheck(
    preg_match('/t="inlineStr"><is><t[^>]*>\+8613800001234/', $plusSheet) === 1,
    'International numbers must be exported as text cells without an escape prefix.'
);

// Archived crews are excluded; restoring brings them back.
$service->archiveCrew(1, $crewId);
$afterArchive = crewXlsxSheet($plugin->dispatch('crew.export', []));
crewCheck(str_contains($afterArchive, '导出国际号班组') && !str_contains($afterArchive, '钢筋一班'), 'Archived crews must be excluded from the export.');
$service->restoreCrew(1, $crewId);
$sheetAgain = crewXlsxSheet($plugin->dispatch('crew.export', []));
crewCheck(str_contains($sheetAgain, '钢筋一班'), 'Restored crews must reappear.');

// Export is a read: the daily sequence increments per export, viewers may export,
// and every export is audited without leaking row contents.
$auditCount = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'labor.crew.export'")->fetchColumn();
crewCheck($auditCount >= 4, 'Each export must write an audit entry.');
// Compare two back-to-back exports; earlier ones in this test also consumed
// numbers of the day, so only adjacent calls must differ by exactly one.
$firstName = (string) $plugin->dispatch('crew.export', [])->data['filename'];
$secondName = (string) $plugin->dispatch('crew.export', [])->data['filename'];
preg_match('/_([0-9]+)\.xlsx$/', $firstName, $m1);
preg_match('/_([0-9]+)\.xlsx$/', $secondName, $m2);
crewCheck(
    (int) $m1[1] + 1 === (int) $m2[1],
    'Adjacent exports must take the next number of the day: ' . $firstName . ' -> ' . $secondName
);
crewCheck(str_starts_with($secondName, '施工班组联系表_'), 'File name must start with the table name.');
$_SESSION['user_id'] = 2;
$viewerExport = $plugin->dispatch('crew.export', []);
crewCheck($viewerExport->type === 'download', 'Users with view permission may export.');
$_SESSION['user_id'] = 1;
unset($_SESSION['active_project_id']);
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.export', []);
}, 409, 'Export must require a selected project.');
$_SESSION['active_project_id'] = 1;
$db->exec("DELETE FROM Labor_Crews WHERE crew_name = '导出国际号班组'");
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

// ---------------------------------------------------------------------------
// Public share links: opt-in per project, token-only read access, tel: dialling,
// export-equivalent columns only, revocation kills the link, re-enable rotates.
// ---------------------------------------------------------------------------
$shareService = new CrewShareService($db);
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];

// The toolbar button sits next to the export button and only for share holders.
$sharePageHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($sharePageHtml, 'crew-share-dialog'), 'Share holders must get the share dialog.');
$exportPos = strpos($sharePageHtml, '导出 Excel');
$sharePos = strpos($sharePageHtml, '分享链接');
crewCheck($exportPos !== false && $sharePos !== false && $sharePos > $exportPos, 'The share button must sit after the export button.');
crewCheck(str_contains($sharePageHtml, '生成分享链接'), 'Inactive share must offer to generate a link.');

// Viewer role (view only) must not see share management.
$_SESSION['user_id'] = 2;
$viewerShareHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(!str_contains($viewerShareHtml, 'crew-share-dialog'), 'Users without share permission must not see the share dialog.');
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.share', []);
}, 403, 'Users without share_labor_crews must not enable sharing.');
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.share_revoke', []);
}, 403, 'Users without share_labor_crews must not revoke sharing.');
$_SESSION['user_id'] = 1;

// Enable: token minted, audited without the token, idempotent on repeat.
$auditBeforeShare = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'labor.crew.share'")->fetchColumn();
$enableRedirect = $plugin->dispatch('crew.share', []);
crewCheck($enableRedirect->type === 'redirect' && $enableRedirect->location === '?page=labor-crews', 'Enabling a share must return to the crew list.');
$activeShare = $shareService->activeShare(1);
crewCheck($activeShare !== null && preg_match('/^[0-9a-f]{32}$/', (string) $activeShare['token']) === 1, 'An active share must carry a 32-hex token.');
$shareToken = (string) $activeShare['token'];
$auditAfterShare = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'labor.crew.share'")->fetchColumn();
crewCheck($auditAfterShare === $auditBeforeShare + 1, 'Enabling a share must write one audit entry.');
$shareAuditDetails = (string) $db->query("SELECT details FROM audit_logs WHERE action = 'labor.crew.share' ORDER BY id DESC LIMIT 1")->fetchColumn();
crewCheck(!str_contains($shareAuditDetails, $shareToken), 'Audit details must not contain the share token.');
$plugin->dispatch('crew.share', []);
crewCheck((string) $shareService->activeShare(1)['token'] === $shareToken, 'Repeated enable must keep the same active token (double-submit safe).');

// The toolbar now shows the active link with copy/preview/revoke controls.
$activeShareHtml = crewRenderView($plugin->dispatch('crews', []));
crewCheck(str_contains($activeShareHtml, 'data-share-link'), 'Active share must expose the link element.');
crewCheck(str_contains($activeShareHtml, $shareToken), 'The link element must contain the token.');
crewCheck(
    preg_match('/data-copy-share\s+data-share-url="([^"]+)"/', $activeShareHtml, $copyAttr) === 1
        && html_entity_decode($copyAttr[1], ENT_QUOTES) === CrewShareService::publicUrl($shareToken),
    'The copy button must carry exactly the public URL as its copy payload.'
);
crewCheck(str_contains($activeShareHtml, 'data-share-feedback'), 'The dialog must expose a copy feedback region.');
crewCheck(str_contains($activeShareHtml, '停用分享'), 'Active share must offer revocation.');

// Public page: no session at all, token is the whole authorization.
$_SESSION = [];
$publicResponse = $plugin->dispatch('crew.share_public', ['token' => $shareToken]);
crewCheck($publicResponse->type === 'raw', 'Public share must serve a pre-rendered static page without login.');
$publicHtml = crewPublicHtml($publicResponse);
crewCheck(is_file(crewShareFilePath($shareToken)), 'Enabling a share must write the static page file.');
crewCheck(file_get_contents(crewShareFilePath($shareToken)) === $publicHtml, 'The served body must be exactly the static file content.');
crewCheck(!str_contains($publicHtml, '<link rel="stylesheet"') && !str_contains($publicHtml, 'src="assets'), 'The static page must be self-contained (no external CSS/JS).');
crewCheck(str_contains($publicHtml, 'class="tile" href="tel:'), 'Each tile must be a whole-card tel: link.');
crewCheck(str_contains($publicHtml, 'tile-num') && str_contains($publicHtml, 'tile-hint'), 'Tiles must carry the number plus the mobile dial hint.');
crewCheck(str_contains($publicHtml, 'class="trade-nav"') && str_contains($publicHtml, 'href="#trade-0"'), 'Craft-type chips must anchor-navigate to the first tile of each trade.');
crewCheck(preg_match('/<li id="trade-\d+"/', $publicHtml) === 1, 'The first tile of each trade must carry the chip anchor id.');
crewCheck(!str_contains($publicHtml, '<section class="trade"'), 'The grid must be continuous: no per-trade sections that leave empty slots.');
crewCheck(substr_count($publicHtml, 'class="tile-type"') >= 2, 'Every tile must carry its own trade label.');
crewCheck(str_contains($publicHtml, '.chips{display:flex;flex-wrap:wrap'), 'Trade chips must wrap to fit narrow screens instead of scrolling sideways.');
crewCheck(str_contains($publicHtml, 'class="name"'), 'Chip labels must be individually truncatable so long trade names cannot overflow.');
crewCheck(str_contains($publicHtml, '--nav-h') && str_contains($publicHtml, 'ResizeObserver'), 'Anchor offset must follow the measured nav height across viewports.');
crewCheck(preg_match('/data-layout="[0-9a-f]{12}"/', $publicHtml) === 1, 'Static pages must stamp the template version they were rendered with.');
crewCheck(str_contains($publicHtml, 'Project One'), 'Public page must name the shared project.');
crewCheck(str_contains($publicHtml, '钢筋一班'), 'Public page must list active crews of the token project.');
crewCheck(str_contains($publicHtml, 'href="tel:13800138001"'), 'Leader phones must be tel: links for direct dialling.');
crewCheck(!str_contains($publicHtml, 'href="tel:+8613900139000"'), 'Emergency contact phones must not be published.');
crewCheck(!str_contains($publicHtml, '李静'), 'Emergency contact names must never appear on the public page.');
crewCheck(!str_contains($publicHtml, '11010519491231002X'), 'Identity numbers must never appear on the public page.');
crewCheck(!str_contains($publicHtml, '110105********'), 'Masked identity numbers must not appear either.');
crewCheck(!str_contains($publicHtml, 'zw-steel'), 'WeChat ids must never appear on the public page.');
crewCheck(!str_contains($publicHtml, 'Project Two'), 'The public page must not leak other projects.');
crewCheck(!str_contains($publicHtml, 'crew-share-dialog') && !str_contains($publicHtml, 'csrf_token'), 'The public page must not expose management controls or CSRF tokens.');
crewCheck(str_contains($publicHtml, 'share-search'), 'Public page must offer live search.');

// Archived crews stay out of the public page; restoring brings them back.
// Use the real controller actions: they are the mutation paths that carry the
// static-page regeneration hooks (direct service calls bypass the plugin).
$shareArchiveId = $service->saveCrew(1, [
    'crew_type_id' => 5, 'crew_name' => '分享归档班组', 'leader_name' => '归档联系人', 'leader_phone' => '13500135020',
]);
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$plugin->dispatch('crew.archive', ['crew_id' => (string) $shareArchiveId]);
$_SESSION = [];
$publicAfterArchive = crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $shareToken]));
crewCheck(!str_contains($publicAfterArchive, '分享归档班组'), 'Archived crews must not appear on the public page.');
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$plugin->dispatch('crew.restore', ['crew_id' => (string) $shareArchiveId]);
$_SESSION = [];
$publicAfterRestore = crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $shareToken]));
crewCheck(str_contains($publicAfterRestore, '分享归档班组'), 'Restored crews must reappear on the public page.');
$db->exec('DELETE FROM Labor_Crews WHERE crew_id = ' . $shareArchiveId);

// Bad tokens are refused with 404 and never reveal whether a share exists.
foreach (['', 'nope', str_repeat('0', 32), strtoupper($shareToken), $shareToken . '0', substr($shareToken, 0, 31)] as $badToken) {
    crewExpectStatus(static function () use ($plugin, $badToken): void {
        $plugin->dispatch('crew.share_public', ['token' => $badToken]);
    }, 404, 'Invalid token must be refused: ' . var_export($badToken, true));
}
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.share_public', []);
}, 404, 'Missing token must be refused.');
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.share_public', ['token' => ['array']]);
}, 404, 'Non-string token must be refused.');

// Shares are per project: project 2 gets its own token and its own rows.
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 2];
$plugin->dispatch('crew.share', []);
$projectTwoToken = (string) $shareService->activeShare(2)['token'];
crewCheck($projectTwoToken !== $shareToken, 'Each project must get its own token.');
$_SESSION = [];
$projectTwoPublic = crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $projectTwoToken]));
crewCheck(str_contains($projectTwoPublic, 'Project Two'), 'Project 2 token must open project 2.');
crewCheck(!str_contains($projectTwoPublic, '13800138001'), 'Project 2 public page must not leak project 1 phones.');

// Revocation kills the link immediately; the row is kept, not deleted.
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$revokeRedirect = $plugin->dispatch('crew.share_revoke', []);
crewCheck($revokeRedirect->type === 'redirect', 'Revoking must return to the crew list.');
crewCheck($shareService->activeShare(1) === null, 'Revoked share must no longer be active.');
$revokedRow = $db->query('SELECT revoked_at, revoked_by FROM Labor_Crew_Shares WHERE project_id = 1')->fetch();
crewCheck($revokedRow !== false && $revokedRow['revoked_at'] !== null, 'Revocation must keep the row with a timestamp (no hard delete).');
crewCheck((int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'labor.crew.share_revoke'")->fetchColumn() >= 1, 'Revocation must be audited.');
crewCheck(!is_file(crewShareFilePath($shareToken)), 'Revocation must delete the static page file.');
$_SESSION = [];
crewExpectStatus(static function () use ($plugin, $shareToken): void {
    $plugin->dispatch('crew.share_public', ['token' => $shareToken]);
}, 404, 'A revoked token must stop working immediately.');

// Re-enabling rotates the token: the old link stays dead.
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$plugin->dispatch('crew.share', []);
$rotatedToken = (string) $shareService->activeShare(1)['token'];
crewCheck($rotatedToken !== $shareToken, 'Re-enabling must mint a fresh token.');
$_SESSION = [];
crewExpectStatus(static function () use ($plugin, $shareToken): void {
    $plugin->dispatch('crew.share_public', ['token' => $shareToken]);
}, 404, 'The old token must stay dead after rotation.');
crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $rotatedToken]));

// Archiving the project stops its public page, like the session context does.
$db->exec("INSERT INTO project_foundation_archive_records (entity_type, entity_id, project_id, archived_by) VALUES ('project', 1, 1, 1)");
crewExpectStatus(static function () use ($plugin, $rotatedToken): void {
    $plugin->dispatch('crew.share_public', ['token' => $rotatedToken]);
}, 404, 'An archived project must not serve its public page.');
crewCheck(!is_file(crewShareFilePath($rotatedToken)), 'Archiving the project must remove the stale static file.');
$db->exec("DELETE FROM project_foundation_archive_records WHERE entity_type = 'project' AND entity_id = 1");
crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $rotatedToken]));

// Sharing needs a selected project, and the registry marks the route public.
$_SESSION = ['user_id' => 1, 'username' => 'admin'];
crewExpectStatus(static function () use ($plugin): void {
    $plugin->dispatch('crew.share', []);
}, 409, 'Enabling a share must require a selected project.');
crewCheck($registry->isPublicRoute('GET', 'labor-crew-share'), 'The share page must be declared public in the manifest.');
crewCheck(!$registry->isPublicRoute('GET', 'labor-crews'), 'The internal crew page must stay behind login.');
crewCheck(!$registry->isPublicRoute('POST', 'labor.crew.share'), 'Share management must never be public.');
$registryPublic = $registry->dispatch('GET', 'labor-crew-share', ['token' => $rotatedToken]);
crewCheck($registryPublic instanceof PluginResponse, 'Registry must resolve the public share route.');

// Disabled plugin: the public route disappears with it.
$registry->setEnabled('labor.crew', false);
$disabledRegistry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
crewCheck(!$disabledRegistry->isPublicRoute('GET', 'labor-crew-share'), 'A disabled plugin must expose no public route.');
crewCheck($disabledRegistry->dispatch('GET', 'labor-crew-share', ['token' => $rotatedToken]) === null, 'A disabled plugin must not serve the public page.');
$registry->setEnabled('labor.crew', true);

// ---------------------------------------------------------------------------
// Static page lifecycle: auto-update on every mutation, 304 revalidation,
// lazy regeneration for legacy shares, cross-project craft-type refresh.
// ---------------------------------------------------------------------------
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$staticToken = (string) $shareService->activeShare(1)['token'];
$staticPath = crewShareFilePath($staticToken);
crewCheck(is_file($staticPath), 'An active share must have a static file on disk.');
$staticBefore = file_get_contents($staticPath);

// Editing a crew regenerates the file without any visit to the public route.
$staticCrewId = $service->saveCrew(1, [
    'crew_type_id' => 2, 'crew_name' => '静态同步班组', 'leader_name' => '同步甲',
    'leader_phone' => '13600001111', 'notes' => '同步前备注',
]);
$plugin->dispatch('crew.save', [
    'crew_id' => $staticCrewId, 'crew_type_id' => 2, 'crew_name' => '静态同步班组',
    'leader_name' => '同步甲', 'leader_phone' => '13600002222', 'notes' => '同步后备注',
]);
$staticAfter = file_get_contents($staticPath);
crewCheck($staticAfter !== $staticBefore, 'Saving a crew must rewrite the static file.');
crewCheck(str_contains($staticAfter, '13600002222') && !str_contains($staticAfter, '13600001111'), 'The static file must carry the updated phone, not the old one.');
crewCheck(str_contains($staticAfter, '同步后备注'), 'The static file must carry the updated notes.');

// Conditional request: unchanged page answers 304 with no body.
$_SESSION = [];
$firstServe = $plugin->dispatch('crew.share_public', ['token' => $staticToken]);
$etag = (string) ($firstServe->data['headers']['ETag'] ?? '');
crewCheck($etag !== '', 'Static responses must carry an ETag.');
$_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
$notModified = $plugin->dispatch('crew.share_public', ['token' => $staticToken]);
crewCheck($notModified->type === 'not_modified', 'Matching If-None-Match must return 304.');
unset($_SERVER['HTTP_IF_NONE_MATCH']);
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];

// Archiving a crew removes it from the static file; restoring brings it back.
$plugin->dispatch('crew.archive', ['crew_id' => (string) $staticCrewId]);
crewCheck(!str_contains((string) file_get_contents($staticPath), '静态同步班组'), 'Archiving a crew must update the static file.');
$plugin->dispatch('crew.restore', ['crew_id' => (string) $staticCrewId]);
crewCheck(str_contains((string) file_get_contents($staticPath), '静态同步班组'), 'Restoring a crew must update the static file.');
$db->exec('DELETE FROM Labor_Crews WHERE crew_id = ' . $staticCrewId);
$plugin->dispatch('crew.save', ['crew_type_id' => 2, 'crew_name' => '占位触发', 'leader_name' => '触发', 'leader_phone' => '13600003333']);
$db->exec("DELETE FROM Labor_Crews WHERE crew_name = '占位触发'");

// Renaming a craft type refreshes every project's static page.
$staticTypeId = $service->saveType(['type_name' => '静态工种旧名', 'sort_order' => 500]);
$plugin->dispatch('crew.save', [
    'crew_type_id' => (string) $staticTypeId, 'crew_name' => '工种改名班组', 'leader_name' => '改名', 'leader_phone' => '13600004444',
]);
crewCheck(str_contains((string) file_get_contents($staticPath), '静态工种旧名'), 'New craft type must reach the static page.');
$plugin->dispatch('type.save', ['crew_type_id' => (string) $staticTypeId, 'type_name' => '静态工种新名']);
crewCheck(str_contains((string) file_get_contents($staticPath), '静态工种新名'), 'Renaming a craft type must refresh the static page.');
crewCheck(is_file(crewShareFilePath($projectTwoToken)), 'Project 2 share keeps its own static file.');
$db->exec("DELETE FROM Labor_Crews WHERE crew_name = '工种改名班组'");
$service->deleteType($staticTypeId);

// A share without a file (created before static pages) regenerates lazily.
@unlink($staticPath);
$_SESSION = [];
$lazyHtml = crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $staticToken]));
crewCheck(is_file($staticPath), 'A missing static file must be regenerated on first visit.');
crewCheck(str_contains($lazyHtml, '钢筋一班'), 'Lazily regenerated page must contain current crews.');

// A layout upgrade must invalidate files rendered by the previous template, so
// existing links pick up new markup without any contact-data change.
$staleHtml = preg_replace('/data-layout="[0-9a-f]{12}"/', 'data-layout="000000000000"', (string) file_get_contents($staticPath));
file_put_contents($staticPath, $staleHtml);
$upgradedHtml = crewPublicHtml($plugin->dispatch('crew.share_public', ['token' => $staticToken]));
crewCheck(!str_contains($upgradedHtml, 'data-layout="000000000000"'), 'A page from an older template must be regenerated on the next request.');
crewCheck((string) file_get_contents($staticPath) === $upgradedHtml, 'The regenerated file must be published to disk.');

// Cleanup: revoke the remaining shares so no test artifacts stay on disk.
$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$plugin->dispatch('crew.share_revoke', []);
$_SESSION['active_project_id'] = 2;
$plugin->dispatch('crew.share_revoke', []);
crewCheck(!is_file(crewShareFilePath($staticToken)) && !is_file(crewShareFilePath($projectTwoToken)), 'Revoking must remove every static artifact.');

echo "labor crew tests passed\n";
