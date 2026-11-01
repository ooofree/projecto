<?php
declare(strict_types=1);

use ProjectO\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:');
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
