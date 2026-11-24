<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Application;
use ProjectO\Modules\DocumentManagement\DocumentImageService;
use ProjectO\Modules\DocumentManagement\DocumentManagementPlugin;
use ProjectO\Modules\DocumentManagement\DocumentService;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Storage\CloudStorageSettings;
use ProjectO\ProfileService;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function documentTestCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function documentTestExpectStatus(callable $operation, int $status, string $message): void
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

function documentTestXlsxPart(\ProjectO\Plugin\PluginResponse $response, string $part): string
{
    $body = $response->data['body'] ?? null;
    if ($response->type !== 'download' || !is_string($body)) {
        throw new RuntimeException('Expected an xlsx download response.');
    }
    $path = tempnam(sys_get_temp_dir(), 'document-xlsx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary file for the xlsx export.');
    }
    file_put_contents($path, $body);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        @unlink($path);
        throw new RuntimeException('Export is not a readable xlsx zip package.');
    }
    $xml = $zip->getFromName($part);
    $zip->close();
    @unlink($path);
    if (!is_string($xml) || $xml === '') {
        throw new RuntimeException('Export is missing its ' . $part . ' part.');
    }

    return $xml;
}

final class DocumentTestImageStorage implements ObjectStorageInterface
{
    public static string $body = '';
    public static string $fileUrl = '';
    /** @var array<string, array{path: string, content_type: string}> */
    public array $uploaded = [];
    /** @var list<string> */
    public array $deleted = [];

    public function __construct(?PDO $db = null)
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function upload(string $objectKey, string $filePath, string $contentType): void
    {
        $this->uploaded[$objectKey] = ['path' => $filePath, 'content_type' => $contentType];
    }

    public function signedReadUrl(string $objectKey, string $contentType): string
    {
        return self::$fileUrl !== ''
            ? self::$fileUrl
            : 'https://r2.example.test/' . $objectKey . '?signature=test';
    }

    public function read(string $objectKey): string
    {
        return self::$body;
    }

    public function delete(string $objectKey): void
    {
        $this->deleted[] = $objectKey;
    }
}

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec(
    "CREATE TABLE Projects (
        project_id INTEGER PRIMARY KEY,
        project_name TEXT NOT NULL,
        start_date TEXT,
        end_date TEXT,
        status TEXT
    )"
);
$db->exec(
    "CREATE TABLE project_foundation_archive_records (
        archive_id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL REFERENCES Projects (project_id),
        archived_by INTEGER NOT NULL,
        archived_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (entity_type, entity_id)
    )"
);
$db->exec(
    "CREATE TABLE Users (
        user_id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        timezone TEXT,
        is_active INTEGER NOT NULL DEFAULT 1
    )"
);
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY, permission_name TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
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
$db->exec(
    "CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )"
);
$db->exec("INSERT INTO Projects (project_id, project_name, status) VALUES (1, 'Project One', 'planning'), (2, 'Project Two', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, is_active) VALUES (1, 'admin', 1), (2, 'engineer', 1)");
$db->exec('INSERT INTO Roles (role_id) VALUES (1)');
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');
$db->exec(
    "INSERT INTO Permissions (permission_id, permission_name)
     VALUES
        (1, 'view_issues'), (2, 'create_issues'), (3, 'edit_issues'),
        (4, 'manage_issues'), (5, 'view_projects'), (6, 'manage_document_settings')"
);
$db->exec(
    'INSERT INTO Role_Permissions (role_id, permission_id)
     VALUES (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6)'
);

// Project space tables (project.foundation) so issue locations can be selected
// from real spaces, including archive and cross-project rejection cases.
$db->exec(
    "CREATE TABLE Buildings (
        building_id INTEGER PRIMARY KEY,
        project_id INTEGER NOT NULL REFERENCES Projects (project_id),
        building_number TEXT NOT NULL,
        building_name TEXT NOT NULL
    )"
);
$db->exec(
    "CREATE TABLE Building_Floors (
        floor_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL REFERENCES Buildings (building_id),
        floor_number TEXT NOT NULL,
        floor_name TEXT,
        usage_type TEXT,
        UNIQUE (floor_id, building_id)
    )"
);
$db->exec(
    "CREATE TABLE Construction_Areas (
        area_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL REFERENCES Buildings (building_id),
        area_name TEXT NOT NULL,
        description TEXT,
        UNIQUE (area_id, building_id)
    )"
);
$db->exec(
    "CREATE TABLE Rooms (
        room_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL,
        floor_id INTEGER NOT NULL,
        room_number TEXT NOT NULL,
        room_type TEXT,
        room_area REAL,
        room_height REAL,
        room_status TEXT,
        FOREIGN KEY (floor_id, building_id) REFERENCES Building_Floors (floor_id, building_id),
        UNIQUE (room_id, building_id, floor_id)
    )"
);
$db->exec("INSERT INTO Buildings (building_id, project_id, building_number, building_name) VALUES (10, 1, 'A', 'Tower A'), (20, 2, 'B', 'Tower B')");
$db->exec("INSERT INTO Building_Floors (floor_id, building_id, floor_number, floor_name) VALUES (11, 10, '01', 'Ground'), (12, 10, '02', NULL), (21, 20, '01', 'Ground')");
$db->exec("INSERT INTO Construction_Areas (area_id, building_id, area_name) VALUES (13, 10, 'Lobby'), (23, 20, 'Other lobby')");
$db->exec("INSERT INTO Rooms (room_id, building_id, floor_id, room_number) VALUES (14, 10, 11, '101'), (15, 10, 12, '201'), (24, 20, 21, '101')");

