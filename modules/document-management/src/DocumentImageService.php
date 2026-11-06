<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use PDO;
use ProjectO\HttpException;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use Throwable;

/**
 * Private image attachments for document issues and RFIs.
 *
 * Images are stored through the platform ObjectStorageInterface (R2 by
 * default) and referenced here only by object key; the callers generate the
 * short-lived signed read URLs for authorized viewers.
 */
final class DocumentImageService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<int, list<array<string, mixed>>> keyed by issue id
     */
    public function issueImagesForProject(int $projectId): array
    {
        return $this->imagesForProject('document_issue_images', 'issue_id', $projectId);
    }

    /**
     * @return array<int, list<array<string, mixed>>> keyed by RFI id
     */
    public function rfiImagesForProject(int $projectId): array
    {
        return $this->imagesForProject('document_rfi_images', 'rfi_id', $projectId);
    }

    public function addIssueImage(
        int $projectId,
        int $issueId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $this->requireIssue($projectId, $issueId);

        return $this->addImage(
            'document_issue_images',
            'issue_id',
            'issues',
            $projectId,
            $issueId,
            $userId,
            $temporaryPath,
            $originalName,
            $storage
        );
    }

    public function addRfiImage(
        int $projectId,
        int $rfiId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $this->requireRfi($projectId, $rfiId);

        return $this->addImage(
            'document_rfi_images',
            'rfi_id',
            'rfis',
            $projectId,
            $rfiId,
            $userId,
            $temporaryPath,
            $originalName,
            $storage
        );
    }

    public function deleteIssueImage(
        int $projectId,
        int $issueId,
        int $imageId,
        ObjectStorageInterface $storage
    ): void {
        $image = $this->imageRow('document_issue_images', 'issue_id', $projectId, $issueId, $imageId);
        $storage->delete((string) $image['object_key']);
        $delete = $this->db->prepare(
            'DELETE FROM document_issue_images WHERE image_id = :image_id AND project_id = :project_id'
        );
        $delete->execute(['image_id' => $imageId, 'project_id' => $projectId]);
    }

    public function deleteRfiImage(
        int $projectId,
        int $rfiId,
        int $imageId,
        ObjectStorageInterface $storage
    ): void {
        $image = $this->imageRow('document_rfi_images', 'rfi_id', $projectId, $rfiId, $imageId);
        $storage->delete((string) $image['object_key']);
        $delete = $this->db->prepare(
            'DELETE FROM document_rfi_images WHERE image_id = :image_id AND project_id = :project_id'
        );
        $delete->execute(['image_id' => $imageId, 'project_id' => $projectId]);
    }

    public function requireIssue(int $projectId, int $issueId): void
    {
        $check = $this->db->prepare(
            'SELECT 1 FROM document_issues WHERE issue_id = :issue_id AND project_id = :project_id'
        );
        $check->execute(['issue_id' => $issueId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('问题不存在或不属于当前项目。', 404);
        }
    }

    public function requireRfi(int $projectId, int $rfiId): void
    {
        $check = $this->db->prepare(
            'SELECT 1 FROM document_rfis WHERE rfi_id = :rfi_id AND project_id = :project_id'
        );
        $check->execute(['rfi_id' => $rfiId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('RFI 不存在或不属于当前项目。', 404);
        }
    }

    private function addImage(
        string $table,
        string $ownerColumn,
        string $keyPrefix,
        int $projectId,
        int $ownerId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $image = ImageUpload::inspect($temporaryPath);
        $originalName = ImageUpload::originalName($originalName, $image['extension']);
        $objectKey = $keyPrefix . '/' . $projectId . '/' . $ownerId . '/'
            . bin2hex(random_bytes(16)) . '.' . $image['extension'];

        $storage->upload($objectKey, $temporaryPath, $image['mime']);
        try {
            $insert = $this->db->prepare(
                'INSERT INTO ' . $table . ' (' . $ownerColumn . ', project_id, object_key, original_name, content_type, file_size, uploaded_by)
                 VALUES (:owner_id, :project_id, :object_key, :original_name, :content_type, :file_size, :uploaded_by)'
            );
            $insert->execute([
                'owner_id' => $ownerId,
                'project_id' => $projectId,
                'object_key' => $objectKey,
                'original_name' => $originalName,
                'content_type' => $image['mime'],
                'file_size' => $image['size'],
                'uploaded_by' => $userId,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Throwable $exception) {
            try {
                $storage->delete($objectKey);
            } catch (Throwable $cleanupException) {
                error_log('Unable to remove an unreferenced document image: ' . get_class($cleanupException));
            }
            throw $exception;
        }
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function imagesForProject(string $table, string $ownerColumn, int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT image_id, ' . $ownerColumn . ' AS owner_id, object_key, original_name, content_type, file_size, created_at
             FROM ' . $table . ' WHERE project_id = :project_id ORDER BY image_id'
        );
        $statement->execute(['project_id' => $projectId]);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['owner_id']][] = $row;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageRow(
        string $table,
        string $ownerColumn,
        int $projectId,
        int $ownerId,
        int $imageId
    ): array {
        $statement = $this->db->prepare(
            'SELECT image_id, object_key FROM ' . $table . '
             WHERE image_id = :image_id AND project_id = :project_id AND ' . $ownerColumn . ' = :owner_id'
        );
        $statement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
            'owner_id' => $ownerId,
        ]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new HttpException('图片记录不存在。', 404);
        }

        return $row;
    }
}
