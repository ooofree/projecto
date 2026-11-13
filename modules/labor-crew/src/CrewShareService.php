<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use PDO;

/**
 * Opt-in public sharing of the crew contact directory.
 *
 * A share is an unguessable per-project token: whoever holds the link may read
 * the public contact page without signing in, and nothing else. One active
 * token per project; disabling keeps the row (revoked_at) for audit and never
 * deletes data. Re-enabling issues a fresh token so a leaked old link stays
 * dead. The public read path resolves the project from the token server-side —
 * a client-supplied project id is never trusted.
 */
final class CrewShareService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** The project's active share row, or null when sharing is off. */
    public function activeShare(int $projectId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT share_id, project_id, token, created_by, created_at, revoked_at, revoked_by
             FROM Labor_Crew_Shares
             WHERE project_id = :project_id AND revoked_at IS NULL
             ORDER BY share_id DESC
             LIMIT 1'
        );
        $statement->execute(['project_id' => $projectId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Enable sharing for the project. Repeated calls while a share is active
     * return the existing token instead of minting a second one, so a double
     * submit cannot invalidate links that were already handed out.
     */
    public function enable(int $projectId, int $userId): string
    {
        $this->db->beginTransaction();
        try {
            // Serialize concurrent enables per project on MySQL by locking the
            // parent project row; without it two simultaneous requests could
            // both pass the active check and mint two live tokens. SQLite
            // serialises writers at the database level already.
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lock = $this->db->prepare('SELECT project_id FROM Projects WHERE project_id = :project_id FOR UPDATE');
                $lock->execute(['project_id' => $projectId]);
                if ($lock->fetchColumn() === false) {
                    throw new \ProjectO\HttpException('项目不存在或已不可用。', 404);
                }
            }

            $existing = $this->activeShare($projectId);
            if ($existing !== null) {
                $this->db->commit();

                return (string) $existing['token'];
            }

            // Each enable appends a new row with a fresh token; revoked rows
            // are never rewritten, so old links stay dead and the full
            // enable/revoke history remains in the table and the audit log.
            $token = bin2hex(random_bytes(16));
            $statement = $this->db->prepare(
                'INSERT INTO Labor_Crew_Shares (project_id, token, created_by)
                 VALUES (:project_id, :token, :created_by)'
            );
            $statement->execute([
                'project_id' => $projectId,
                'token' => $token,
                'created_by' => $userId,
            ]);
            $this->db->commit();

            return $token;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** Every active share across projects (used to refresh static pages). */
    public function allActiveShares(): array
    {
        return $this->db->query(
            'SELECT share_id, project_id, token, created_by, created_at
             FROM Labor_Crew_Shares
             WHERE revoked_at IS NULL
             ORDER BY share_id'
        )->fetchAll();
    }

    /** Disable sharing; the row is kept with revoked_at set. Idempotent. */
    public function revoke(int $projectId, int $userId): bool
    {
        $statement = $this->db->prepare(
            'UPDATE Labor_Crew_Shares
             SET revoked_at = CURRENT_TIMESTAMP, revoked_by = :revoked_by
             WHERE project_id = :project_id AND revoked_at IS NULL'
        );
        $statement->execute(['project_id' => $projectId, 'revoked_by' => $userId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Public read path: resolve an active token to its project. Archived
     * projects are refused exactly like the session-based project context, so
     * archiving a project also stops its public contact page.
     */
    public function projectForToken(string $token): ?array
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) !== 1) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT p.project_id, p.project_name, p.start_date, p.end_date, p.status
             FROM Labor_Crew_Shares s
             JOIN Projects p ON p.project_id = s.project_id
             WHERE s.token = :token AND s.revoked_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'project\'
                     AND a.entity_id = p.project_id AND a.project_id = p.project_id
               )
             LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $project = $statement->fetch();

        return $project === false ? null : $project;
    }

    /**
     * Absolute URL of the public page, derived from the current request so the
     * copied link works behind the same host/proxy the sharer is using.
     */
    public static function publicUrl(string $token): string
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        // The host header is client input: keep only characters valid in an
        // authority so it can never inject headers or markup into the link.
        if (preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $host) !== 1) {
            $host = 'localhost';
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = str_replace('\\', '/', dirname($script));
        $base = ($base === '/' || $base === '.') ? '' : rtrim($base, '/');

        return ($https ? 'https://' : 'http://') . $host . $base . '/?page=labor-crew-share&token=' . $token;
    }
}