$db->exec(
    "CREATE TABLE document_issues (
        issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        issue_number TEXT,
        description TEXT NOT NULL,
        severity TEXT NOT NULL DEFAULT 'medium',
        status TEXT NOT NULL DEFAULT 'open',
        location TEXT,
        location_type TEXT,
        location_building_id INTEGER REFERENCES Buildings (building_id),
        location_floor_id INTEGER REFERENCES Building_Floors (floor_id),
        location_area_id INTEGER REFERENCES Construction_Areas (area_id),
        location_room_id INTEGER REFERENCES Rooms (room_id),
        location_note TEXT,
        reported_by INTEGER NOT NULL,
        reported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolution_result TEXT,
        verification_status TEXT NOT NULL DEFAULT 'pending',
        verification_note TEXT,
        evidence_reference TEXT,
        closed_at TEXT,
        closed_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (issue_id, project_id),
        FOREIGN KEY (project_id) REFERENCES Projects (project_id),
        FOREIGN KEY (reported_by) REFERENCES Users (user_id),
        FOREIGN KEY (closed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_issue_status_history (
        history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        issue_id INTEGER NOT NULL,
        old_status TEXT,
        new_status TEXT NOT NULL,
        reason TEXT NOT NULL,
        evidence_reference TEXT,
        transition_data TEXT,
        changed_by INTEGER NOT NULL,
        changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (issue_id, project_id) REFERENCES document_issues (issue_id, project_id),
        FOREIGN KEY (changed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_issue_follow_ups (
        follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        issue_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        is_completed INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NOT NULL,
        completed_by INTEGER,
        completed_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (follow_up_id, project_id),
        FOREIGN KEY (issue_id, project_id) REFERENCES document_issues (issue_id, project_id),
        FOREIGN KEY (created_by) REFERENCES Users (user_id),
        FOREIGN KEY (completed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_issue_follow_up_history (
        history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        follow_up_id INTEGER NOT NULL,
        old_status TEXT NOT NULL,
        new_status TEXT NOT NULL,
        reason TEXT NOT NULL,
        evidence_reference TEXT,
        changed_by INTEGER NOT NULL,
        changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follow_up_id, project_id) REFERENCES document_issue_follow_ups (follow_up_id, project_id),
        FOREIGN KEY (changed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfis (
        rfi_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        primary_issue_id INTEGER NOT NULL,
        rfi_number TEXT NOT NULL UNIQUE,
        assigned_to INTEGER NOT NULL,
        priority TEXT NOT NULL DEFAULT 'medium',
        due_date TEXT,
        description TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        submitted_by INTEGER NOT NULL,
        submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        response_text TEXT,
        response_complete INTEGER NOT NULL DEFAULT 0,
        evidence_required INTEGER NOT NULL DEFAULT 0,
        evidence_reference TEXT,
        responded_at TEXT,
        closed_at TEXT,
        closed_by INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (rfi_id, project_id),
        FOREIGN KEY (project_id) REFERENCES Projects (project_id),
        FOREIGN KEY (primary_issue_id, project_id) REFERENCES document_issues (issue_id, project_id),
        FOREIGN KEY (assigned_to) REFERENCES Users (user_id),
        FOREIGN KEY (submitted_by) REFERENCES Users (user_id),
        FOREIGN KEY (closed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_number_sequences (
        project_id INTEGER NOT NULL,
        document_type TEXT NOT NULL,
        last_number INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (project_id, document_type),
        FOREIGN KEY (project_id) REFERENCES Projects (project_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfi_related_issues (
        project_id INTEGER NOT NULL,
        rfi_id INTEGER NOT NULL,
        issue_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (rfi_id, issue_id),
        FOREIGN KEY (rfi_id, project_id) REFERENCES document_rfis (rfi_id, project_id),
        FOREIGN KEY (issue_id, project_id) REFERENCES document_issues (issue_id, project_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfi_status_history (
        history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        rfi_id INTEGER NOT NULL,
        old_status TEXT,
        new_status TEXT NOT NULL,
        reason TEXT NOT NULL,
        evidence_reference TEXT,
        transition_data TEXT,
        changed_by INTEGER NOT NULL,
        changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rfi_id, project_id) REFERENCES document_rfis (rfi_id, project_id),
        FOREIGN KEY (changed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfi_follow_ups (
        follow_up_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        rfi_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        is_completed INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NOT NULL,
        completed_by INTEGER,
        completed_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (follow_up_id, project_id),
        FOREIGN KEY (rfi_id, project_id) REFERENCES document_rfis (rfi_id, project_id),
        FOREIGN KEY (created_by) REFERENCES Users (user_id),
        FOREIGN KEY (completed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfi_follow_up_history (
        history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        follow_up_id INTEGER NOT NULL,
        old_status TEXT NOT NULL,
        new_status TEXT NOT NULL,
        reason TEXT NOT NULL,
        evidence_reference TEXT,
        changed_by INTEGER NOT NULL,
        changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follow_up_id, project_id) REFERENCES document_rfi_follow_ups (follow_up_id, project_id),
        FOREIGN KEY (changed_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_issue_images (
        image_id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL,
        object_key TEXT NOT NULL,
        original_name TEXT NOT NULL,
        content_type TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        uploaded_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (issue_id, project_id) REFERENCES document_issues (issue_id, project_id),
        FOREIGN KEY (uploaded_by) REFERENCES Users (user_id)
    )"
);
$db->exec(
    "CREATE TABLE document_rfi_images (
        image_id INTEGER PRIMARY KEY AUTOINCREMENT,
        rfi_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL,
        object_key TEXT NOT NULL,
        original_name TEXT NOT NULL,
        content_type TEXT NOT NULL,
        file_size INTEGER NOT NULL,
        uploaded_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rfi_id, project_id) REFERENCES document_rfis (rfi_id, project_id),
        FOREIGN KEY (uploaded_by) REFERENCES Users (user_id)
    )"
);

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$_SERVER = [];
$documents = new DocumentService($db);
$issueId = $documents->createIssue(1, 1, [
    'description' => 'Dimension conflict at north facade',
    'severity' => 'high',
    'location' => 'North facade',
]);
$secondaryIssueId = $documents->createIssue(1, 1, ['description' => 'Door opening clarification']);
$otherProjectIssueId = $documents->createIssue(2, 1, ['description' => 'Project two issue']);
documentTestCheck(
    (string) $db->query('SELECT issue_number FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn()
        === 'P1-ISS-001'
        && (string) $db->query('SELECT issue_number FROM document_issues WHERE issue_id = ' . $secondaryIssueId)->fetchColumn()
        === 'P1-ISS-002'
        && (string) $db->query('SELECT issue_number FROM document_issues WHERE issue_id = ' . $otherProjectIssueId)->fetchColumn()
        === 'P2-ISS-001',
    'Issue numbers should be unique to their project and sequential within that project.'
);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'open',
    'New issues should begin in open status.'
);
$documents->updateIssue(1, $issueId, [
    'description' => 'Updated north facade dimension conflict',
    'severity' => 'critical',
    'location' => 'North facade, level 2',
]);
$updatedIssue = $db->query(
    'SELECT description, severity, status, location FROM document_issues WHERE issue_id = ' . $issueId
)->fetch();
documentTestCheck(
    $updatedIssue['description'] === 'Updated north facade dimension conflict'
        && $updatedIssue['severity'] === 'critical'
        && $updatedIssue['location'] === 'North facade, level 2'
        && $updatedIssue['status'] === 'open',
    'Editing issue details should update ordinary fields without changing status.'
);
documentTestExpectStatus(
    static fn () => $documents->updateIssue(1, $otherProjectIssueId, [
        'description' => 'Cross-project edit',
        'severity' => 'low',
    ]),
    404,
    'Issue edits must reject records outside the selected project.'
);

$issueFollowUpId = $documents->addIssueFollowUp(1, 1, $issueId, 'Verify repair on site');
// Regression: the ledger sorts and labels by updated_at, so a status change
// must bump it even though it only touches workflow columns.
$db->exec("UPDATE document_issues SET updated_at = '2026-01-01 00:00:00' WHERE issue_id = {$issueId}");
$documents->transitionIssue(
    1,
    1,
    $issueId,
    'in_review',
    'Assigned for investigation',
    ['evidence_reference' => 'ARCH-INVESTIGATION-01'],
    false
);
documentTestCheck(
    (string) $db->query('SELECT updated_at FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() !== '2026-01-01 00:00:00',
    'Issue status transitions must refresh updated_at.'
);
$documents->transitionIssue(
    1,
    1,
    $issueId,
    'resolved',
    'Repair completed',
    ['resolution_result' => 'Revised dimension checked against site conditions.'],
    false
);
documentTestExpectStatus(
    static fn () => $documents->transitionIssue(
        1,
        1,
        $issueId,
        'closed',
        'Close request',
        [
            'resolution_result' => 'Revised dimension checked against site conditions.',
            'verification_status' => 'passed',
            'evidence_reference' => 'ARCH-ISSUE-01',
        ],
        false
    ),
    409,
    'An issue with an incomplete follow-up should not close.'
);
$documents->completeIssueFollowUp(
    1,
    1,
    $issueFollowUpId,
    'Site inspection passed',
    'ARCH-FOLLOWUP-01'
);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_issue_follow_up_history
         WHERE follow_up_id = {$issueFollowUpId}
           AND old_status = 'open' AND new_status = 'completed'
           AND reason = 'Site inspection passed'
           AND evidence_reference = 'ARCH-FOLLOWUP-01'"
    )->fetchColumn() === 1,
    'Issue follow-up completion should record the full status transition.'
);
$documents->transitionIssue(
    1,
    1,
    $issueId,
    'closed',
    'Site verification passed',
    [
        'resolution_result' => 'Revised dimension checked against site conditions.',
        'verification_status' => 'passed',
        'evidence_reference' => 'ARCH-ISSUE-01',
    ],
    false
);
documentTestExpectStatus(
    static fn () => $documents->transitionIssue(1, 1, $issueId, 'open', 'Reopen', [], false),
    403,
    'Only managers should reopen a closed issue.'
);
documentTestExpectStatus(
    static fn () => $documents->createRfi(1, 1, [
        'primary_issue_id' => $issueId,
        'assigned_to' => 2,
        'due_date' => '2030-01-15T17:00',
        'description' => 'RFI attempt on a closed issue',
    ]),
    409,
    'A closed issue must not spawn new RFIs.'
);
$documents->transitionIssue(1, 1, $issueId, 'open', 'New evidence requires review', [], true);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'open',
    'Reopening should return an issue to open.'
);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_issue_status_history
         WHERE issue_id = {$issueId} AND new_status = 'closed'
           AND evidence_reference = 'ARCH-ISSUE-01'"
    )->fetchColumn() === 1,
    'Closure evidence should remain in the status history after reopening.'
);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_issue_status_history
         WHERE issue_id = {$issueId} AND new_status = 'in_review'
           AND evidence_reference = 'ARCH-INVESTIGATION-01'"
    )->fetchColumn() === 1,
    'Status history should retain associated archive references.'
);

$rfiId = $documents->createRfi(1, 1, [
    'primary_issue_id' => $issueId,
    'related_issue_ids' => [$secondaryIssueId, $secondaryIssueId],
    'assigned_to' => 2,
    'priority' => 'high',
    'due_date' => '2030-01-15T17:00',
    'description' => 'Please confirm the revised facade dimension.',
    'evidence_required' => '1',
]);
$documents->updateRfi(1, $rfiId, [
    'primary_issue_id' => $issueId,
    'related_issue_ids' => [$secondaryIssueId],
    'assigned_to' => 2,
    'priority' => 'urgent',
    'due_date' => '2030-01-16T17:00',
    'description' => 'Please confirm the updated facade dimension.',
    'evidence_required' => '1',
]);
$updatedRfi = $db->query(
    'SELECT description, priority, due_date, evidence_required FROM document_rfis WHERE rfi_id = ' . $rfiId
)->fetch();
documentTestCheck(
    $updatedRfi['description'] === 'Please confirm the updated facade dimension.'
        && $updatedRfi['priority'] === 'urgent'
        && $updatedRfi['due_date'] === '2030-01-16 17:00:00'
        && (int) $updatedRfi['evidence_required'] === 1,
    'Draft RFI details should be editable.'
);
documentTestCheck(
    (int) $db->query(
        'SELECT COUNT(*) FROM document_rfi_related_issues WHERE rfi_id = ' . $rfiId
    )->fetchColumn() === 1,
    'Editing a draft RFI should replace its related issue links.'
);
$secondRfiId = $documents->createRfi(1, 1, [
    'primary_issue_id' => $issueId,
    'assigned_to' => 2,
    'due_date' => '2030-01-15T17:00',
    'description' => 'Please confirm the door opening detail.',
]);
documentTestCheck(
    (int) $db->query(
        'SELECT COUNT(*) FROM document_rfis WHERE project_id = 1 AND primary_issue_id = ' . $issueId
    )->fetchColumn() === 2,
    'A primary issue should be allowed to have multiple RFIs.'
);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'in_review',
    'Creating the first RFI must move the open issue into in_review.'
);
$linkedRfis = $documents->issueLinkedRfis(1);
documentTestCheck(
    isset($linkedRfis[$issueId]) && count($linkedRfis[$issueId]) === 2,
    'Issue detail must expose its linked RFIs.'
);
$otherProjectRfiId = $documents->createRfi(2, 1, [
    'primary_issue_id' => $otherProjectIssueId,
    'assigned_to' => 2,
    'due_date' => '2030-01-15T17:00',
    'description' => 'Project two RFI',
]);
documentTestCheck(
    (string) $db->query('SELECT rfi_number FROM document_rfis WHERE rfi_id = ' . $rfiId)->fetchColumn()
        === 'P1-RFI-001'
        && (string) $db->query('SELECT rfi_number FROM document_rfis WHERE rfi_id = ' . $secondRfiId)->fetchColumn()
        === 'P1-RFI-002'
        && (string) $db->query('SELECT rfi_number FROM document_rfis WHERE rfi_id = ' . $otherProjectRfiId)->fetchColumn()
        === 'P2-RFI-001',
    'RFI numbers should be unique to their project and sequential within that project.'
);
documentTestCheck(
    (int) $db->query(
        'SELECT COUNT(*) FROM document_rfi_related_issues WHERE rfi_id = ' . $rfiId
    )->fetchColumn() === 1,
    'Duplicate secondary issue links should be collapsed.'
);
documentTestExpectStatus(
    static fn () => $documents->createRfi(1, 1, [
        'primary_issue_id' => $issueId,
        'assigned_to' => 2,
        'description' => 'Missing due date',
    ]),
    422,
    'An RFI must require an explicitly entered due date.'
);
documentTestExpectStatus(
    static fn () => $documents->createRfi(1, 1, [
        'primary_issue_id' => $issueId,
        'related_issue_ids' => [$otherProjectIssueId],
        'assigned_to' => 2,
        'description' => 'Cross-project related issue attempt',
        'due_date' => '2030-01-15T17:00',
    ]),
    422,
    'An RFI must reject issue links from another project.'
);

$documents->transitionRfi(1, 1, $rfiId, 'submitted', 'Ready to send', [], false);
documentTestExpectStatus(
    static fn () => $documents->updateRfi(1, $rfiId, [
        'primary_issue_id' => $secondaryIssueId,
        'related_issue_ids' => [],
        'assigned_to' => 2,
        'priority' => 'high',
        'due_date' => '2030-01-16T17:00',
        'description' => 'Submitted RFI',
    ]),
    409,
    'Submitting an RFI should lock its issue associations.'
);
$documents->updateRfi(1, $rfiId, [
    'assigned_to' => 2,
    'priority' => 'high',
    'due_date' => '2030-01-16T17:00',
    'description' => 'Please confirm the issued facade dimension.',
    'evidence_required' => '1',
]);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_rfis WHERE rfi_id = ' . $rfiId)->fetchColumn() === 'submitted',
    'Editing non-association RFI fields must preserve its workflow status.'
);
$db->exec("UPDATE document_rfis SET updated_at = '2026-01-01 00:00:00' WHERE rfi_id = {$rfiId}");
$documents->transitionRfi(1, 1, $rfiId, 'under_review', 'Recipient started review', [], false);
documentTestCheck(
    (string) $db->query('SELECT updated_at FROM document_rfis WHERE rfi_id = ' . $rfiId)->fetchColumn() !== '2026-01-01 00:00:00',
    'RFI status transitions must refresh updated_at.'
);
documentTestExpectStatus(
    static fn () => $documents->transitionRfi(
        1,
        1,
        $rfiId,
        'responded',
        'Incomplete response',
        ['response_text' => 'Initial response', 'response_complete' => false],
        false
    ),
    422,
    'An incomplete response should not become a formal response.'
);
$documents->transitionRfi(
    1,
    1,
    $rfiId,
    'responded',
    'Formal response completed',
    ['response_text' => 'Use the approved facade dimension.', 'response_complete' => true],
    false
);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'in_review',
    'A formal RFI response must not change the issue status by itself.'
);
$rfiFollowUpId = $documents->addRfiFollowUp(1, 1, $rfiId, 'Update coordination drawing');
documentTestExpectStatus(
    static fn () => $documents->transitionRfi(
        1,
        1,
        $rfiId,
        'closed',
        'Close request',
        ['evidence_reference' => 'ARCH-RFI-01'],
        false
    ),
    409,
    'An RFI with an incomplete follow-up should not close.'
);
$documents->completeRfiFollowUp(
    1,
    1,
    $rfiFollowUpId,
    'Coordination drawing updated',
    'ARCH-FOLLOWUP-RFI-01'
);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_rfi_follow_up_history
         WHERE follow_up_id = {$rfiFollowUpId}
           AND old_status = 'open' AND new_status = 'completed'
           AND reason = 'Coordination drawing updated'
           AND evidence_reference = 'ARCH-FOLLOWUP-RFI-01'"
    )->fetchColumn() === 1,
    'RFI follow-up completion should record the full status transition.'
);
documentTestExpectStatus(
    static fn () => $documents->transitionRfi(
        1,
        1,
        $rfiId,
        'closed',
        'Close without evidence',
        [],
        false
    ),
    422,
    'An RFI requiring evidence should not close without an archive reference.'
);
$documents->transitionRfi(
    1,
    1,
    $rfiId,
    'closed',
    'Follow-up complete and evidence archived',
    ['evidence_reference' => 'ARCH-RFI-01'],
    false
);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'in_review',
    'Closing one RFI must keep the issue in review while another linked RFI is still open.'
);
$documents->transitionRfi(1, 1, $secondRfiId, 'submitted', 'Send the second request', [], false);
$documents->transitionRfi(1, 1, $secondRfiId, 'under_review', 'Second review started', [], false);
$documents->transitionRfi(
    1,
    1,
    $secondRfiId,
    'responded',
    'Second answer complete',
    ['response_text' => 'Door opening detail confirmed.', 'response_complete' => true],
    false
);
$documents->transitionRfi(1, 1, $secondRfiId, 'closed', 'Second RFI closed', [], false);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'resolved',
    'Closing the last linked RFI must move the in-review issue to resolved for review.'
);
documentTestExpectStatus(
    static fn () => $documents->transitionRfi(1, 1, $rfiId, 'submitted', 'Reopen', [], false),
    403,
    'Only managers should reopen a closed RFI.'
);
$documents->transitionRfi(1, 1, $rfiId, 'submitted', 'Further clarification needed', [], true);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_rfi_status_history
         WHERE rfi_id = {$rfiId} AND new_status = 'closed'
           AND evidence_reference = 'ARCH-RFI-01'"
    )->fetchColumn() === 1,
    'RFI closure evidence should remain in the status history after reopening.'
);
documentTestCheck(
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'in_review',
    'Reopening a closed RFI must move the resolved issue back to in_review.'
);
$documents->transitionIssue(
    1,
    1,
    $issueId,
    'resolved',
    'Ready for closure after re-review',
    ['resolution_result' => 'Reopened RFI answer applied on site.'],
    false
);
documentTestExpectStatus(
    static fn () => $documents->transitionIssue(
        1,
        1,
        $issueId,
        'closed',
        'Close while an RFI is open',
        [
            'resolution_result' => 'Reopened RFI answer applied on site.',
            'verification_status' => 'passed',
            'evidence_reference' => 'ARCH-ISSUE-GATE',
        ],
        false
    ),
    409,
    'An issue must not close while any linked RFI is still open.'
);
documentTestExpectStatus(
    static fn () => $documents->transitionIssue(1, 1, $otherProjectIssueId, 'in_review', 'Cross-project', [], false),
    404,
    'A record from another project must not be readable or writable through the current project.'
);
documentTestCheck(
    (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() >= 10,
    'Document actions and status transitions should write audit records.'
);

$plugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management')
);
$page = $plugin->dispatch('issues', []);
documentTestCheck(
    $page->type === 'view' && is_file((string) $page->viewPath),
    'The issues page should resolve its plugin view.'
);
$rfiPage = $plugin->dispatch('rfis', []);
documentTestCheck(
    $rfiPage->type === 'view' && is_file((string) $rfiPage->viewPath),
    'The RFI page should resolve its plugin view.'
);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['page' => 'rfis'];
$_POST = [];
ob_start();
(new Application($db))->run();
$renderedRfiPage = ob_get_clean();
documentTestCheck(
    str_contains($renderedRfiPage, 'RFI 管理')
        && str_contains($renderedRfiPage, 'RFI-')
        && str_contains($renderedRfiPage, '项目：Project One')
        && str_contains($renderedRfiPage, 'href="?page=projects">项目首页</a>')
        && str_contains($renderedRfiPage, 'href="?page=issues">问题管理</a>')
        && str_contains($renderedRfiPage, 'href="?page=documents">文档管理首页</a>')
        && str_contains($renderedRfiPage, 'rfis-table')
        && str_contains($renderedRfiPage, 'rfi.update'),
    'The RFI route should render project-scoped records and module navigation through the host application.'
);
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedIssuesPage = ob_get_clean();
documentTestCheck(
    str_contains($renderedIssuesPage, '问题管理')
        && str_contains($renderedIssuesPage, 'href="?page=projects">项目首页</a>')
        && str_contains($renderedIssuesPage, 'href="?page=rfis">RFI 管理</a>')
        && str_contains($renderedIssuesPage, '登记问题')
        && str_contains($renderedIssuesPage, 'Updated north facade dimension conflict')
        && str_contains($renderedIssuesPage, 'issues-table')
        && str_contains($renderedIssuesPage, 'issue.update')
        && str_contains($renderedIssuesPage, '关联 RFI（2）')
        && str_contains($renderedIssuesPage, 'P1-RFI-001'),
    'The issues route should render project-scoped records through the host application.'
);
$_GET = ['page' => 'documents'];
ob_start();
(new Application($db))->run();
$renderedDocumentsPage = ob_get_clean();
documentTestCheck(
    str_contains($renderedDocumentsPage, '文档管理')
        && str_contains($renderedDocumentsPage, 'RFI'),
    'The document landing route should render from the host application.'
);
$_GET = ['page' => 'projects'];
ob_start();
(new Application($db))->run();
$renderedProjectsPage = ob_get_clean();
// V2.1 home simplification: the home page carries only project basic info and
// the portfolio report; module entries live in the project sidebar instead.
documentTestCheck(
    str_contains($renderedProjectsPage, 'sidebar-item')
        && str_contains($renderedProjectsPage, '?page=documents'),
    'The authorized document-management entry should remain reachable via the project sidebar.'
);

