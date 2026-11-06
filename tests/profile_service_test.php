<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\ProfileService;

require dirname(__DIR__) . '/vendor/autoload.php';

function profileServiceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function profileServiceExpectStatus(callable $operation, int $status, string $message): void
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
    'CREATE TABLE Users (
        user_id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        timezone TEXT,
        is_active INTEGER NOT NULL
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

$originalPassword = 'correct-horse-battery';
$newPassword = 'replacement-password-42';
$insert = $db->prepare(
    'INSERT INTO Users (user_id, username, password_hash, is_active) VALUES (?, ?, ?, ?)'
);
$insert->execute([1, 'alice', password_hash($originalPassword, PASSWORD_DEFAULT), 1]);
$insert->execute([2, 'inactive', password_hash($originalPassword, PASSWORD_DEFAULT), 0]);

$_SESSION = ['user_id' => 1, 'username' => 'alice'];
$_SERVER = [];

$service = new ProfileService($db);
$storedHash = (string) $db->query('SELECT password_hash FROM Users WHERE user_id = 1')->fetchColumn();

profileServiceExpectStatus(
    fn () => $service->changePassword(1, 'wrong-current-password', $newPassword, $newPassword),
    422,
    'An incorrect current password must be rejected.'
);
profileServiceCheck(
    (string) $db->query('SELECT password_hash FROM Users WHERE user_id = 1')->fetchColumn() === $storedHash,
    'A rejected current-password check must not change the stored hash.'
);

profileServiceExpectStatus(
    fn () => $service->changePassword(1, $originalPassword, 'short', 'short'),
    422,
    'A new password shorter than 12 characters must be rejected.'
);
profileServiceExpectStatus(
    fn () => $service->changePassword(1, $originalPassword, $newPassword, $newPassword . 'x'),
    422,
    'The new password confirmation must match.'
);
profileServiceExpectStatus(
    fn () => $service->changePassword(1, $originalPassword, $originalPassword, $originalPassword),
    422,
    'The new password must differ from the current password.'
);
profileServiceExpectStatus(
    fn () => $service->changePassword(2, $originalPassword, $newPassword, $newPassword),
    401,
    'An inactive account must not change its password.'
);

$service->changePassword(1, $originalPassword, $newPassword, $newPassword);
profileServiceCheck(
    password_verify(
        $newPassword,
        (string) $db->query('SELECT password_hash FROM Users WHERE user_id = 1')->fetchColumn()
    ),
    'A valid password change must store the new password hash.'
);
profileServiceCheck(
    !password_verify(
        $originalPassword,
        (string) $db->query('SELECT password_hash FROM Users WHERE user_id = 1')->fetchColumn()
    ),
    'The previous password must stop working after a password change.'
);

$auditRows = $db->query('SELECT action, details FROM audit_logs')->fetchAll();
profileServiceCheck(count($auditRows) === 1, 'A password change must be audited exactly once.');
profileServiceCheck(
    $auditRows[0]['action'] === 'profile.password.update',
    'The password change audit action must be stable.'
);
profileServiceCheck(
    !str_contains((string) $auditRows[0]['details'], $newPassword)
        && !str_contains((string) $auditRows[0]['details'], $originalPassword),
    'The password change audit must not contain either password.'
);

echo "Profile service tests passed.\n";
