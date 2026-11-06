<?php
declare(strict_types=1);

namespace ProjectO\Storage;

use PDO;
use ProjectO\HttpException;
use Throwable;

/**
 * Persists the system-wide image object-storage configuration in the platform
 * System_Settings table, mirroring the legacy document-management settings
 * page: values live in the database, secret keys are write-only (a blank
 * submit keeps the stored secret), and only authorized users may change them.
 *
 * One configuration serves every module that stores images (document issues,
 * RFIs and material samples).
 */
final class CloudStorageSettings
{
    public const SETTING_KEYS = [
        'enabled',
        'r2_account_id',
        'bucket_name',
        'access_key_id',
        'secret_access_key',
    ];
    private const PREFIX = 'cloud_storage.';
    private const MAX_VALUE_LENGTH = 255;

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Loads the stored configuration. `exists` reports whether the database
     * holds an explicit configuration; absence lets the storage adapter fall
     * back to its environment variables.
     *
     * The secret is returned so the save handler can keep it when the form
     * leaves the field blank; it must never reach a view or a log.
     *
     * @return array{exists: bool, enabled: bool, account_id: string, bucket: string, access_key_id: string, secret_access_key: string}
     */
    public function read(): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::SETTING_KEYS), '?'));
        $statement = $this->db->prepare(
            'SELECT setting_key, setting_value FROM System_Settings WHERE setting_key IN (' . $placeholders . ')'
        );
        $statement->execute(array_map(static fn (string $key): string => self::PREFIX . $key, self::SETTING_KEYS));
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            $values[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        $exists = array_key_exists(self::PREFIX . 'enabled', $values);
        return [
            'exists' => $exists,
            'enabled' => $exists && $values[self::PREFIX . 'enabled'] === '1',
            'account_id' => $values[self::PREFIX . 'r2_account_id'] ?? '',
            'bucket' => $values[self::PREFIX . 'bucket_name'] ?? '',
            'access_key_id' => $values[self::PREFIX . 'access_key_id'] ?? '',
            'secret_access_key' => $values[self::PREFIX . 'secret_access_key'] ?? '',
        ];
    }

    /**
     * Validates and stores the configuration submitted from the document
     * module's system settings page.
     *
     * @param array<string, mixed> $input
     */
    public function save(array $input): void
    {
        $enabled = array_key_exists('enabled', $input)
            && is_string($input['enabled'])
            && $input['enabled'] === '1';
        $accountId = $this->trimmed($input, 'account_id');
        $bucket = $this->trimmed($input, 'bucket');
        $accessKeyId = $this->trimmed($input, 'access_key_id');
        $secret = $this->raw($input, 'secret_access_key');

        if ($accountId !== '' && preg_match('/^[a-f0-9]{32}$/i', $accountId) !== 1) {
            throw new HttpException('R2 Account ID 必须是 32 位十六进制字符。', 422);
        }
        if ($bucket !== '' && preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $bucket) !== 1) {
            throw new HttpException('存储桶名称只能包含小写字母、数字和连字符，长度为 3 到 63 个字符。', 422);
        }
        if (
            mb_strlen($accountId) > self::MAX_VALUE_LENGTH
            || mb_strlen($bucket) > self::MAX_VALUE_LENGTH
            || mb_strlen($accessKeyId) > self::MAX_VALUE_LENGTH
        ) {
            throw new HttpException('存储配置字段超出长度限制。', 422);
        }
        if (strlen($secret) > self::MAX_VALUE_LENGTH) {
            throw new HttpException('Secret Access Key 超出长度限制。', 422);
        }

        $current = $this->read();
        if ($secret === '') {
            $secret = $current['secret_access_key'];
        }
        if ($enabled) {
            if ($accountId === '' || $bucket === '' || $accessKeyId === '') {
                throw new HttpException('启用图片存储时，Account ID、存储桶名称和 Access Key ID 均为必填。', 422);
            }
            if ($secret === '') {
                throw new HttpException('启用图片存储时必须填写 Secret Access Key；留空表示保留已保存的密钥。', 422);
            }
        }

        $values = [
            'enabled' => $enabled ? '1' : '0',
            'r2_account_id' => $accountId,
            'bucket_name' => $bucket,
            'access_key_id' => $accessKeyId,
            'secret_access_key' => $secret,
        ];

        $this->db->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $this->upsert(self::PREFIX . $key, $value);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function upsert(string $key, string $value): void
    {
        $select = $this->db->prepare('SELECT 1 FROM System_Settings WHERE setting_key = :setting_key');
        $select->execute(['setting_key' => $key]);
        if ($select->fetchColumn() === false) {
            $write = $this->db->prepare(
                'INSERT INTO System_Settings (setting_key, setting_value, updated_by)
                 VALUES (:setting_key, :setting_value, :updated_by)'
            );
        } else {
            $write = $this->db->prepare(
                'UPDATE System_Settings
                 SET setting_value = :setting_value, updated_by = :updated_by
                 WHERE setting_key = :setting_key'
            );
        }
        $write->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_by' => (int) ($_SESSION['user_id'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function trimmed(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value)) {
            throw new HttpException('系统设置表单字段格式无效。', 422);
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function raw(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value)) {
            throw new HttpException('系统设置表单字段格式无效。', 422);
        }

        return $value;
    }
}