unset($_SESSION['active_project_id']);
documentTestExpectStatus(
    static fn () => $plugin->dispatch('issues', []),
    409,
    'Document pages must require an active project.'
);
$_SESSION['active_project_id'] = 1;
$db->exec("DELETE FROM Role_Permissions WHERE permission_id = 1");
documentTestExpectStatus(
    static fn () => $plugin->dispatch('issues', []),
    403,
    'Document pages must enforce server-side view permission.'
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1)');
documentTestExpectStatus(
    static function () use ($db, $plugin): void {
        $db->exec('DELETE FROM Role_Permissions WHERE permission_id = 5');
        try {
            $plugin->dispatch('issues', []);
        } finally {
            $db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 5)');
        }
    },
    403,
    'Document pages must enforce the selected-project access permission.'
);

$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
documentTestCheck(
    isset($registry->migrations()['document.management/202611010002_document_management.sql']),
    'Enabled plugin migrations should be exposed to the installer.'
);

documentTestCheck(
    (int) $db->query('SELECT COUNT(*) FROM document_rfis WHERE rfi_id = ' . $secondRfiId)->fetchColumn() === 1,
    'A second RFI linked to the same primary issue should persist independently.'
);

// --- Document module system settings: shared image object storage ---
putenv('APP_R2_ACCOUNT_ID');
putenv('APP_R2_BUCKET');
putenv('APP_R2_ACCESS_KEY_ID');
putenv('APP_R2_SECRET_ACCESS_KEY');

