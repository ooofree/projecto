<?php
declare(strict_types=1);

namespace ProjectO\Modules\CloseoutManagement;

use PDO;
use ProjectO\HttpException;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use Throwable;

/**
 * Private evidence photos for closeout items.
 *
 * Bytes live in the platform object storage (R2 by default); the database only
 * keeps the object key and metadata. Every read and write is scoped to the
 * project + item pair resolved server-side.
 */
final class CloseoutImageService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForItem(int $projectId, int $itemId): array
    {
        $statement = $this->db->prepare(
            'SELECT image_id, object_key, original_name, content_type, file_size, created_at
             FROM project_closeout_images
             WHERE project_id = :project_id AND item_id = :item_id
             ORDER BY image_id DESC'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        return $statement->fetchAll();
    }

    public function add(
        int $projectId,
        int $itemId,
        int $userId,
        string $temporaryPath,
        string $originalName,
        ObjectStorageInterface $storage
    ): int {
        $inspection = ImageUpload::inspect($temporaryPath);
        $originalName = ImageUpload::originalName($originalName, $inspection['extension']);
        $objectKey = 'closeouts/' . $projectId . '/' . $itemId . '/'
            . bin2hex(random_bytes(16)) . '.' . $inspection['extension'];

        $storage->upload($objectKey, $temporaryPath, $inspection['mime']);
        try {
            $insert = $this->db->prepare(
                'INSERT INTO project_closeout_images
                 (project_id, item_id, object_key, original_name, content_type, file_size, uploaded_by)
                 VALUES (:project_id, :item_id, :object_key, :original_name, :content_type, :file_size, :uploaded_by)'
            );
            $insert->execute([
                'project_id' => $projectId,
                'item_id' => $itemId,
                'object_key' => $objectKey,
                'original_name' => $originalName,
                'content_type' => $inspection['mime'],
                'file_size' => $inspection['size'],
                'uploaded_by' => $userId,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Throwable $exception) {
            try {
                $storage->delete($objectKey);
            } catch (Throwable $cleanupException) {
                error_log('Unable to remove an unreferenced closeout image: ' . get_class($cleanupException));
            }
            throw $exception;
        }
    }

    public function delete(int $projectId, int $itemId, int $imageId, ObjectStorageInterface $storage): void
    {
        $image = $this->requireImage($projectId, $itemId, $imageId);
        $storage->delete((string) $image['object_key']);
        $delete = $this->db->prepare(
            'DELETE FROM project_closeout_images
             WHERE image_id = :image_id AND project_id = :project_id AND item_id = :item_id'
        );
        $delete->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function requireImage(int $projectId, int $itemId, int $imageId): array
    {
        $statement = $this->db->prepare(
            'SELECT image_id, object_key, original_name, content_type, file_size, created_at
             FROM project_closeout_images
             WHERE image_id = :image_id AND project_id = :project_id AND item_id = :item_id
             LIMIT 1'
        );
        $statement->execute([
            'image_id' => $imageId,
            'project_id' => $projectId,
            'item_id' => $itemId,
        ]);
        $image = $statement->fetch();
        if ($image === false) {
            throw new HttpException('附件不存在或不属于该消项事项。', 404);
        }

        return $image;
    }
}
