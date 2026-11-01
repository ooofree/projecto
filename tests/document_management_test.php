<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Application;
use ProjectO\Modules\DocumentManagement\DocumentManagementPlugin;
use ProjectO\Modules\DocumentManagement\DocumentService;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginRegistry;

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
    "CREATE TABLE Users (
        user_id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1
    )"
);
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY, permission_name TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
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
        (4, 'manage_issues'), (5, 'view_projects')"
);
$db->exec(
    'INSERT INTO Role_Permissions (role_id, permission_id)
     VALUES (1, 1), (1, 2), (1, 3), (1, 4), (1, 5)'
);

$db->exec(
    "CREATE TABLE document_issues (
        issue_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        description TEXT NOT NULL,
        severity TEXT NOT NULL DEFAULT 'medium',
        status TEXT NOT NULL DEFAULT 'open',
        location TEXT,
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
    "CREATE TABLE document_rfi_number_sequences (
        calendar_year INTEGER PRIMARY KEY,
        last_number INTEGER NOT NULL DEFAULT 0
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
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'open',
    'New issues should begin in open status.'
);

$issueFollowUpId = $documents->addIssueFollowUp(1, 1, $issueId, 'Verify repair on site');
$documents->transitionIssue(
    1,
    1,
    $issueId,
    'in_review',
    'Assigned for investigation',
    ['evidence_reference' => 'ARCH-INVESTIGATION-01'],
    false
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
    (string) $db->query('SELECT rfi_number FROM document_rfis WHERE rfi_id = ' . $rfiId)->fetchColumn()
        === 'RFI-' . date('Y') . '-001'
        && (string) $db->query('SELECT rfi_number FROM document_rfis WHERE rfi_id = ' . $secondRfiId)->fetchColumn()
        === 'RFI-' . date('Y') . '-002',
    'RFI numbers should be allocated sequentially per calendar year.'
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
$documents->transitionRfi(1, 1, $rfiId, 'under_review', 'Recipient started review', [], false);
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
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'open',
    'Responding to an RFI must not automatically change the issue status.'
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
    (string) $db->query('SELECT status FROM document_issues WHERE issue_id = ' . $issueId)->fetchColumn() === 'open',
    'Closing an RFI must leave its primary issue state independent.'
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
        && str_contains($renderedRfiPage, '项目：Project One'),
    'The RFI route should render project-scoped records through the host application.'
);
$_GET = ['page' => 'issues'];
ob_start();
(new Application($db))->run();
$renderedIssuesPage = ob_get_clean();
documentTestCheck(
    str_contains($renderedIssuesPage, '问题管理')
        && str_contains($renderedIssuesPage, '登记问题')
        && str_contains($renderedIssuesPage, 'Dimension conflict at north facade'),
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
documentTestCheck(
    str_contains($renderedProjectsPage, '?page=documents'),
    'The project page should expose the authorized document-management entry.'
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

echo "Document management tests passed.\n";