$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 6');
documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings', []),
    403,
    'System settings must enforce the document settings permission.'
);
documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings.save', ['enabled' => '1']),
    403,
    'Saving system settings must enforce the document settings permission.'
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 6)');

$settingsPage = $plugin->dispatch('settings', []);
documentTestCheck(
    $settingsPage->type === 'view' && is_file((string) $settingsPage->viewPath),
    'The system settings page should resolve its plugin view.'
);

documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings.save', [
        'enabled' => '1',
        'account_id' => 'not-hex',
        'bucket' => 'project-o-images',
        'access_key_id' => 'r2-access-key-id-example',
        'secret_access_key' => 'r2-secret-access-key-example',
    ]),
    422,
    'The R2 account id must be validated as 32 hexadecimal characters.'
);
documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings.save', [
        'enabled' => '1',
        'account_id' => 'd8f4d2fa4b3c4d5e8f9012345678abcd',
        'bucket' => 'Invalid_Bucket!',
        'access_key_id' => 'r2-access-key-id-example',
        'secret_access_key' => 'r2-secret-access-key-example',
    ]),
    422,
    'The R2 bucket name must be validated.'
);
documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings.save', [
        'enabled' => '1',
        'account_id' => 'd8f4d2fa4b3c4d5e8f9012345678abcd',
        'bucket' => 'project-o-images',
        'access_key_id' => '',
        'secret_access_key' => '',
    ]),
    422,
    'Enabling image storage requires bucket credentials.'
);
documentTestExpectStatus(
    static fn () => $plugin->dispatch('settings.save', [
        'enabled' => '1',
        'account_id' => 'd8f4d2fa4b3c4d5e8f9012345678abcd',
        'bucket' => 'project-o-images',
        'access_key_id' => 'r2-access-key-id-example',
    ]),
    422,
    'Enabling image storage requires a secret when none is stored yet.'
);
$settingsSave = $plugin->dispatch('settings.save', [
    'enabled' => '1',
    'account_id' => 'D8F4D2FA4B3C4D5E8F9012345678ABCD',
    'bucket' => 'project-o-images',
    'access_key_id' => 'r2-access-key-id-example',
    'secret_access_key' => 'r2-secret-access-key-example',
]);
documentTestCheck(
    $settingsSave->type === 'redirect',
    'Saving valid system settings should redirect back to the settings page.'
);
$storedCloudStorage = (new CloudStorageSettings($db))->read();
documentTestCheck(
    $storedCloudStorage['exists']
        && $storedCloudStorage['enabled']
        && $storedCloudStorage['account_id'] === 'D8F4D2FA4B3C4D5E8F9012345678ABCD'
        && $storedCloudStorage['bucket'] === 'project-o-images'
        && $storedCloudStorage['access_key_id'] === 'r2-access-key-id-example'
        && $storedCloudStorage['secret_access_key'] === 'r2-secret-access-key-example',
    'Valid image storage settings must be persisted in System_Settings.'
);
$plugin->dispatch('settings.save', [
    'enabled' => '1',
    'account_id' => 'D8F4D2FA4B3C4D5E8F9012345678ABCD',
    'bucket' => 'project-o-images',
    'access_key_id' => 'r2-access-key-id-example',
    'secret_access_key' => '',
]);
documentTestCheck(
    (new CloudStorageSettings($db))->read()['secret_access_key'] === 'r2-secret-access-key-example',
    'A blank secret submit must keep the previously stored secret.'
);
$plugin->dispatch('settings.save', [
    'enabled' => '0',
    'account_id' => 'D8F4D2FA4B3C4D5E8F9012345678ABCD',
    'bucket' => 'project-o-images',
    'access_key_id' => 'r2-access-key-id-example',
]);
documentTestCheck(
    (new CloudStorageSettings($db))->read()['enabled'] === false,
    'Clearing the enable checkbox must disable the stored storage configuration.'
);
$plugin->dispatch('settings.save', [
    'enabled' => '1',
    'account_id' => 'D8F4D2FA4B3C4D5E8F9012345678ABCD',
    'bucket' => 'project-o-images',
    'access_key_id' => 'r2-access-key-id-example',
]);

$settingsAuditCount = 0;
$settingsAuditLeak = false;
foreach ($db->query(
    "SELECT details FROM audit_logs WHERE action = 'document.settings.storage.save'"
)->fetchAll() as $auditRow) {
    $settingsAuditCount++;
    if (str_contains((string) $auditRow['details'], 'r2-secret')) {
        $settingsAuditLeak = true;
    }
}
documentTestCheck(
    $settingsAuditCount >= 4 && !$settingsAuditLeak,
    'System settings changes must be audited without recording the secret.'
);

$sdkAvailable = class_exists(\Aws\S3\S3Client::class);
$storageAdapter = new R2ObjectStorage($db);
$storageStatus = $storageAdapter->configurationStatus();
documentTestCheck(
    $storageStatus['source'] === 'database'
        && $storageStatus['enabled'] === true
        && $storageStatus['sdk_available'] === $sdkAvailable
        && $storageStatus['configured'] === $sdkAvailable
        && $storageAdapter->isConfigured() === $sdkAvailable,
    'The adapter must prefer database configuration and gate readiness on the optional AWS SDK.'
);
if ($sdkAvailable) {
    $signedUrl = $storageAdapter->signedReadUrl('issues/1/1/test.png', 'image/png');
    documentTestCheck(
        str_contains($signedUrl, 'r2.cloudflarestorage.com')
            && str_contains($signedUrl, 'X-Amz-Signature=')
            && str_contains($signedUrl, 'X-Amz-Expires=300'),
        'Configured storage must produce five-minute presigned read URLs.'
    );
}

$_GET = ['page' => 'document-settings'];
ob_start();
(new Application($db))->run();
$renderedSettingsPage = ob_get_clean();
documentTestCheck(
    str_contains($renderedSettingsPage, '系统设置')
        && str_contains($renderedSettingsPage, 'value="document.settings.save"')
        && str_contains($renderedSettingsPage, '图片存储')
        && str_contains($renderedSettingsPage, 'value="project-o-images"')
        && !str_contains($renderedSettingsPage, 'r2-secret-access-key-example'),
    'The document settings route must render the storage configuration without exposing the secret.'
);
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedIssuesWithSettings = ob_get_clean();
documentTestCheck(
    str_contains($renderedIssuesWithSettings, 'href="?page=document-settings"'),
    'Authorized document pages must link to the system settings.'
);

$db->exec("DELETE FROM System_Settings WHERE setting_key LIKE 'cloud_storage.%'");
putenv('APP_R2_ACCOUNT_ID=d8f4d2fa4b3c4d5e8f9012345678abcd');
putenv('APP_R2_BUCKET=project-o-images');
putenv('APP_R2_ACCESS_KEY_ID=r2-access-key-id-example');
putenv('APP_R2_SECRET_ACCESS_KEY=r2-secret-access-key-example');
documentTestCheck(
    (new R2ObjectStorage($db))->configurationStatus()['source'] === 'environment',
    'Without database configuration the adapter must fall back to environment variables.'
);
putenv('APP_R2_ACCOUNT_ID');
putenv('APP_R2_BUCKET');
putenv('APP_R2_ACCESS_KEY_ID');
putenv('APP_R2_SECRET_ACCESS_KEY');
$unconfiguredAdapter = new R2ObjectStorage($db);
documentTestCheck(
    $unconfiguredAdapter->configurationStatus()['source'] === 'none',
    'With neither database nor environment configuration the adapter must report none.'
);
documentTestExpectStatus(
    static fn () => $unconfiguredAdapter->upload('issues/1/1/test.png', 'php://memory', 'image/png'),
    503,
    'Unconfigured storage must refuse uploads with a diagnosable error.'
);

// --- Issue and RFI images use the shared private object storage ---
documentTestCheck(
    isset($registry->migrations()['document.management/202611060001_issue_rfi_images.sql']),
    'The issue/RFI image migration must be exposed to the installer.'
);
$faviconPng = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    true
);
$imageTempPath = tempnam(sys_get_temp_dir(), 'document-image-');
documentTestCheck(
    $imageTempPath !== false && is_string($faviconPng),
    'The image fixture should be created.'
);
file_put_contents((string) $imageTempPath, (string) $faviconPng);

$fakeStorage = new DocumentTestImageStorage();
$imageService = new DocumentImageService($db);
$issueImageId = $imageService->addIssueImage(
    1,
    $issueId,
    1,
    (string) $imageTempPath,
    'defect.png',
    $fakeStorage
);
documentTestCheck(
    count($fakeStorage->uploaded) === 1,
    'Adding an issue image must upload the object to the configured storage.'
);
$uploadedIssueKey = (string) array_key_first($fakeStorage->uploaded);
documentTestCheck(
    str_starts_with($uploadedIssueKey, 'issues/1/' . $issueId . '/')
        && str_ends_with($uploadedIssueKey, '.png'),
    'Issue images must be stored under a project-scoped object key.'
);
$issueImages = $imageService->issueImagesForProject(1)[$issueId] ?? [];
documentTestCheck(
    count($issueImages) === 1
        && $issueImages[0]['original_name'] === 'defect.png'
        && (int) $issueImages[0]['file_size'] === strlen((string) $faviconPng),
    'Issue image metadata must be persisted for the current project only.'
);
documentTestExpectStatus(
    static fn () => $imageService->addIssueImage(2, $issueId, 1, (string) $imageTempPath, 'cross.png', $fakeStorage),
    404,
    'Images must not be attached to another project issue.'
);
$rfiImageId = $imageService->addRfiImage(
    1,
    $rfiId,
    1,
    (string) $imageTempPath,
    'rfi-evidence.png',
    $fakeStorage
);
$rfiImages = $imageService->rfiImagesForProject(1)[$rfiId] ?? [];
documentTestCheck(
    count($rfiImages) === 1 && $rfiImages[0]['original_name'] === 'rfi-evidence.png',
    'RFI image metadata must be persisted.'
);
$imageService->deleteIssueImage(1, $issueId, $issueImageId, $fakeStorage);
documentTestCheck(
    (int) $db->query('SELECT COUNT(*) FROM document_issue_images WHERE issue_id = ' . $issueId)->fetchColumn() === 0
        && in_array($uploadedIssueKey, $fakeStorage->deleted, true),
    'Deleting an issue image must remove both the reference and the stored object.'
);
@unlink((string) $imageTempPath);

