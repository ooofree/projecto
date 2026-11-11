<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;

final class Support
{
    private static ?\DateTimeZone $displayTimezone = null;

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

    /**
     * Sets the timezone used to display UTC timestamps and to interpret
     * user-entered local date-times for the current request.
     */
    public static function setDisplayTimezone(string $timezone): void
    {
        try {
            self::$displayTimezone = new \DateTimeZone($timezone);
        } catch (\Throwable) {
            self::$displayTimezone = new \DateTimeZone('UTC');
        }
    }

    public static function displayTimezone(): \DateTimeZone
    {
        return self::$displayTimezone ??= new \DateTimeZone('UTC');
    }

    /** @return array<string, string> */
    public static function timezoneOptions(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();

        return array_combine($identifiers, $identifiers);
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Effective display timezone: the signed-in user's own timezone when set,
     * otherwise the system default, otherwise Asia/Shanghai.
     */
    public static function resolveTimezone(PDO $db): string
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                $statement = $db->prepare('SELECT timezone FROM Users WHERE user_id = :user_id');
                $statement->execute(['user_id' => $userId]);
                $timezone = $statement->fetchColumn();
                if (is_string($timezone) && self::isValidTimezone($timezone)) {
                    return $timezone;
                }
            } catch (\Throwable $exception) {
                error_log('Unable to read the user timezone: ' . $exception->getMessage());
            }
        }

        $statement = $db->prepare("SELECT setting_value FROM System_Settings WHERE setting_key = 'timezone'");
        $statement->execute();
        $timezone = $statement->fetchColumn();
        if (is_string($timezone) && self::isValidTimezone($timezone)) {
            return $timezone;
        }

        return 'Asia/Shanghai';
    }

    /**
     * Formats a UTC timestamp in the effective display timezone.
     */
    public static function formatDateTime(?string $value, string $format = 'Y-m-d H:i'): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));

        return $date->setTimezone(self::displayTimezone())->format($format);
    }

    /**
     * Increments a per-day sequence stored in System_Settings (used by export
     * file names) and returns the new value.
     */
    public static function nextDailySequence(PDO $db, string $prefix, ?string $day = null): int
    {
        $day = $day ?? self::nowLocal('Ymd');
        $key = $prefix . '.' . $day;
        $db->beginTransaction();
        try {
            $select = $db->prepare('SELECT setting_value FROM System_Settings WHERE setting_key = :setting_key');
            $select->execute(['setting_key' => $key]);
            $current = (int) $select->fetchColumn();
            $next = $current + 1;
            if ($current === 0) {
                $write = $db->prepare(
                    'INSERT INTO System_Settings (setting_key, setting_value, updated_by)
                     VALUES (:setting_key, :setting_value, :updated_by)'
                );
            } else {
                $write = $db->prepare(
                    'UPDATE System_Settings SET setting_value = :setting_value, updated_by = :updated_by
                     WHERE setting_key = :setting_key'
                );
            }
            $write->execute([
                'setting_key' => $key,
                'setting_value' => (string) $next,
                'updated_by' => (int) ($_SESSION['user_id'] ?? 0),
            ]);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $next;
    }

    public static function nowLocal(string $format = 'Y-m-d H:i:s'): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(self::displayTimezone())
            ->format($format);
    }

    /**
     * Sanitises a label for use inside a download file name: replaces characters
     * that are invalid on disk or in the Content-Disposition header, removes
     * control characters, collapses whitespace, trims edge dots/underscores and
     * caps the length. Returns an empty string when nothing usable remains.
     */
    public static function safeFileSegment(string $value, int $maxLength = 60): string
    {
        $clean = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', ';'], '_', $value);
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', '', $clean);
        $clean = is_string($clean) ? trim(preg_replace('/\s+/u', ' ', $clean) ?? '') : '';
        $clean = trim($clean, " ._");

        return $clean === '' ? '' : mb_substr($clean, 0, $maxLength, 'UTF-8');
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
