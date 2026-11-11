<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use DateTimeImmutable;
use PDO;
use ProjectO\HttpException;
use ProjectO\Security\FieldCipher;

/**
 * Labor crew (work-team) directory plus the shared craft-type catalogue.
 *
 * Ownership follows the confirmed contract "a crew answers to its project":
 * every crew row carries a non-null project_id and is read and written only
 * inside the caller-supplied project scope. The craft-type catalogue is global
 * reference data, like Brands and Suppliers, so it has no project_id.
 *
 * Identity numbers never reach the database in clear text: saveCrew() encrypts
 * the normalised value and stores a keyed fingerprint for duplicate detection.
 */
final class CrewDirectoryService
{
    public function __construct(
        private readonly PDO $db,
        private readonly ?FieldCipher $cipher
    ) {
    }

    /** @return array<string, array> */
    public function directoryData(int $projectId, bool $includeArchived = false): array
    {
        return [
            'crews' => $this->crews($projectId, $includeArchived),
            'types' => $this->types(),
        ];
    }

    /** @return array<int, array> */
    public function crews(int $projectId, bool $includeArchived = false): array
    {
        $sql = 'SELECT c.crew_id, c.project_id, c.crew_type_id, c.crew_name, c.leader_name,
                       c.leader_phone, c.wechat_id, c.id_card_number_encrypted,
                       c.emergency_contact_name, c.emergency_contact_phone, c.notes,
                       c.sort_order, c.is_active, c.created_at, c.updated_at,
                       t.type_name
                FROM Labor_Crews c
                JOIN Labor_Crew_Types t ON t.crew_type_id = c.crew_type_id
                WHERE c.project_id = :project_id';
        if (!$includeArchived) {
            $sql .= ' AND c.is_active = 1';
        }
        // Crews with a sort number come first, smallest first; crews left at the
        // default 0 (unset) follow in the original type-grouped order, so a
        // single edited number moves a crew to the front instead of behind every
        // other row. An upgrade with all rows at 0 keeps the previous order.
        $sql .= ' ORDER BY CASE WHEN c.sort_order = 0 THEN 1 ELSE 0 END, c.sort_order,'
            . ' t.sort_order, t.type_name, c.crew_name, c.crew_id';

        $statement = $this->db->prepare($sql);
        $statement->execute(['project_id' => $projectId]);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['id_card_masked'] = $this->mask($this->decryptIdCard($row['id_card_number_encrypted'] ?? null));
        }
        unset($row);