$reporter = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management', $fakeStorage)
);
$db->exec('DELETE FROM Role_Permissions WHERE permission_id IN (3, 4)');
documentTestExpectStatus(
    static fn () => $reporter->dispatch('issue.image.upload', ['issue_id' => (string) $issueId]),
    403,
    'Uploading issue images must require issue editing permission.'
);
documentTestExpectStatus(
    static fn () => $reporter->dispatch('rfi.image.delete', [
        'rfi_id' => (string) $rfiId,
        'image_id' => (string) $rfiImageId,
    ]),
    403,
    'Deleting RFI images must require issue editing permission.'
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 3), (1, 4)');

$unconfiguredPlugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management')
);
documentTestExpectStatus(
    static fn () => $unconfiguredPlugin->dispatch('issue.image.upload', ['issue_id' => (string) $issueId]),
    503,
    'Image uploads must fail clearly when object storage is not configured.'
);
documentTestExpectStatus(
    static fn () => $reporter->dispatch('issue.image.upload', [
        'issue_id' => (string) $issueId,
        '_files' => ['image' => ['error' => UPLOAD_ERR_NO_FILE]],
    ]),
    422,
    'Image uploads must reject missing files.'
);
$rfiImageDelete = $reporter->dispatch('rfi.image.delete', [
    'rfi_id' => (string) $rfiId,
    'image_id' => (string) $rfiImageId,
]);
documentTestCheck(
    $rfiImageDelete->type === 'redirect',
    'Deleting an RFI image through the plugin route should redirect back to the RFI page.'
);
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'document.rfi.image.delete'"
    )->fetchColumn() >= 1,
    'Image deletions must be audited.'
);

$cloudStorage = new CloudStorageSettings($db);
$cloudStorage->save([
    'enabled' => '1',
    'account_id' => 'd8f4d2fa4b3c4d5e8f9012345678abcd',
    'bucket' => 'project-o-images',
    'access_key_id' => 'r2-access-key-id-example',
    'secret_access_key' => 'r2-secret-access-key-example',
]);
$db->exec(
    "INSERT INTO document_issue_images (issue_id, project_id, object_key, original_name, content_type, file_size, uploaded_by)
     VALUES ({$issueId}, 1, 'issues/1/{$issueId}/signed.png', 'signed.png', 'image/png', 68, 1)"
);
$signedIssueImageId = (int) $db->lastInsertId();
$db->exec(
    "INSERT INTO document_rfi_images (rfi_id, project_id, object_key, original_name, content_type, file_size, uploaded_by)
     VALUES ({$rfiId}, 1, 'rfis/1/{$rfiId}/signed.png', 'signed-rfi.png', 'image/png', 68, 1)"
);
$signedRfiImageId = (int) $db->lastInsertId();
$realStoragePlugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management')
);
$sdkAvailable = class_exists(\Aws\S3\S3Client::class);
$signedIssues = $realStoragePlugin->dispatch('issues', []);
$signedRfis = $realStoragePlugin->dispatch('rfis', []);
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedIssuesWithImages = ob_get_clean();
$_GET = ['page' => 'rfis'];
ob_start();
(new Application($db))->run();
$renderedRfisWithImages = ob_get_clean();
if ($sdkAvailable) {
    documentTestCheck(
        str_contains($renderedIssuesWithImages, 'page=document-image')
            && str_contains($renderedIssuesWithImages, 'type=issue')
            && str_contains($renderedRfisWithImages, 'page=document-image')
            && str_contains($renderedRfisWithImages, 'type=rfi'),
        'Issue and RFI images must be served through the application image route.'
    );
} else {
    documentTestCheck(
        str_contains($renderedIssuesWithImages, '图片存储尚未配置')
            && str_contains($renderedRfisWithImages, '图片存储尚未配置'),
        'Pages must explain when image storage is unavailable.'
    );
}
documentTestCheck(
    str_contains($renderedIssuesWithImages, 'value="issue.image.upload"')
        || !$sdkAvailable,
    'Issue dialogs must expose the image upload form when storage is configured.'
);

DocumentTestImageStorage::$body = $faviconPng;
$fakeImagePlugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management', new DocumentTestImageStorage())
);
$issueImageResponse = $fakeImagePlugin->dispatch('document.image.view', [
    'type' => 'issue',
    'issue_id' => (string) $issueId,
    'image_id' => (string) $signedIssueImageId,
]);
documentTestCheck(
    $issueImageResponse->type === 'inline'
        && ($issueImageResponse->data['content_type'] ?? '') === 'image/png'
        && ($issueImageResponse->data['body'] ?? '') === $faviconPng
        && str_contains((string) ($issueImageResponse->data['filename'] ?? ''), '问题图片-'),
    'Issue images must be streamed inline with a sequential file name.'
);
$rfiImageResponse = $fakeImagePlugin->dispatch('document.image.view', [
    'type' => 'rfi',
    'rfi_id' => (string) $rfiId,
    'image_id' => (string) $signedRfiImageId,
]);
documentTestCheck(
    $rfiImageResponse->type === 'inline'
        && str_contains((string) ($rfiImageResponse->data['filename'] ?? ''), 'RFI图片-'),
    'RFI images must be streamed inline with a sequential file name.'
);
$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 1');
documentTestExpectStatus(
    static fn () => $fakeImagePlugin->dispatch('document.image.view', [
        'type' => 'issue',
        'issue_id' => (string) $issueId,
        'image_id' => (string) $signedIssueImageId,
    ]),
    403,
    'Document image delivery must enforce the issue view permission.'
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1)');
documentTestExpectStatus(
    static fn () => $fakeImagePlugin->dispatch('document.image.view', [
        'type' => 'issue',
        'issue_id' => (string) $issueId,
        'image_id' => '999999',
    ]),
    404,
    'Unknown document image ids must be rejected.'
);
$_SESSION['active_project_id'] = 2;
documentTestExpectStatus(
    static fn () => $fakeImagePlugin->dispatch('document.image.view', [
        'type' => 'issue',
        'issue_id' => (string) $issueId,
        'image_id' => (string) $signedIssueImageId,
    ]),
    404,
    'Document images must not cross project boundaries.'
);
$_SESSION['active_project_id'] = 1;
$previousDocViewAdapter = getenv('APP_OBJECT_STORAGE_ADAPTER');
putenv('APP_OBJECT_STORAGE_ADAPTER=DocumentTestImageStorage');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [
    'page' => 'document-image',
    'type' => 'issue',
    'issue_id' => (string) $issueId,
    'image_id' => (string) $signedIssueImageId,
];
http_response_code(200);
ob_start();
(new Application($db))->run();
$inlineDocumentBody = (string) ob_get_clean();
documentTestCheck(
    http_response_code() === 200 && $inlineDocumentBody === $faviconPng,
    'The application must stream document images inline through its image route.'
);
if ($previousDocViewAdapter === false) {
    putenv('APP_OBJECT_STORAGE_ADAPTER');
} else {
    putenv('APP_OBJECT_STORAGE_ADAPTER=' . $previousDocViewAdapter);
}
$_GET = [];
documentTestCheck(
    !str_contains($renderedIssuesWithImages, 'r2-secret-access-key-example')
        && !str_contains($renderedRfisWithImages, 'r2-secret-access-key-example'),
    'Rendered document pages must never expose the storage secret.'
);
$issueWithImages = array_values(array_filter(
    $signedIssues->data['issues'],
    static fn (array $row): bool => (int) $row['issue_id'] === $issueId
));
$rfiWithImages = array_values(array_filter(
    $signedRfis->data['rfis'],
    static fn (array $row): bool => (int) $row['rfi_id'] === $rfiId
));
documentTestCheck(
    $issueWithImages !== [] && $issueWithImages[0]['images'] !== []
        && $rfiWithImages !== [] && $rfiWithImages[0]['images'] !== [],
    'Issue and RFI page data must include their image attachments.'
);

$db->exec('DELETE FROM document_issue_images');
$db->exec('DELETE FROM document_rfi_images');
$db->exec("DELETE FROM System_Settings WHERE setting_key LIKE 'cloud_storage.%'");

// --- Timezone: UTC storage with project default and per-user display ---
documentTestCheck(
    Support::resolveTimezone($db) === 'Asia/Shanghai',
    'The system default timezone must be the project timezone.'
);
(new ProfileService($db))->saveTimezone(1, 'Europe/London');
documentTestCheck(
    Support::resolveTimezone($db) === 'Europe/London',
    'A personal timezone must override the project default.'
);
documentTestExpectStatus(
    static fn () => (new ProfileService($db))->saveTimezone(1, 'Not/AZone'),
    422,
    'Invalid personal timezones must be rejected.'
);
(new ProfileService($db))->saveTimezone(1, '');
documentTestCheck(
    Support::resolveTimezone($db) === 'Asia/Shanghai',
    'Clearing the personal timezone must fall back to the project default.'
);

$db->exec("UPDATE Users SET timezone = 'Asia/Shanghai' WHERE user_id = 1");
$db->exec(
    "UPDATE document_issues
     SET reported_at = '2026-03-03 10:00:00', updated_at = '2026-03-03 10:00:00'
     WHERE issue_id = {$issueId}"
);
$_SERVER = ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/index.php'];
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$issuesInChinaTime = (string) ob_get_clean();
documentTestCheck(
    str_contains($issuesInChinaTime, '2026-03-03 18:00'),
    'UTC timestamps must be displayed in the effective timezone.'
);
$_GET = ['page' => 'profile'];
ob_start();
(new Application($db))->run();
$profilePage = (string) ob_get_clean();
documentTestCheck(
    str_contains($profilePage, '个人设置')
        && str_contains($profilePage, 'Asia/Shanghai')
        && str_contains($profilePage, 'name="timezone"'),
    'Every signed-in user must be able to choose their display timezone.'
);
documentTestCheck(
    str_contains($profilePage, 'name="current_password"')
        && str_contains($profilePage, 'name="new_password"')
        && str_contains($profilePage, 'name="new_password_confirmation"')
        && str_contains($profilePage, 'value="profile.password"'),
    'Personal settings must expose self-service password change.'
);

