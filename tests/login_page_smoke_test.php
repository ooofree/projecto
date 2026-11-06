<?php
declare(strict_types=1);

use ProjectO\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:');
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
$_SESSION = [];
$_GET = ['page' => 'login'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
(new Application($db))->run();
$html = ob_get_clean();

if (!str_contains($html, '<h1>登录</h1>') || !str_contains($html, 'name="csrf_token"')) {
    throw new RuntimeException('Login page did not render the expected form and CSRF token.');
}

echo "Login page smoke test passed.\n";