        return $rows;
    }

    /** @return array<int, array> */
    public function types(): array
    {
        return $this->db->query(
            'SELECT crew_type_id, type_name, sort_order
             FROM Labor_Crew_Types
             ORDER BY sort_order, type_name, crew_type_id'
        )->fetchAll();
    }

    public function saveCrew(int $projectId, array $data): int
    {
        $crewId = $this->optionalInt($data['crew_id'] ?? null);
        $crewName = $this->requiredText($data, 'crew_name', 255, '班组名称');
        $leaderName = $this->requiredText($data, 'leader_name', 100, '负责人姓名');
        $leaderPhone = $this->normalisePhone(
            $this->requiredText($data, 'leader_phone', 30, '负责人手机号'),
            '负责人手机号'
        );
        $wechatId = $this->optionalText($data, 'wechat_id', 100, '微信号');
        $emergencyPhoneRaw = $this->optionalText($data, 'emergency_contact_phone', 30, '紧急联系人电话');
        $emergencyName = $this->optionalText($data, 'emergency_contact_name', 100, '紧急联系人姓名');
        if (($emergencyName !== null) !== ($emergencyPhoneRaw !== null)) {
            throw new HttpException('紧急联系人姓名与电话须同时填写或同时留空。', 422);
        }
        $emergencyPhone = $emergencyPhoneRaw === null
            ? null
            : $this->normalisePhone($emergencyPhoneRaw, '紧急联系人电话');
        $notes = $this->optionalText($data, 'notes', 2000, '备注');
        $sortOrder = $this->optionalSortOrder($data['sort_order'] ?? null);
        $typeId = $this->requireType($this->requiredInt($data['crew_type_id'] ?? null, '工种'));

        // The form never re-displays the stored number, so a blank field on edit
        // means "keep the existing value"; clearing requires the explicit
        // id_card_clear checkbox. That avoids silently destroying data.
        $rawIdCard = $this->optionalText($data, 'id_card_number', 25, '身份证号');
        $clearRequested = isset($data['id_card_clear']) && $data['id_card_clear'] !== '' && $data['id_card_clear'] !== '0';
        $keepExisting = $crewId !== null && $rawIdCard === null && !$clearRequested;
        $idCard = $this->normaliseIdCard($rawIdCard);
        $fingerprint = null;
        $encrypted = null;
        if ($idCard !== null) {
            if ($this->cipher === null) {
                throw new HttpException(
                    '服务器尚未配置数据加密密钥，无法保存身份证号。请联系管理员配置 APP_DATA_KEY。',
                    503
                );
            }
            $fingerprint = $this->cipher->fingerprint($idCard);
            $encrypted = $this->cipher->encrypt($idCard);
        }
        $this->assertFingerprintIsUnused($projectId, $fingerprint, $crewId);
        $this->assertCrewNameIsUnused($projectId, $crewName, $crewId);

        if ($crewId === null) {
            $statement = $this->db->prepare(
                'INSERT INTO Labor_Crews
                    (project_id, crew_type_id, crew_name, leader_name, leader_phone, wechat_id,
                     id_card_number_encrypted, id_card_fingerprint, emergency_contact_name,
                     emergency_contact_phone, notes, sort_order, is_active)
                 VALUES (:project_id, :type_id, :crew_name, :leader_name, :leader_phone, :wechat_id,
                         :id_card, :fingerprint, :emergency_name, :emergency_phone, :notes, :sort_order, 1)'
            );
        } else {
            $this->requireCrewInProject($projectId, $crewId);
            $sql = 'UPDATE Labor_Crews
                 SET crew_type_id = :type_id, crew_name = :crew_name, leader_name = :leader_name,
                     leader_phone = :leader_phone, wechat_id = :wechat_id,
                     emergency_contact_name = :emergency_name, emergency_contact_phone = :emergency_phone,
                     notes = :notes, sort_order = :sort_order';
            if (!$keepExisting) {
                $sql .= ', id_card_number_encrypted = :id_card, id_card_fingerprint = :fingerprint';
            }
            $sql .= ' WHERE project_id = :project_id AND crew_id = :crew_id';
            $statement = $this->db->prepare($sql);
        }

        $params = [
            'project_id' => $projectId,
            'type_id' => $typeId,
            'crew_name' => $crewName,
            'leader_name' => $leaderName,
            'leader_phone' => $leaderPhone,
            'wechat_id' => $wechatId,
            'emergency_name' => $emergencyName,
            'emergency_phone' => $emergencyPhone,
            'notes' => $notes,
            'sort_order' => $sortOrder,
        ];
        if ($crewId === null) {
            $params['id_card'] = $encrypted;
            $params['fingerprint'] = $fingerprint;
        } else {
            $params['crew_id'] = $crewId;
            if (!$keepExisting) {
                $params['id_card'] = $encrypted;
                $params['fingerprint'] = $fingerprint;
            }
        }
        $statement->execute($params);

        return $crewId ?? (int) $this->db->lastInsertId();
    }

    /**
     * Archiving keeps the row, the encrypted identity number and the history
     * intact; crews are never hard deleted from this screen.
     */
    public function archiveCrew(int $projectId, int $crewId): void
    {
        $this->requireCrewInProject($projectId, $crewId);
        $statement = $this->db->prepare(
            'UPDATE Labor_Crews SET is_active = 0 WHERE project_id = :project_id AND crew_id = :crew_id'
        );
        $statement->execute(['project_id' => $projectId, 'crew_id' => $crewId]);
    }

    public function restoreCrew(int $projectId, int $crewId): void
    {
        $this->requireCrewInProject($projectId, $crewId);
        $this->assertCrewNameIsUnused($projectId, $this->crewName($projectId, $crewId), $crewId);
        $statement = $this->db->prepare(
            'UPDATE Labor_Crews SET is_active = 1 WHERE project_id = :project_id AND crew_id = :crew_id'
        );
        $statement->execute(['project_id' => $projectId, 'crew_id' => $crewId]);
    }

    public function saveType(array $data): int
    {
        $typeId = $this->optionalInt($data['crew_type_id'] ?? null);
        $typeName = $this->requiredText($data, 'type_name', 100, '工种名称');
        $sortOrder = $this->optionalSortOrder($data['sort_order'] ?? null);

        $this->assertTypeNameIsUnused($typeName, $typeId);

        if ($typeId === null) {
            $statement = $this->db->prepare(
                'INSERT INTO Labor_Crew_Types (type_name, sort_order) VALUES (:name, :sort_order)'
            );
            $statement->execute(['name' => $typeName, 'sort_order' => $sortOrder]);

            return (int) $this->db->lastInsertId();
        }

        $this->requireType($typeId);
        $statement = $this->db->prepare(
            'UPDATE Labor_Crew_Types SET type_name = :name, sort_order = :sort_order
             WHERE crew_type_id = :type_id'
        );
        $statement->execute(['name' => $typeName, 'sort_order' => $sortOrder, 'type_id' => $typeId]);

        return $typeId;
    }

    /**
     * Types are referenced by crews across every project, so they can only be
     * removed once nothing points at them. The foreign key stays RESTRICT.
     */
    public function deleteType(int $typeId): void
    {
        $this->requireType($typeId);
        $statement = $this->db->prepare('SELECT COUNT(*) FROM Labor_Crews WHERE crew_type_id = :id');
        $statement->execute(['id' => $typeId]);
        $references = (int) $statement->fetchColumn();
        if ($references > 0) {
            throw new HttpException(
                '该工种下仍有 ' . $references . ' 个班组，不能删除。请先调整班组工种或归档班组。',
                409
            );
        }

        $delete = $this->db->prepare('DELETE FROM Labor_Crew_Types WHERE crew_type_id = :id');
        $delete->execute(['id' => $typeId]);
    }

    /**
     * Rows for the Excel export: craft type, crew name, leader name, phone and
     * remarks. Identity numbers and WeChat ids are never part of the exported
     * file, and archived crews are excluded by design. Rows use the same manual
     * order as the on-screen list.
     *
     * @return array<int, array<string, string|null>>
     */
    public function exportRows(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT t.type_name, c.crew_name, c.leader_name, c.leader_phone, c.notes
             FROM Labor_Crews c
             JOIN Labor_Crew_Types t ON t.crew_type_id = c.crew_type_id
             WHERE c.project_id = :project_id AND c.is_active = 1
             ORDER BY CASE WHEN c.sort_order = 0 THEN 1 ELSE 0 END, c.sort_order,
                     t.sort_order, t.type_name, c.crew_name, c.crew_id'
        );
        $statement->execute(['project_id' => $projectId]);

        return $statement->fetchAll();
    }

    public function countActive(int $projectId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM Labor_Crews WHERE project_id = :project_id AND is_active = 1'
        );
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn();
    }

    public function decryptIdCard(?string $payload): ?string
    {
        if ($payload === null || $payload === '' || $this->cipher === null) {
            return null;
        }

        return $this->cipher->decrypt($payload);
    }

    public function isEncryptionConfigured(): bool
    {
        return $this->cipher !== null;
    }

    /** 前 6 位 + 掩码 + 后 4 位；无权限者永远只看到该形式。 */
    public function mask(?string $idCard): string
    {
        if ($idCard === null || $idCard === '') {
            return '';
        }
        $length = strlen($idCard);
        if ($length <= 10) {
            return substr($idCard, 0, 2) . str_repeat('*', max(1, $length - 4)) . substr($idCard, -2);
        }

        return substr($idCard, 0, 6) . str_repeat('*', $length - 10) . substr($idCard, -4);
    }

    /**
     * Accepts the 18-digit resident identity card and verifies the check digit, so
     * a mistyped number fails on write instead of polluting the directory.
     */
    private function normaliseIdCard(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalised = strtoupper(str_replace([' ', '-', '　'], '', $value));
        if (preg_match('/^\d{17}[\dX]$/', $normalised) !== 1) {
            throw new HttpException('身份证号须为 18 位（最后一位可为 X）。', 422);
        }

        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $checkCharacters = '10X98765432';
        $sum = 0;
        for ($index = 0; $index < 17; $index++) {
            $sum += (int) $normalised[$index] * $weights[$index];
        }
        if ($checkCharacters[$sum % 11] !== $normalised[17]) {
            throw new HttpException('身份证号校验位不正确，请核对后重试。', 422);
        }

        $birthday = substr($normalised, 6, 8);
        $date = DateTimeImmutable::createFromFormat('Ymd', $birthday);
        if ($date === false || $date->format('Ymd') !== $birthday) {
            throw new HttpException('身份证号中的出生日期无效。', 422);
        }

        return $normalised;
    }

    private function normalisePhone(string $value, string $label): string
    {
        $normalised = str_replace([' ', '-', '　'], '', $value);
        if (preg_match('/^\+?[0-9]{7,15}$/', $normalised) !== 1) {
            throw new HttpException($label . '须为 7 至 15 位数字，可带 + 国家码前缀。', 422);
        }

        return $normalised;
    }

    private function requireType(int $typeId): int
    {
        $statement = $this->db->prepare('SELECT 1 FROM Labor_Crew_Types WHERE crew_type_id = :id');
        $statement->execute(['id' => $typeId]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('工种不存在，请重新选择。', 422);
        }

        return $typeId;
    }

    private function requireCrewInProject(int $projectId, int $crewId): void
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM Labor_Crews WHERE project_id = :project_id AND crew_id = :crew_id'
        );
        $statement->execute(['project_id' => $projectId, 'crew_id' => $crewId]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('班组不存在或不属于当前项目。', 404);
        }
    }

    private function crewName(int $projectId, int $crewId): string
    {
        $statement = $this->db->prepare(
            'SELECT crew_name FROM Labor_Crews WHERE project_id = :project_id AND crew_id = :crew_id'
        );
        $statement->execute(['project_id' => $projectId, 'crew_id' => $crewId]);
        $name = $statement->fetchColumn();

        return is_string($name) ? $name : '';
    }

    private function assertCrewNameIsUnused(int $projectId, string $crewName, ?int $crewId): void
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM Labor_Crews
             WHERE project_id = :project_id AND crew_name = :crew_name AND is_active = 1
               AND (:crew_id IS NULL OR crew_id <> :exclude_crew_id)'
        );
        $statement->execute([
            'project_id' => $projectId,
            'crew_name' => $crewName,
            'crew_id' => $crewId,
            'exclude_crew_id' => $crewId,
        ]);
        if ((int) $statement->fetchColumn() > 0) {
            throw new HttpException('当前项目已有同名班组「' . $crewName . '」，请改用其他名称。', 409);
        }
    }

    private function assertFingerprintIsUnused(int $projectId, ?string $fingerprint, ?int $crewId): void
    {
        if ($fingerprint === null) {
            return;
        }
        $statement = $this->db->prepare(
            'SELECT crew_name FROM Labor_Crews
             WHERE project_id = :project_id AND id_card_fingerprint = :fingerprint
               AND (:crew_id IS NULL OR crew_id <> :exclude_crew_id)
             LIMIT 1'
        );
        $statement->execute([
            'project_id' => $projectId,
            'fingerprint' => $fingerprint,
            'crew_id' => $crewId,
            'exclude_crew_id' => $crewId,
        ]);
        $existing = $statement->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            throw new HttpException(
                '该身份证号已登记在项目内的班组「' . $existing . '」，同一负责人不能重复登记。',
                409
            );
        }
    }

    private function assertTypeNameIsUnused(string $typeName, ?int $typeId): void
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM Labor_Crew_Types
             WHERE type_name = :name AND (:type_id IS NULL OR crew_type_id <> :exclude_type_id)'
        );
        $statement->execute(['name' => $typeName, 'type_id' => $typeId, 'exclude_type_id' => $typeId]);
        if ((int) $statement->fetchColumn() > 0) {
            throw new HttpException('工种「' . $typeName . '」已存在。', 409);
        }
    }

    private function optionalSortOrder(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $sortOrder = filter_var($value, FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 9999) {
            throw new HttpException('排序须为 0 至 9999 的整数。', 422);
        }

        return $sortOrder;
    }

    private function requiredText(array $data, string $key, int $maxLength, string $label): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new HttpException($label . '不能为空且不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value;
    }

    private function optionalText(array $data, string $key, int $maxLength, string $label): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException($label . '不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value === '' ? null : $value;
    }

    private function requiredInt(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException($label . '无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($label . '无效。', 422);
        }

        return $id;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredInt($value, '编号');
    }
}