Support::setDisplayTimezone('Asia/Shanghai');
$timezoneRfiId = $documents->createRfi(1, 1, [
    'primary_issue_id' => (string) $issueId,
    'assigned_to' => '1',
    'priority' => 'medium',
    'due_date' => '2030-02-01T09:30',
    'description' => 'Timezone conversion check',
]);
$storedDueDate = (string) $db->query(
    'SELECT due_date FROM document_rfis WHERE rfi_id = ' . $timezoneRfiId
)->fetchColumn();
documentTestCheck(
    $storedDueDate === '2030-02-01 01:30:00',
    'Local due-date input must be converted to UTC before storage.'
);
Support::setDisplayTimezone('UTC');

// --- Issue Excel export ---
$issueExport = $plugin->dispatch('issue.export', []);
documentTestCheck(
    $issueExport->type === 'download'
        && ($issueExport->data['content_type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        && preg_match('/^问题信息追踪表_\d{8}_\d+\.xlsx$/u', (string) ($issueExport->data['filename'] ?? '')) === 1,
    'Issue export should produce an .xlsx download with the table/date/daily-sequence name.'
);
$issueExportSequence = (int) preg_replace(
    '/^问题信息追踪表_\d{8}_(\d+)\.xlsx$/u',
    '$1',
    (string) $issueExport->data['filename']
);
$issueSheet = documentTestXlsxPart($issueExport, 'xl/worksheets/sheet1.xml');
$issueStyles = documentTestXlsxPart($issueExport, 'xl/styles.xml');
$issueRows = count($plugin->dispatch('issues', [])->data['issues']);
documentTestCheck(
    str_contains($issueSheet, 'Project One问题信息追踪表')
        && str_contains($issueSheet, '问题编号')
        && str_contains($issueSheet, '问题描述')
        && str_contains($issueSheet, '登记时间')
        && str_contains($issueSheet, '内容统计:')
        && !str_contains($issueSheet, 'Project two issue')
        && substr_count($issueSheet, '<row r=') === $issueRows + 6,
    'The issue export must contain the current project issues and status statistics.'
);
documentTestCheck(
    substr_count($issueStyles, 'wrapText="1"') >= 2,
    'The issue export must wrap text inside the fixed column widths.'
);
$secondIssueExport = $plugin->dispatch('issue.export', []);
$secondIssueSequence = (int) preg_replace(
    '/^问题信息追踪表_\d{8}_(\d+)\.xlsx$/u',
    '$1',
    (string) ($secondIssueExport->data['filename'] ?? '')
);
documentTestCheck(
    $secondIssueSequence === $issueExportSequence + 1,
    'Issue export file names must use an incrementing daily sequence.'
);
$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 1');
documentTestExpectStatus(
    static fn () => $plugin->dispatch('issue.export', []),
    403,
    'Issue export must enforce the issue view permission.'
);
$db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 1)');

// Embedded issue image in the export.
$exportPngPath = tempnam(sys_get_temp_dir(), 'document-export-image-');
documentTestCheck($exportPngPath !== false, 'The export image fixture should be created.');
file_put_contents((string) $exportPngPath, $faviconPng);
DocumentTestImageStorage::$fileUrl = 'file://' . $exportPngPath;
$db->exec(
    "INSERT INTO document_issue_images (issue_id, project_id, object_key, original_name, content_type, file_size, uploaded_by)
     VALUES ({$issueId}, 1, 'issues/1/{$issueId}/export.png', 'export.png', 'image/png', 70, 1)"
);
$exportImagePlugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management', new DocumentTestImageStorage())
);
$issueExportWithImage = $exportImagePlugin->dispatch('issue.export', []);
$imageExportPath = tempnam(sys_get_temp_dir(), 'document-image-export-');
file_put_contents((string) $imageExportPath, (string) ($issueExportWithImage->data['body'] ?? ''));
$imageExportZip = new ZipArchive();
documentTestCheck($imageExportZip->open((string) $imageExportPath) === true, 'The image export should be readable.');
$imageExportDrawing = (string) $imageExportZip->getFromName('xl/drawings/drawing1.xml');
$imageExportRels = (string) $imageExportZip->getFromName('xl/drawings/_rels/drawing1.xml.rels');
$imageExportMedia = $imageExportZip->locateName('xl/media/image1.png') !== false;
$imageExportZip->close();
@unlink((string) $imageExportPath);
DocumentTestImageStorage::$fileUrl = '';
@unlink((string) $exportPngPath);
documentTestCheck(
    $imageExportMedia
        && str_contains($imageExportDrawing, 'cy="714375"')
        && str_contains($imageExportRels, 'page=document-image'),
    'Issue exports must embed the first readable image at the fixed height with a link to the image route.'
);
$db->exec('DELETE FROM document_issue_images');

// --- Issue location selected from project spaces (2026-11-15) ---
$spaceCatalog = new \ProjectO\Modules\ProjectFoundation\ProjectSpaceService($db);
$locatedDocuments = new DocumentService($db, $spaceCatalog);

$catalog = $locatedDocuments->issueLocationCatalog(1);
$catalogValues = array_column($catalog['options'], 'value');
documentTestCheck(
    count($catalog['options']) === 6
        && in_array('building:10', $catalogValues, true)
        && in_array('floor:11', $catalogValues, true)
        && in_array('area:13', $catalogValues, true)
        && in_array('room:14', $catalogValues, true),
    'The location catalogue should offer every active space type of the current project.'
);
documentTestCheck(
    !in_array('building:20', $catalogValues, true) && !in_array('room:24', $catalogValues, true),
    'Space options must never include another project.'
);

$spaceIssueId = $locatedDocuments->createIssue(1, 1, [
    'description' => 'Crack reported in room 101',
    'severity' => 'high',
    'location' => 'room:14',
]);
$spaceIssueRow = $db->query(
    'SELECT location, location_type, location_building_id, location_floor_id, location_area_id, location_room_id
     FROM document_issues WHERE issue_id = ' . $spaceIssueId
)->fetch();
documentTestCheck(
    $spaceIssueRow['location'] === null
        && $spaceIssueRow['location_type'] === 'room'
        && (int) $spaceIssueRow['location_building_id'] === 10
        && (int) $spaceIssueRow['location_floor_id'] === 11
        && $spaceIssueRow['location_area_id'] === null
        && (int) $spaceIssueRow['location_room_id'] === 14,
    'Selecting a room must store the structured reference together with its parent chain.'
);
$findListedIssue = static function (DocumentService $service, int $projectId, int $wantedId): ?array {
    foreach ($service->listIssues($projectId) as $listed) {
        if ((int) $listed['issue_id'] === $wantedId) {
            return $listed;
        }
    }
    return null;
};
$listedSpaceIssue = $findListedIssue($locatedDocuments, 1, $spaceIssueId);
documentTestCheck(
    $listedSpaceIssue !== null
        && $listedSpaceIssue['location_name'] === 'Tower A Ground 101'
        && $listedSpaceIssue['location_value'] === 'room:14',
    'Issue lists must resolve the selected space into a readable location name.'
);

$areaIssueId = $locatedDocuments->createIssue(1, 1, [
    'description' => 'Area level issue',
    'location' => 'area:13',
]);
documentTestCheck(
    ($findListedIssue($locatedDocuments, 1, $areaIssueId)['location_name'] ?? '') === 'Tower A Lobby',
    'Area selections must resolve to building plus area name.'
);
$unnamedFloorIssueId = $locatedDocuments->createIssue(1, 1, [
    'description' => 'Floor without a name',
    'location' => 'floor:12',
]);
documentTestCheck(
    ($findListedIssue($locatedDocuments, 1, $unnamedFloorIssueId)['location_name'] ?? '') === 'Tower A 02',
    'A floor without a name must fall back to its floor number.'
);

// Permission, project and archive boundaries are enforced server-side.
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Cross project space',
        'location' => 'room:24',
    ]),
    404,
    'Registering an issue must reject spaces that belong to another project.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->updateIssue(1, $spaceIssueId, [
        'description' => 'Cross project space edit',
        'severity' => 'high',
        'location' => 'building:20',
    ]),
    404,
    'Editing an issue must reject spaces that belong to another project.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Unknown space',
        'location' => 'room:9999',
    ]),
    404,
    'Unknown space references must be rejected.'
);
foreach (['North facade', 'room:0', 'stair:14', 'room:14;DROP'] as $invalidLocation) {
    documentTestExpectStatus(
        static fn () => $locatedDocuments->createIssue(1, 1, [
            'description' => 'Invalid location payload',
            'location' => $invalidLocation,
        ]),
        422,
        'Free text and malformed space references must be rejected once spaces are available.'
    );
}
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Array location payload',
        'location' => ['room:14'],
    ]),
    422,
    'A non-string location must be rejected.'
);

// An empty selection clears both the structured reference and the legacy text.
$locatedDocuments->updateIssue(1, $spaceIssueId, [
    'description' => 'Crack reported in room 101',
    'severity' => 'high',
    'location' => '',
]);
$clearedRow = $db->query(
    'SELECT location, location_type, location_building_id, location_floor_id, location_area_id, location_room_id
     FROM document_issues WHERE issue_id = ' . $spaceIssueId
)->fetch();
documentTestCheck(
    $clearedRow['location'] === null
        && $clearedRow['location_type'] === null
        && $clearedRow['location_building_id'] === null
        && $clearedRow['location_floor_id'] === null
        && $clearedRow['location_area_id'] === null
        && $clearedRow['location_room_id'] === null,
    'Choosing no location must clear every location column.'
);

