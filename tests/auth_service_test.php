<?php
declare(strict_types=1);

use ProjectO\AuthService;

require dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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
$insert = $db->prepare(
    'INSERT INTO Users (user_id, username, password_hash, is_active) VALUES (?, ?, ?, ?)'
);
$insert->execute([1, 'admin', password_hash('correct-horse-battery', PASSWORD_DEFAULT), 1]);
$insert->execute([2, 'disabled', password_hash('correct-horse-battery', PASSWORD_DEFAULT), 0]);
$_SESSION = [];
$_SERVER = [];

$auth = new AuthService($db);
if ($auth->login('admin', 'wrong-password')) {
    throw new RuntimeException('An incorrect password must not authenticate.');
}
if ($auth->login('disabled', 'correct-horse-battery')) {
    throw new RuntimeException('An inactive account must not authenticate.');
}
if (!$auth->login('admin', 'correct-horse-battery')) {
    throw new RuntimeException('A valid active account should authenticate.');
}
if (($_SESSION['username'] ?? null) !== 'admin') {
    throw new RuntimeException('Successful authentication should establish the user session.');
}

$auth->logout();
if (session_status() !== PHP_SESSION_NONE) {
    throw new RuntimeException('Logout should destroy the session.');
}
if ((int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() !== 2) {
    throw new RuntimeException('Successful login and logout should both be audited.');
}

echo "Authentication service tests passed.\n";
