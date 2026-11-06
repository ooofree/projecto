<?php
declare(strict_types=1);

use ProjectO\Application;
use ProjectO\Database;
use ProjectO\Environment;

require dirname(__DIR__) . '/src/Environment.php';
Environment::load(dirname(__DIR__) . '/.env');
require dirname(__DIR__) . '/vendor/autoload.php';
date_default_timezone_set('UTC');

$isProduction = getenv('APP_ENV') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting(E_ALL);

session_name('project_o_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

try {
    (new Application(Database::connect()))->run();
} catch (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(503);
    echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>Project O setup</title>';
    echo '<main><h1>Project O 尚未就绪</h1><p>请检查数据库配置、确认数据库已创建并运行安装命令。</p></main></html>';
}