// Historical free-text locations stay readable and are replaced only on re-pick.
$legacyListed = $findListedIssue($locatedDocuments, 1, $issueId);
documentTestCheck(
    $legacyListed !== null
        && $legacyListed['location_name'] === 'North facade, level 2'
        && $legacyListed['location_value'] === '',
    'Legacy free-text locations must keep displaying their original text.'
);
$locatedDocuments->updateIssue(1, $issueId, [
    'description' => 'Updated north facade dimension conflict',
    'severity' => 'critical',
    'location' => 'floor:12',
]);
$migratedRow = $db->query(
    'SELECT location, location_type, location_floor_id FROM document_issues WHERE issue_id = ' . $issueId
)->fetch();
documentTestCheck(
    $migratedRow['location'] === null
        && $migratedRow['location_type'] === 'floor'
        && (int) $migratedRow['location_floor_id'] === 12,
    'Re-picking a space must replace the legacy text with the structured reference.'
);

// End-to-end through the plugin registry and host application.
$locationRegistry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$registryCreate = $locationRegistry->dispatch('POST', 'issue.create', [
    'description' => 'Registered through the plugin route',
    'severity' => 'medium',
    'location' => 'area:13',
]);
documentTestCheck(
    $registryCreate !== null && $registryCreate->type === 'redirect',
    'The issue.create route must accept a project space selection.'
);
documentTestCheck(
    (int) $db->query(
        "SELECT location_area_id FROM document_issues WHERE description = 'Registered through the plugin route'"
    )->fetchColumn() === 13,
    'The plugin route must persist the selected space.'
);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedLocationPage = (string) ob_get_clean();
documentTestCheck(
    str_contains($renderedLocationPage, '<select name="location" class="loc-fallback"')
        && str_contains($renderedLocationPage, '<optgroup label="楼栋">')
        && str_contains($renderedLocationPage, '<optgroup label="楼层">')
        && str_contains($renderedLocationPage, '<optgroup label="区域">')
        && str_contains($renderedLocationPage, '<optgroup label="房间">')
        && str_contains($renderedLocationPage, 'value="room:14"')
        && !str_contains($renderedLocationPage, 'value="room:24"')
        && !str_contains($renderedLocationPage, 'name="location" maxlength="255"'),
    'The issue forms must keep a grouped native fallback select, never a free-text location field.'
);
documentTestCheck(
    str_contains($renderedLocationPage, 'Tower A Lobby'),
    'The rendered page must show resolved space names.'
);
// A record still holding free text must offer to keep it, pre-selected.
$legacyRenderIssueId = $locatedDocuments->createIssue(1, 1, ['description' => 'Legacy render check']);
$db->exec("UPDATE document_issues SET location = 'North facade, level 2' WHERE issue_id = " . $legacyRenderIssueId);
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedLegacyPage = (string) ob_get_clean();
documentTestCheck(
    str_contains($renderedLegacyPage, '<option value="legacy" selected>保留原位置：North facade, level 2</option>')
        && str_contains($renderedLegacyPage, '<option value="">不指定位置（清空）</option>'),
    'Editing a legacy location must preselect keeping it and offer an explicit clear option.'
);

// Editing unrelated fields must not discard a historical free-text location.
$legacyOnlyIssueId = $locatedDocuments->createIssue(1, 1, ['description' => 'Legacy placeholder']);
$db->exec("UPDATE document_issues SET location = 'North stair, level 3' WHERE issue_id = " . $legacyOnlyIssueId);
$locatedDocuments->updateIssue(1, $legacyOnlyIssueId, [
    'description' => 'Legacy placeholder edited',
    'severity' => 'low',
    'location' => 'legacy',
]);
$keptLegacy = $db->query(
    'SELECT location, location_type FROM document_issues WHERE issue_id = ' . $legacyOnlyIssueId
)->fetch();
documentTestCheck(
    $keptLegacy['location'] === 'North stair, level 3' && $keptLegacy['location_type'] === null,
    'Keeping the original location must preserve the historical text when other fields change.'
);
$locatedDocuments->updateIssue(1, $legacyOnlyIssueId, [
    'description' => 'Legacy placeholder edited',
    'severity' => 'low',
    'location' => '',
]);
documentTestCheck(
    ($findListedIssue($locatedDocuments, 1, $legacyOnlyIssueId)['location_name'] ?? 'x') === '',
    'Explicitly choosing no location must clear a historical text location.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Keep marker on create',
        'location' => 'legacy',
    ]),
    422,
    'The keep-original marker must be rejected when there is no original location.'
);

// Cross-plugin access must go through the declared core contract.
$contractGuardRejected = false;
try {
    $emptyServices = new \ProjectO\Plugin\PluginServiceRegistry();
    $undeclaredContext = new PluginContext(
        $db,
        dirname(__DIR__) . '/modules/document-management',
        null,
        null,
        $emptyServices->forPlugin('document.management')
    );
    $undeclaredContext->service(\ProjectO\Contracts\ProjectSpaceCatalog::class);
} catch (\ProjectO\Plugin\PluginContractException $exception) {
    $contractGuardRejected = true;
}
documentTestCheck(
    $contractGuardRejected,
    'A plugin must not resolve a contract it has not declared in its manifest.'
);

$testServices = new \ProjectO\Plugin\PluginServiceRegistry();
$testServices->declare('document.management', [\ProjectO\Contracts\ProjectSpaceCatalog::class]);
$testServices->publish(
    'project.foundation',
    \ProjectO\Contracts\ProjectSpaceCatalog::class,
    $spaceCatalog
);
$locatedPlugin = new DocumentManagementPlugin(new PluginContext(
    $db,
    dirname(__DIR__) . '/modules/document-management',
    new DocumentTestImageStorage(),
    null,
    $testServices->forPlugin('document.management')
));
documentTestCheck(
    $locatedPlugin->dispatch('issues', [])->data['spaceSelectionReady'] === true,
    'The issues page must report space selection as available when the contract resolves.'
);
$locatedExport = $locatedPlugin->dispatch('issue.export', []);
$locatedExportSheet = documentTestXlsxPart($locatedExport, 'xl/worksheets/sheet1.xml');
documentTestCheck(
    str_contains($locatedExportSheet, 'Tower A Lobby'),
    'The issue export must contain the resolved space name in the location column.'
);

// Without a provider the page stays usable and explains the limitation.
$noSpacePlugin = new DocumentManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/document-management')
);
documentTestCheck(
    $noSpacePlugin->dispatch('issues', [])->data['spaceSelectionReady'] === false,
    'The issues page must report space selection as unavailable without a provider.'
);
$noSpaceDocuments = new DocumentService($db);
documentTestExpectStatus(
    static fn () => $noSpaceDocuments->createIssue(1, 1, [
        'description' => 'Space selection without a provider',
        'location' => 'room:14',
    ]),
    503,
    'A space selection must fail clearly when the space contract is unavailable.'
);
documentTestCheck(
    $noSpaceDocuments->createIssue(1, 1, ['description' => 'Issue without any location']) > 0,
    'Issues must remain maintainable when the space contract is unavailable.'
);

// Authorization is checked before any space lookup, so a rejected request
// cannot reveal whether a space exists in another project.
$createDenied = false;
$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 2');
try {
    documentTestExpectStatus(
        static fn () => $locationRegistry->dispatch('POST', 'issue.create', [
            'description' => 'Unauthorized space probe',
            'location' => 'room:24',
        ]),
        403,
        'Registering an issue must check the create permission before validating the space.'
    );
    $createDenied = true;
} finally {
    $db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 2)');
}
documentTestCheck($createDenied, 'The create permission check must run before the space lookup.');
$editDenied = false;
$db->exec('DELETE FROM Role_Permissions WHERE permission_id IN (3, 4)');
try {
    documentTestExpectStatus(
        static fn () => $locationRegistry->dispatch('POST', 'issue.update', [
            'issue_id' => (string) $spaceIssueId,
            'description' => 'Unauthorized space probe',
            'severity' => 'high',
            'location' => 'room:24',
        ]),
        403,
        'Editing an issue must check the edit permission before validating the space.'
    );
    $editDenied = true;
} finally {
    $db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 3), (1, 4)');
}
documentTestCheck($editDenied, 'The edit permission check must run before the space lookup.');
documentTestCheck(
    (int) $db->query(
        "SELECT COUNT(*) FROM document_issues WHERE description = 'Unauthorized space probe'"
    )->fetchColumn() === 0,
    'A rejected request must not create or modify any issue.'
);

// Archived spaces leave the options but keep their label for existing records.
$db->exec(
    "INSERT INTO project_foundation_archive_records (entity_type, entity_id, project_id, archived_by)
     VALUES ('room', 15, 1, 1), ('floor', 11, 1, 1)"
);
$archivedCatalog = $locatedDocuments->issueLocationCatalog(1);
$archivedValues = array_column($archivedCatalog['options'], 'value');
documentTestCheck(
    !in_array('room:15', $archivedValues, true)
        && !in_array('floor:11', $archivedValues, true)
        && !in_array('room:14', $archivedValues, true)
        && isset($archivedCatalog['labels']['room:15'], $archivedCatalog['labels']['room:14']),
    'Archived spaces and children of an archived floor must leave the options but keep their labels.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Archived space selection',
        'location' => 'room:15',
    ]),
    422,
    'An archived space must not be selectable for a new issue.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Space under an archived floor',
        'location' => 'room:14',
    ]),
    422,
    'A space under an archived floor must not be selectable.'
);
$locatedDocuments->updateIssue(1, $spaceIssueId, [
    'description' => 'Crack reported in room 101',
    'severity' => 'high',
    'location' => 'area:13',
]);
$db->exec('DELETE FROM project_foundation_archive_records');
$locatedDocuments->updateIssue(1, $spaceIssueId, [
    'description' => 'Crack reported in room 101',
    'severity' => 'high',
    'location' => 'room:14',
]);
documentTestCheck(
    ($findListedIssue($locatedDocuments, 1, $spaceIssueId)['location_name'] ?? '') === 'Tower A Ground 101',
    'Restored spaces must resolve again for existing issues.'
);

