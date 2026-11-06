<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;

/**
 * Personal account settings for a signed-in user.
 */
final class ProfileService
{
    private const MINIMUM_PASSWORD_LENGTH = 12;

    public function __construct(private readonly PDO $db)
    {
    }

    public function saveTimezone(int $userId, string $timezone): void
    {
        $timezone = trim($timezone);
        if ($timezone !== '' && !Support::isValidTimezone($timezone)) {
            throw new HttpException('时区选项无效。', 422);
        }
        $statement = $this->db->prepare(
            'UPDATE Users SET timezone = :timezone WHERE user_id = :user_id'
        );
        $statement->execute([
            'timezone' => $timezone === '' ? null : $timezone,
            'user_id' => $userId,
        ]);
    }

    /**
     * Changes the signed-in user's own password after verifying the current one.
     * Password values are never written to the audit log.
     */
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): void {
        if ($userId < 1) {
            throw new HttpException('登录状态无效，请重新登录。', 401);
        }
        if ($currentPassword === '') {
            throw new HttpException('请输入当前密码。', 422);
        }
        if (strlen($newPassword) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new HttpException('新密码至少需要 12 个字符。', 422);
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new HttpException('两次输入的新密码不一致。', 422);
        }

        $statement = $this->db->prepare(
            'SELECT password_hash FROM Users WHERE user_id = :user_id AND is_active = 1'
        );
        $statement->execute(['user_id' => $userId]);
        $storedHash = $statement->fetchColumn();
        if (!is_string($storedHash) || $storedHash === '') {
            throw new HttpException('账号不存在或已停用，请重新登录。', 401);
        }
        if (!password_verify($currentPassword, $storedHash)) {
            throw new HttpException('当前密码不正确。', 422);
        }
        if (password_verify($newPassword, $storedHash)) {
            throw new HttpException('新密码不能与当前密码相同。', 422);
        }

        $update = $this->db->prepare(
            'UPDATE Users SET password_hash = :password_hash WHERE user_id = :user_id'
        );
        $update->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'user_id' => $userId,
        ]);
        Support::audit($this->db, 'profile.password.update', 'Changed own password.');
    }
}
