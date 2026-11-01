<?php
declare(strict_types=1);

use ProjectO\Database;
use ProjectO\Environment;
use ProjectO\Plugin\PluginRegistry;

require dirname(__DIR__) . '/src/Environment.php';
Environment::load(dirname(__DIR__) . '/.env');
require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this installer from the command line.\n");
    exit(1);
}

try {
    $db = Database::connect();
    $migrationTable = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'ProjectO_Migrations'"
    )->fetchColumn();

    if ((int) $migrationTable === 0) {
        $existingTables = (int) $db->query(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
        )->fetchColumn();
        if ($existingTables > 0) {
            throw new RuntimeException(
                'Refusing to install into a non-empty database. Create a dedicated empty database for Project O.'
            );
        }
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `ProjectO_Migrations` (
            `migration` varchar(255) NOT NULL,
            `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationDirectory = dirname(__DIR__) . '/database/migrations';
    $migrationFiles = glob($migrationDirectory . '/*.sql');
    sort($migrationFiles, SORT_STRING);

    foreach ($migrationFiles as $migrationFile) {
        $migration = basename($migrationFile);
        $check = $db->prepare('SELECT 1 FROM `ProjectO_Migrations` WHERE `migration` = :migration');
        $check->execute(['migration' => $migration]);
        if ($check->fetchColumn() !== false) {
            continue;
        }

        $contents = file_get_contents($migrationFile);
        if ($contents === false) {
            throw new RuntimeException('Unable to read migration: ' . $migration);
        }

        foreach (explode(';', $contents) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $db->exec($statement);
            }
        }

        $mark = $db->prepare('INSERT INTO `ProjectO_Migrations` (`migration`) VALUES (:migration)');
        $mark->execute(['migration' => $migration]);
        fwrite(STDOUT, "Applied migration: {$migration}\n");
    }

    $plugins = new PluginRegistry($db, dirname(__DIR__) . '/modules');
    foreach ($plugins->migrations() as $migration => $migrationFile) {
        $check = $db->prepare('SELECT 1 FROM `ProjectO_Migrations` WHERE `migration` = :migration');
        $check->execute(['migration' => $migration]);
        if ($check->fetchColumn() !== false) {
            continue;
        }

        $contents = file_get_contents($migrationFile);
        if ($contents === false) {
            throw new RuntimeException('Unable to read plugin migration: ' . $migration);
        }

        foreach (explode(';', $contents) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $db->exec($statement);
            }
        }

        $mark = $db->prepare('INSERT INTO `ProjectO_Migrations` (`migration`) VALUES (:migration)');
        $mark->execute(['migration' => $migration]);
        fwrite(STDOUT, "Applied plugin migration: {$migration}\n");
    }

    $db->beginTransaction();
    $db->exec(
        "INSERT INTO `Roles` (`role_name`, `description`)
         VALUES ('administrator', 'Project O platform administrator')
         ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)"
    );
    $roleId = (int) $db->query(
        "SELECT `role_id` FROM `Roles` WHERE `role_name` = 'administrator'"
    )->fetchColumn();

    $permissionInsert = $db->prepare(
        "INSERT INTO `Permissions` (`permission_name`, `display_name`, `description`, `module`)
         VALUES (:name, :display_name, :description, :module)
         ON DUPLICATE KEY UPDATE
            `display_name` = VALUES(`display_name`),
            `description` = VALUES(`description`),
            `module` = VALUES(`module`)"
    );
    $rolePermissionInsert = $db->prepare(
        'INSERT IGNORE INTO `Role_Permissions` (`role_id`, `permission_id`)
         VALUES (:role_id, :permission_id)'
    );

    $permissions = $plugins->permissions();
    $permissions[] = [
        'name' => 'manage_platform',
        'display_name' => '管理平台',
        'description' => '管理用户、角色权限、插件和系统设置',
        'module' => '平台管理',
    ];
    foreach ($permissions as $permission) {
        $permissionInsert->execute([
            'name' => $permission['name'],
            'display_name' => $permission['display_name'],
            'description' => $permission['description'] ?? null,
            'module' => $permission['module'],
        ]);
        $permissionIdQuery = $db->prepare(
            'SELECT `permission_id` FROM `Permissions` WHERE `permission_name` = :name'
        );
        $permissionIdQuery->execute(['name' => $permission['name']]);
        $rolePermissionInsert->execute([
            'role_id' => $roleId,
            'permission_id' => (int) $permissionIdQuery->fetchColumn(),
        ]);
    }
    $db->commit();

    $userCount = (int) $db->query('SELECT COUNT(*) FROM `Users`')->fetchColumn();
    if ($userCount > 0) {
        fwrite(STDOUT, "Schema and permissions are ready. Existing users were not changed.\n");
        exit(0);
    }

    $username = trim((string) (getenv('PROJECT_O_ADMIN_USERNAME') ?: prompt('Administrator username: ')));
    $countryCode = strtoupper(trim((string) (
        getenv('PROJECT_O_ADMIN_COUNTRY_CODE') ?: prompt('ISO 3166-1 alpha-2 country code: ')
    )));
    $password = getenv('PROJECT_O_ADMIN_PASSWORD');
    if ($password === false) {
        $password = promptSecret('Administrator password (minimum 12 characters): ');
        $confirmation = promptSecret('Confirm administrator password: ');
        if (!hash_equals($password, $confirmation)) {
            throw new RuntimeException('Administrator passwords do not match.');
        }
    }

    if ($username === '' || mb_strlen($username) > 255) {
        throw new RuntimeException('Username must contain between 1 and 255 characters.');
    }
    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        throw new RuntimeException('Country code must be a two-letter ISO country code.');
    }
    if (strlen($password) < 12) {
        throw new RuntimeException('Administrator password must contain at least 12 characters.');
    }

    $db->beginTransaction();
    $createUser = $db->prepare(
        'INSERT INTO `Users` (`username`, `password_hash`, `country_code`, `is_active`)
         VALUES (:username, :password_hash, :country_code, 1)'
    );
    $createUser->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'country_code' => $countryCode,
    ]);
    $userId = (int) $db->lastInsertId();

    $assignRole = $db->prepare(
        'INSERT INTO `User_Roles` (`user_id`, `role_id`) VALUES (:user_id, :role_id)'
    );
    $assignRole->execute(['user_id' => $userId, 'role_id' => $roleId]);
    $db->commit();

    fwrite(STDOUT, "Administrator created. Start the web server and sign in.\n");
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Installation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

function prompt(string $message): string
{
    fwrite(STDOUT, $message);
    return rtrim((string) fgets(STDIN), "\r\n");
}

function promptSecret(string $message): string
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        throw new RuntimeException(
            'Set PROJECT_O_ADMIN_PASSWORD in the environment when running the installer non-interactively.'
        );
    }

    fwrite(STDOUT, $message);
    shell_exec('stty -echo');
    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return $password;
}