// A referenced space is archived, never hard-deleted; unreferenced ones still delete.
documentTestCheck(
    $spaceCatalog->deleteOrArchive(1, 'room', 14, 1) === 'archived',
    'A space referenced by an issue must be archived instead of hard-deleted.'
);
documentTestCheck(
    (int) $db->query('SELECT COUNT(*) FROM Rooms WHERE room_id = 14')->fetchColumn() === 1,
    'Archiving a referenced space must keep the space row and the issue reference.'
);
$db->exec("INSERT INTO Rooms (room_id, building_id, floor_id, room_number) VALUES (16, 10, 12, '202')");
documentTestCheck(
    $spaceCatalog->deleteOrArchive(1, 'room', 16, 1) === 'deleted',
    'An unreferenced space must still be hard-deleted.'
);
$db->exec('DELETE FROM project_foundation_archive_records');

// --- Approach F: search catalogue, tree paths, and the detail note (2026-11-15) ---
$fCatalog = $spaceCatalog->locationCatalog(1);
$fRoom = null;
foreach ($fCatalog['options'] as $option) {
    if ($option['value'] === 'room:14') { $fRoom = $option; break; }
}
documentTestCheck(
    $fRoom !== null
        && $fRoom['name'] === '101'
        && $fRoom['path'] === ['Tower A', 'Ground', '101']
        && $fRoom['depth'] === 2
        && (int) $fRoom['building_id'] === 10
        && (int) $fRoom['floor_id'] === 11,
    'Each catalogue option must carry its own name, ancestor path and depth for search and tree rendering.'
);
$fBuilding = null;
$fArea = null;
foreach ($fCatalog['options'] as $option) {
    if ($option['value'] === 'building:10') { $fBuilding = $option; }
    if ($option['value'] === 'area:13') { $fArea = $option; }
}
documentTestCheck(
    $fBuilding !== null && $fBuilding['path'] === ['Tower A'] && $fBuilding['depth'] === 0
        && $fArea !== null && $fArea['path'] === ['Tower A', 'Lobby'] && $fArea['depth'] === 1
        && $fArea['floor_id'] === null,
    'Areas must stay building-level: no floor parent is invented for the tree.'
);

// The detail note is stored alongside the space and shown together with it.
$noteIssueId = $locatedDocuments->createIssue(1, 1, [
    'description' => 'Crack along the grid axis',
    'severity' => 'medium',
    'location' => 'floor:11',
    'location_note' => 'C-E轴靠窗一侧',
]);
$noteRow = $db->query(
    'SELECT location_type, location_floor_id, location_note FROM document_issues WHERE issue_id = ' . $noteIssueId
)->fetch();
documentTestCheck(
    $noteRow['location_type'] === 'floor' && (int) $noteRow['location_floor_id'] === 11
        && $noteRow['location_note'] === 'C-E轴靠窗一侧',
    'A space selection and its detail note must be stored together.'
);
$noteListed = $findListedIssue($locatedDocuments, 1, $noteIssueId);
documentTestCheck(
    $noteListed !== null
        && $noteListed['location_name'] === 'Tower A Ground'
        && $noteListed['location_display'] === 'Tower A Ground · C-E轴靠窗一侧',
    'Lists must show the space name plus the detail note.'
);
// A note alone is allowed: some locations have no matching space record at all.
$noteOnlyId = $locatedDocuments->createIssue(1, 1, [
    'description' => 'Lobby issue without a space record',
    'location' => '',
    'location_note' => '客梯前室',
]);
$noteOnlyListed = $findListedIssue($locatedDocuments, 1, $noteOnlyId);
documentTestCheck(
    $noteOnlyListed !== null && $noteOnlyListed['location_name'] === ''
        && $noteOnlyListed['location_display'] === '客梯前室',
    'A detail note without a space must still be readable.'
);
// Editing the note must not disturb the selected space.
$locatedDocuments->updateIssue(1, $noteIssueId, [
    'description' => 'Crack along the grid axis',
    'severity' => 'high',
    'location' => 'floor:11',
    'location_note' => 'C-E轴，已复核',
]);
$noteEdited = $db->query(
    'SELECT location_floor_id, location_note FROM document_issues WHERE issue_id = ' . $noteIssueId
)->fetch();
documentTestCheck(
    (int) $noteEdited['location_floor_id'] === 11 && $noteEdited['location_note'] === 'C-E轴，已复核',
    'Editing the note must keep the selected space intact.'
);
// Clearing the note keeps the space.
$locatedDocuments->updateIssue(1, $noteIssueId, [
    'description' => 'Crack along the grid axis',
    'severity' => 'high',
    'location' => 'floor:11',
    'location_note' => '',
]);
documentTestCheck(
    ($findListedIssue($locatedDocuments, 1, $noteIssueId)['location_display'] ?? 'x') === 'Tower A Ground',
    'Clearing the note must leave the space name.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Overlong note',
        'location' => 'floor:11',
        'location_note' => str_repeat('轴', 256),
    ]),
    422,
    'An overlong detail note must be rejected.'
);
documentTestExpectStatus(
    static fn () => $locatedDocuments->createIssue(1, 1, [
        'description' => 'Array note',
        'location_note' => ['C-E'],
    ]),
    422,
    'A non-string detail note must be rejected.'
);
// Permission is checked before the note is validated, like the space itself.
$noteDenied = false;
$db->exec('DELETE FROM Role_Permissions WHERE permission_id = 2');
try {
    documentTestExpectStatus(
        static fn () => $locationRegistry->dispatch('POST', 'issue.create', [
            'description' => 'Unauthorized note probe',
            'location' => 'room:24',
            'location_note' => 'x',
        ]),
        403,
        'The create permission must be checked before location validation.'
    );
    $noteDenied = true;
} finally {
    $db->exec('INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, 2)');
}
documentTestCheck($noteDenied, 'Note handling must not run before authorization.');

// The page ships the picker container, catalogue payload and its own assets.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$fPage = (string) ob_get_clean();
documentTestCheck(
    str_contains($fPage, 'data-location-picker')
        && str_contains($fPage, 'data-location-catalog')
        && str_contains($fPage, 'data-project-id="1"')
        && str_contains($fPage, 'name="location_note"'),
    'The issue page must render the picker container, its catalogue and the note field.'
);
documentTestCheck(
    str_contains($fPage, 'page=module-asset&amp;module=document.management&amp;file=issue-location.css')
        && str_contains($fPage, 'page=module-asset&amp;module=document.management&amp;file=issue-location.js'),
    'The issue page must load its own plugin assets through the asset route.'
);
documentTestCheck(
    !str_contains($fPage, 'modules/document-management/public/'),
    'Plugin assets must not be referenced as raw filesystem paths.'
);
// The JSON payload must not be able to break out of its script element.
documentTestCheck(
    str_contains($fPage, 'Tower A') && str_contains($fPage, '"depth":2'),
    'The catalogue payload must include ancestor paths for the enhancer.'
);

// The asset route serves declared files and refuses everything else.
$serveAsset = static function (array $query) use ($db): array {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = $query;
    ob_start();
    (new Application($db))->run();
    return ['body' => (string) ob_get_clean(), 'status' => http_response_code()];
};
$cssOut = $serveAsset(['page' => 'module-asset', 'module' => 'document.management', 'file' => 'issue-location.css']);
documentTestCheck(
    str_contains($cssOut['body'], '.loc-picker') && str_contains($cssOut['body'], '.loc-tree'),
    'The asset route must serve the declared stylesheet.'
);
$jsOut = $serveAsset(['page' => 'module-asset', 'module' => 'document.management', 'file' => 'issue-location.js']);
documentTestCheck(
    str_contains($jsOut['body'], 'data-location-picker'),
    'The asset route must serve the declared script.'
);
// The host renders errors instead of throwing, so assert on the response.
foreach ([
    ['page' => 'module-asset', 'module' => 'document.management', 'file' => '../../src/DocumentService.php'],
    ['page' => 'module-asset', 'module' => 'document.management', 'file' => 'plugin.json'],
    ['page' => 'module-asset', 'module' => '../../etc', 'file' => 'passwd'],
    ['page' => 'module-asset', 'module' => 'unknown.module', 'file' => 'issue-location.css'],
    ['page' => 'module-asset', 'module' => ['document.management'], 'file' => 'issue-location.css'],
] as $badAsset) {
    $refused = $serveAsset($badAsset);
    documentTestCheck(
        !str_contains($refused['body'], '.loc-picker')
            && !str_contains($refused['body'], 'declare(strict_types')
            && !str_contains($refused['body'], 'data-location-picker')
            && !str_contains($refused['body'], 'loc-picker'),
        'Undeclared or traversing asset requests must be refused without leaking file content.'
    );
}
// Disabled plugins must not serve their assets.
$db->exec("INSERT INTO Plugin_States (plugin_id, is_enabled) VALUES ('document.management', 0)");
$disabledAsset = $serveAsset(['page' => 'module-asset', 'module' => 'document.management', 'file' => 'issue-location.css']);
documentTestCheck(
    !str_contains($disabledAsset['body'], '.loc-picker'),
    'A disabled plugin must not serve assets.'
);
$db->exec("DELETE FROM Plugin_States WHERE plugin_id = 'document.management'");
// The registry itself must refuse undeclared paths outright.
$assetRegistry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
foreach ([
    ['document.management', '../../src/DocumentService.php'],
    ['document.management', 'plugin.json'],
    ['../../etc', 'passwd'],
    ['unknown.module', 'issue-location.css'],
] as [$badModule, $badFile]) {
    documentTestCheck(
        $assetRegistry->assetPath($badModule, $badFile) === null,
        'The registry must not resolve undeclared or traversing asset paths.'
    );
}
documentTestCheck(
    $assetRegistry->assetPath('document.management', 'issue-location.css') !== null
        && $assetRegistry->assetVersion('document.management', 'issue-location.css') !== '0',
    'Declared assets must resolve and carry a content fingerprint.'
);

// Export carries the combined location text.
$fExport = $locatedPlugin->dispatch('issue.export', []);
$fSheet = documentTestXlsxPart($fExport, 'xl/worksheets/sheet1.xml');
documentTestCheck(
    str_contains($fSheet, '客梯前室'),
    'The export must include detail notes in the location column.'
);

echo "Document management tests passed.\n";
