<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;

final class Support
{
    public static function escape(string|int|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function hasPermission(PDO $db, string $permission): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $stmt = $db->prepare(
            'SELECT 1
             FROM User_Roles ur
             JOIN Role_Permissions rp ON rp.role_id = ur.role_id
             JOIN Permissions p ON p.permission_id = rp.permission_id
             JOIN Users u ON u.user_id = ur.user_id
             WHERE ur.user_id = :user_id
               AND p.permission_name = :permission
               AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => (int) $_SESSION['user_id'],
            'permission' => $permission,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public static function requirePermission(PDO $db, string $permission): void
    {
        if (!self::hasPermission($db, $permission)) {
            throw new HttpException('您没有权限执行此操作。', 403);
        }
    }

    public static function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?page=login');
            exit;
        }
    }

    public static function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    public static function audit(PDO $db, string $action, string $details): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent)
             VALUES (:user_id, :action, :details, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id' => (int) $_SESSION['user_id'],
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 1000)
                : null,
        ]);
    }
}
