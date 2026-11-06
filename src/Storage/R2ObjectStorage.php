<?php
declare(strict_types=1);

namespace ProjectO\Storage;

use Aws\S3\S3Client;
use PDO;
use ProjectO\HttpException;
use RuntimeException;
use Throwable;

/**
 * Cloudflare R2 adapter for the platform's private image object storage
 * (document issues, RFIs and material samples).
 *
 * Configuration precedence: the database configuration maintained on the
 * document module's system settings page (CloudStorageSettings) wins when
 * present; otherwise the APP_R2_* environment variables are used. The AWS SDK
 * is a project dependency but is still detected at runtime so a deployment
 * without it reports the storage as unavailable instead of failing requests.
 */
final class R2ObjectStorage implements ObjectStorageInterface
{
    private const SETTING_PREFIX = 'cloud_storage.';

    private ?S3Client $client = null;

    /** @var array{source: string, enabled: bool, account_id: string, bucket: string, access_key_id: string, secret_access_key: string}|null */
    private ?array $configuration = null;

    public function __construct(private readonly ?PDO $db = null)
    {
    }

    public function isConfigured(): bool
    {
        return class_exists(S3Client::class) && $this->credentialsComplete($this->configuration());
    }

    /**
     * Describes the active configuration for the settings page. Never includes
     * the secret access key.
     *
     * @return array{source: string, enabled: bool, sdk_available: bool, configured: bool}
     */
    public function configurationStatus(): array
    {
        $configuration = $this->configuration();
        $sdkAvailable = class_exists(S3Client::class);

        return [
            'source' => $configuration['source'],
            'enabled' => $configuration['enabled'],
            'sdk_available' => $sdkAvailable,
            'configured' => $sdkAvailable && $this->credentialsComplete($configuration),
        ];
    }

    public function upload(string $objectKey, string $filePath, string $contentType): void
    {
        $body = fopen($filePath, 'rb');
        if ($body === false) {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        try {
            $this->client()->putObject([
                'Bucket' => $this->configuration()['bucket'],
                'Key' => $objectKey,
                'Body' => $body,
                'ContentType' => $contentType,
            ]);
        } finally {
            fclose($body);
        }
    }

    public function signedReadUrl(string $objectKey, string $contentType): string
    {
        $command = $this->client()->getCommand('GetObject', [
            'Bucket' => $this->configuration()['bucket'],
            'Key' => $objectKey,
            'ResponseContentType' => $contentType,
            'ResponseContentDisposition' => 'inline',
        ]);
        return (string) $this->client()->createPresignedRequest($command, '+5 minutes')->getUri();
    }

    public function delete(string $objectKey): void
    {
        $this->client()->deleteObject([
            'Bucket' => $this->configuration()['bucket'],
            'Key' => $objectKey,
        ]);
    }

    private function client(): S3Client
    {
        if (!$this->isConfigured()) {
            throw new HttpException(
                '尚未配置图片存储：请在文件模块“系统设置”完成设置，或核对部署环境变量与 AWS SDK 安装。',
                503
            );
        }
        if ($this->client === null) {
            $configuration = $this->configuration();
            $this->client = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => 'https://' . $configuration['account_id'] . '.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $configuration['access_key_id'],
                    'secret' => $configuration['secret_access_key'],
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * @param array{source: string, enabled: bool, account_id: string, bucket: string, access_key_id: string, secret_access_key: string} $configuration
     */
    private function credentialsComplete(array $configuration): bool
    {
        return $configuration['enabled']
            && preg_match('/^[a-f0-9]{32}$/i', $configuration['account_id']) === 1
            && preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $configuration['bucket']) === 1
            && $configuration['access_key_id'] !== ''
            && $configuration['secret_access_key'] !== '';
    }

    /**
     * @return array{source: string, enabled: bool, account_id: string, bucket: string, access_key_id: string, secret_access_key: string}
     */
    private function configuration(): array
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        $databaseConfiguration = $this->databaseConfiguration();
        if ($databaseConfiguration !== null) {
            $this->configuration = $databaseConfiguration;
            return $this->configuration;
        }

        $accountId = trim((string) getenv('APP_R2_ACCOUNT_ID'));
        $bucket = trim((string) getenv('APP_R2_BUCKET'));
        $accessKeyId = trim((string) getenv('APP_R2_ACCESS_KEY_ID'));
        $secretKey = (string) getenv('APP_R2_SECRET_ACCESS_KEY');
        $anyValue = $accountId !== '' || $bucket !== '' || $accessKeyId !== '' || $secretKey !== '';
        $this->configuration = [
            'source' => $anyValue ? 'environment' : 'none',
            'enabled' => $anyValue,
            'account_id' => $accountId,
            'bucket' => $bucket,
            'access_key_id' => $accessKeyId,
            'secret_access_key' => $secretKey,
        ];
        return $this->configuration;
    }

    /**
     * @return array{source: string, enabled: bool, account_id: string, bucket: string, access_key_id: string, secret_access_key: string}|null
     */
    private function databaseConfiguration(): ?array
    {
        if ($this->db === null) {
            return null;
        }

        try {
            $statement = $this->db->prepare(
                'SELECT setting_key, setting_value FROM System_Settings WHERE setting_key LIKE :prefix'
            );
            $statement->execute(['prefix' => self::SETTING_PREFIX . '%']);
        } catch (Throwable $exception) {
            error_log('Unable to read cloud storage settings: ' . $exception->getMessage());
            return null;
        }

        $values = [];
        foreach ($statement->fetchAll() as $row) {
            $values[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        if (!array_key_exists(self::SETTING_PREFIX . 'enabled', $values)) {
            return null;
        }

        return [
            'source' => 'database',
            'enabled' => $values[self::SETTING_PREFIX . 'enabled'] === '1',
            'account_id' => trim($values[self::SETTING_PREFIX . 'r2_account_id'] ?? ''),
            'bucket' => trim($values[self::SETTING_PREFIX . 'bucket_name'] ?? ''),
            'access_key_id' => trim($values[self::SETTING_PREFIX . 'access_key_id'] ?? ''),
            'secret_access_key' => (string) ($values[self::SETTING_PREFIX . 'secret_access_key'] ?? ''),
        ];
    }
}
