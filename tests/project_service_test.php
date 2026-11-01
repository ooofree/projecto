<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\ProjectContext;
use ProjectO\Modules\ProjectFoundation\ProjectService;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec(
    "CREATE TABLE Projects (
        project_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_name TEXT NOT NULL,
        start_date TEXT,
        end_date TEXT,
        status TEXT
    )"
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
$_SESSION = ['user_id' => 7];
$_SERVER = [];

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$projects = new ProjectService($db);
$id = $projects->create('North Tower', '2026-01-15', null, 'planning');
check($id === 1, 'The first project should have ID 1.');
check(count($projects->list()) === 1, 'The project list should contain the created project.');

$selected = $projects->select($id);
check($selected['project_name'] === 'North Tower', 'Selection should return the chosen project.');
check((int) $_SESSION['active_project_id'] === $id, 'Selection should set the active project context.');
$projectContext = new ProjectContext($db);
check((int) $projectContext->current()['project_id'] === $id, 'The current project should resolve from the session.');
check((int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 1, 'Project selection should be audited.');

foreach ([
    ['', null, null, 'planning'],
    ['Bad date', '2026-02-30', null, 'planning'],
    ['Bad status', null, null, 'archived'],
] as [$name, $startDate, $endDate, $status]) {
    try {
        $projects->create($name, $startDate, $endDate, $status);
        throw new RuntimeException('Expected invalid project input to be rejected.');
    } catch (HttpException $exception) {
        check($exception->statusCode === 422, 'Invalid project fields should return HTTP 422.');
    }
}

try {
    $projects->select(999);
    throw new RuntimeException('Expected unknown project to be rejected.');
} catch (HttpException $exception) {
    check($exception->statusCode === 404, 'Selecting an unknown project should return HTTP 404.');
}

$_SESSION['active_project_id'] = 'not-an-id';
check($projectContext->current() === null, 'Invalid session project IDs should be ignored.');
check(!isset($_SESSION['active_project_id']), 'Invalid session project IDs should be cleared.');
try {
    $projectContext->requireCurrent();
    throw new RuntimeException('An unset project context should be rejected.');
} catch (HttpException $exception) {
    check($exception->statusCode === 409, 'Missing project context should return HTTP 409.');
}

$_SESSION['csrf_token'] = 'expected-token';
check(
    \ProjectO\Support::verifyCsrfToken('expected-token'),
    'A matching CSRF token should be accepted.'
);
check(
    !\ProjectO\Support::verifyCsrfToken('wrong-token'),
    'A mismatched CSRF token should be rejected.'
);

echo "Project service tests passed.\n";
