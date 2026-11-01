<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Plugin\PluginResponse;
use RuntimeException;
use Throwable;

final class PlatformAdministrationService
{
    public function __construct(
        private readonly PDO $db,
        private readonly PluginRegistry $plugins
    ) {
    }

    public function pageData(): array
    {
        Support::requirePermission($this->db, 'manage_platform');
        $this->synchronizePluginPermissions();

        $settings = $this->settings();
        $users = $this->db->query(
            'SELECT user_id, username, country_code, is_active FROM Users ORDER BY username'
        )->fetchAll();
        $userRoles = $this->db->query(
            'SELECT ur.user_id, ur.role_id
             FROM User_Roles ur
             ORDER BY ur.user_id, ur.role_id'
        )->fetchAll();
        foreach ($users as &$user) {
            $user['role_ids'] = [];
        }
        unset($user);
        $userIndex = [];
        foreach ($users as $index => $user) {
            $userIndex[(int) $user['user_id']] = $index;
        }
        foreach ($userRoles as $userRole) {
            $userId = (int) $userRole['user_id'];
            if (isset($userIndex[$userId])) {
                $users[$userIndex[$userId]]['role_ids'][] = (int) $userRole['role_id'];
            }
        }

        $roles = $this->db->query(
            'SELECT role_id, role_name, description FROM Roles ORDER BY role_name'
        )->fetchAll();
        $rolePermissions = $this->db->query(
            'SELECT role_id, permission_id FROM Role_Permissions'
        )->fetchAll();
        foreach ($roles as &$role) {
            $role['permission_ids'] = [];
        }
        unset($role);
        $roleIndex = [];
        foreach ($roles as $index => $role) {
            $roleIndex[(int) $role['role_id']] = $index;
        }
        foreach ($rolePermissions as $rolePermission) {
            $roleId = (int) $rolePermission['role_id'];
            if (isset($roleIndex[$roleId])) {
                $roles[$roleIndex[$roleId]]['permission_ids'][] = (int) $rolePermission['permission_id'];
            }
        }

        $permissions = $this->db->query(
            'SELECT permission_id, permission_name, display_name, description, module
             FROM Permissions
             ORDER BY module, display_name, permission_name'
        )->fetchAll();

        return [
            'title' => '平台管理',
            'settings' => $settings,
            'plugins' => $this->plugins->catalog(),
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'runtime' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'environment' => (string) (getenv('APP_ENV') ?: '未设置'),
                'timezone' => date_default_timezone_get(),
                'database_driver' => (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME),
            ],
        ];
    }

    public function theme(): string
    {
        return $this->settings()['theme'];
    }

    public function dispatch(string $action, array $input): PluginResponse
    {
        Support::requirePermission($this->db, 'manage_platform');

        match ($action) {
            'admin.settings.save' => $this->saveSettings($input),
            'admin.plugin.toggle' => $this->togglePlugin($input),
            'admin.user.create' => $this->createUser($input),
            'admin.user.update' => $this->updateUser($input),
            'admin.role.create' => $this->createRole($input),
            'admin.role.update' => $this->updateRole($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };

        Support::flash('平台管理操作已完成。');
        return PluginResponse::redirect('?page=admin');
    }

    private function saveSettings(array $input): void
    {
        $theme = $this->requiredString($input, 'theme');
        $countryCode = strtoupper($this->requiredString($input, 'default_country_code'));
        $currencyCode = strtoupper($this->requiredString($input, 'default_currency_code'));
        if (!in_array($theme, ['light', 'dark'], true)) {
            throw new HttpException('主题选项无效。', 422);
        }
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new HttpException('默认国家代码必须是两位 ISO 代码。', 422);
        }
        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new HttpException('默认币种代码必须是三位 ISO 代码。', 422);
        }

        $this->transaction(function () use ($theme, $countryCode, $currencyCode): void {
            $this->lockAdminGuard();
            $this->writeSetting('theme', $theme);
            $this->writeSetting('default_country_code', $countryCode);
            $this->writeSetting('default_currency_code', $currencyCode);
            Support::audit($this->db, 'platform.settings.update', 'Updated platform settings.');
        });
    }

    private function togglePlugin(array $input): void
    {
        $pluginId = $this->requiredString($input, 'plugin_id');
        $enabled = $this->requiredString($input, 'enabled');
        if (!in_array($enabled, ['0', '1'], true)) {
            throw new HttpException('插件状态无效。', 422);
        }

        $this->transaction(function () use ($pluginId, $enabled): void {
            $this->lockAdminGuard();
            $this->plugins->setEnabled($pluginId, $enabled === '1');
            Support::audit(
                $this->db,
                'platform.plugin.state',
                'Changed plugin ' . $pluginId . ' to ' . ($enabled === '1' ? 'enabled' : 'disabled') . '.'
            );
        });
    }

    private function createUser(array $input): void
    {
        $username = trim($this->requiredString($input, 'username'));
        $password = $this->requiredRawString($input, 'password');
        $countryCode = strtoupper($this->requiredString($input, 'country_code'));
        $roleIds = $this->selectedIds($input['role_ids'] ?? []);
        if ($username === '' || mb_strlen($username) > 255) {
            throw new HttpException('用户名不能为空，且最多为 255 个字符。', 422);
        }
        if (strlen($password) < 12) {
            throw new HttpException('初始密码至少需要 12 个字符。', 422);
        }
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new HttpException('国家代码必须是两位 ISO 代码。', 422);
        }
        if ($roleIds === []) {
            throw new HttpException('新用户至少需要分配一个角色。', 422);
        }

        $this->transaction(function () use ($username, $password, $countryCode, $roleIds): void {
            $this->lockAdminGuard();
            $this->assertUsernameAvailable($username, null);
            $this->assertRolesExist($roleIds);
            $insert = $this->db->prepare(
                'INSERT INTO Users (username, password_hash, country_code, is_active)
                 VALUES (:username, :password_hash, :country_code, 1)'
            );
            $insert->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'country_code' => $countryCode,
            ]);
            $userId = (int) $this->db->lastInsertId();
            $this->replaceUserRoles($userId, $roleIds);
            Support::audit($this->db, 'platform.user.create', 'Created user ' . $userId . '.');
        });
    }

    private function updateUser(array $input): void
    {
        $userId = $this->positiveInt($input['user_id'] ?? null, '用户编号');
        $username = trim($this->requiredString($input, 'username'));
        $countryCode = strtoupper($this->requiredString($input, 'country_code'));
        $active = $this->requiredString($input, 'is_active');
        $password = $input['password'] ?? '';
        if (!is_string($password)) {
            throw new HttpException('密码字段格式无效。', 422);
        }
        $roleIds = $this->selectedIds($input['role_ids'] ?? []);
        if ($username === '' || mb_strlen($username) > 255) {
            throw new HttpException('用户名不能为空，且最多为 255 个字符。', 422);
        }
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new HttpException('国家代码必须是两位 ISO 代码。', 422);
        }
        if (!in_array($active, ['0', '1'], true)) {
            throw new HttpException('账号状态无效。', 422);
        }
        if ($password !== '' && strlen($password) < 12) {
            throw new HttpException('重设密码至少需要 12 个字符。', 422);
        }
        if ($userId === (int) ($_SESSION['user_id'] ?? 0) && $active !== '1') {
            throw new HttpException('不能停用当前登录账号。', 422);
        }

        $this->transaction(function () use (
            $userId,
            $username,
            $countryCode,
            $active,
            $password,
            $roleIds
        ): void {
            $this->lockAdminGuard();
            $this->assertUserExists($userId);
            $this->assertUsernameAvailable($username, $userId);
            $this->assertRolesExist($roleIds);

            if ($password === '') {
                $update = $this->db->prepare(
                    'UPDATE Users
                     SET username = :username, country_code = :country_code, is_active = :is_active
                     WHERE user_id = :user_id'
                );
                $update->execute([
                    'username' => $username,
                    'country_code' => $countryCode,
                    'is_active' => (int) $active,
                    'user_id' => $userId,
                ]);
            } else {
                $update = $this->db->prepare(
                    'UPDATE Users
                     SET username = :username, country_code = :country_code, is_active = :is_active,
                         password_hash = :password_hash
                     WHERE user_id = :user_id'
                );
                $update->execute([
                    'username' => $username,
                    'country_code' => $countryCode,
                    'is_active' => (int) $active,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'user_id' => $userId,
                ]);
            }

            $this->replaceUserRoles($userId, $roleIds);
            $this->assertAdministratorRemains();
            Support::audit($this->db, 'platform.user.update', 'Updated user ' . $userId . '.');
        });
    }

    private function createRole(array $input): void
    {
        $name = trim($this->requiredString($input, 'role_name'));
        $description = $this->optionalString($input, 'description');
        $permissionIds = $this->selectedIds($input['permission_ids'] ?? []);
        $this->validateRoleFields($name, $description);

        $this->transaction(function () use ($name, $description, $permissionIds): void {
            $this->lockAdminGuard();
            $this->assertRoleNameAvailable($name, null);
            $this->assertPermissionsExist($permissionIds);
            $insert = $this->db->prepare(
                'INSERT INTO Roles (role_name, description) VALUES (:role_name, :description)'
            );
            $insert->execute(['role_name' => $name, 'description' => $description]);
            $roleId = (int) $this->db->lastInsertId();
            $this->replaceRolePermissions($roleId, $permissionIds);
            $this->assertAdministratorRemains();
            Support::audit($this->db, 'platform.role.create', 'Created role ' . $roleId . '.');
        });
    }

    private function updateRole(array $input): void
    {
        $roleId = $this->positiveInt($input['role_id'] ?? null, '角色编号');
        $name = trim($this->requiredString($input, 'role_name'));
        $description = $this->optionalString($input, 'description');
        $permissionIds = $this->selectedIds($input['permission_ids'] ?? []);
        $this->validateRoleFields($name, $description);

        $this->transaction(function () use ($roleId, $name, $description, $permissionIds): void {
            $this->lockAdminGuard();
            $this->assertRoleExists($roleId);
            $this->assertRoleNameAvailable($name, $roleId);
            $this->assertPermissionsExist($permissionIds);
            $update = $this->db->prepare(
                'UPDATE Roles SET role_name = :role_name, description = :description
                 WHERE role_id = :role_id'
            );
            $update->execute([
                'role_name' => $name,
                'description' => $description,
                'role_id' => $roleId,
            ]);
            $this->replaceRolePermissions($roleId, $permissionIds);
            $this->assertAdministratorRemains();
            Support::audit($this->db, 'platform.role.permissions', 'Updated permissions for role ' . $roleId . '.');
        });
    }

    private function settings(): array
    {
        $rows = $this->db->query(
            "SELECT setting_key, setting_value
             FROM System_Settings
             WHERE setting_key IN ('theme', 'language', 'default_country_code', 'default_currency_code')"
        )->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        foreach (['theme', 'language', 'default_country_code', 'default_currency_code'] as $key) {
            if (!array_key_exists($key, $settings)) {
                throw new RuntimeException('Required platform setting is missing: ' . $key);
            }
        }
        if (!in_array($settings['theme'], ['light', 'dark'], true)) {
            throw new RuntimeException('Stored platform theme is invalid.');
        }

        return $settings;
    }

    private function synchronizePluginPermissions(): void
    {
        $find = $this->db->prepare(
            'SELECT permission_id FROM Permissions WHERE permission_name = :permission_name'
        );
        $insert = $this->db->prepare(
            'INSERT INTO Permissions (permission_name, display_name, description, module)
             VALUES (:permission_name, :display_name, :description, :module)'
        );
        $update = $this->db->prepare(
            'UPDATE Permissions
             SET display_name = :display_name, description = :description, module = :module
             WHERE permission_name = :permission_name'
        );

        foreach ($this->plugins->permissions() as $permission) {
            $name = (string) $permission['name'];
            $find->execute(['permission_name' => $name]);
            $parameters = [
                'permission_name' => $name,
                'display_name' => (string) $permission['display_name'],
                'description' => $permission['description'] ?? null,
                'module' => (string) ($permission['module'] ?? 'Plugin'),
            ];
            if ($find->fetchColumn() === false) {
                $insert->execute($parameters);
            } else {
                $update->execute($parameters);
            }
        }
    }

    private function writeSetting(string $key, string $value): void
    {
        $select = $this->db->prepare(
            'SELECT 1 FROM System_Settings WHERE setting_key = :setting_key'
        );
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

    private function lockAdminGuard(): void
    {
        $update = $this->db->exec(
            "UPDATE System_Settings
             SET setting_value = setting_value
             WHERE setting_key = 'admin_guard'"
        );
        if ($update === false) {
            throw new RuntimeException('Unable to lock platform administration state.');
        }
        $check = $this->db->query(
            "SELECT 1 FROM System_Settings WHERE setting_key = 'admin_guard'"
        );
        if ($check->fetchColumn() === false) {
            throw new RuntimeException('Platform administration guard is missing.');
        }
    }

    private function assertAdministratorRemains(): void
    {
        $count = (int) $this->db->query(
            "SELECT COUNT(DISTINCT u.user_id)
             FROM Users u
             JOIN User_Roles ur ON ur.user_id = u.user_id
             JOIN Role_Permissions rp ON rp.role_id = ur.role_id
             JOIN Permissions p ON p.permission_id = rp.permission_id
             WHERE u.is_active = 1 AND p.permission_name = 'manage_platform'"
        )->fetchColumn();
        if ($count < 1) {
            throw new HttpException('操作会移除最后一个有效平台管理员，已取消。', 422);
        }
    }

    private function assertUsernameAvailable(string $username, ?int $exceptUserId): void
    {
        $sql = 'SELECT 1 FROM Users WHERE username = :username';
        $parameters = ['username' => $username];
        if ($exceptUserId !== null) {
            $sql .= ' AND user_id <> :user_id';
            $parameters['user_id'] = $exceptUserId;
        }
        $check = $this->db->prepare($sql . ' LIMIT 1');
        $check->execute($parameters);
        if ($check->fetchColumn() !== false) {
            throw new HttpException('用户名已存在。', 422);
        }
    }

    private function assertUserExists(int $userId): void
    {
        $check = $this->db->prepare('SELECT 1 FROM Users WHERE user_id = :user_id');
        $check->execute(['user_id' => $userId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('用户不存在。', 404);
        }
    }

    private function assertRoleExists(int $roleId): void
    {
        $check = $this->db->prepare('SELECT 1 FROM Roles WHERE role_id = :role_id');
        $check->execute(['role_id' => $roleId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('角色不存在。', 404);
        }
    }

    private function assertRoleNameAvailable(string $name, ?int $exceptRoleId): void
    {
        $sql = 'SELECT 1 FROM Roles WHERE role_name = :role_name';
        $parameters = ['role_name' => $name];
        if ($exceptRoleId !== null) {
            $sql .= ' AND role_id <> :role_id';
            $parameters['role_id'] = $exceptRoleId;
        }
        $check = $this->db->prepare($sql . ' LIMIT 1');
        $check->execute($parameters);
        if ($check->fetchColumn() !== false) {
            throw new HttpException('角色名称已存在。', 422);
        }
    }

    private function assertRolesExist(array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }
        $check = $this->db->prepare('SELECT 1 FROM Roles WHERE role_id = :role_id');
        foreach ($roleIds as $roleId) {
            $check->execute(['role_id' => $roleId]);
            if ($check->fetchColumn() === false) {
                throw new HttpException('所选角色不存在。', 422);
            }
        }
    }

    private function assertPermissionsExist(array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }
        $check = $this->db->prepare(
            'SELECT 1 FROM Permissions WHERE permission_id = :permission_id'
        );
        foreach ($permissionIds as $permissionId) {
            $check->execute(['permission_id' => $permissionId]);
            if ($check->fetchColumn() === false) {
                throw new HttpException('所选权限不存在。', 422);
            }
        }
    }

    private function replaceUserRoles(int $userId, array $roleIds): void
    {
        $delete = $this->db->prepare('DELETE FROM User_Roles WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);
        $insert = $this->db->prepare(
            'INSERT INTO User_Roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        foreach ($roleIds as $roleId) {
            $insert->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    private function replaceRolePermissions(int $roleId, array $permissionIds): void
    {
        $delete = $this->db->prepare(
            'DELETE FROM Role_Permissions WHERE role_id = :role_id'
        );
        $delete->execute(['role_id' => $roleId]);
        $insert = $this->db->prepare(
            'INSERT INTO Role_Permissions (role_id, permission_id)
             VALUES (:role_id, :permission_id)'
        );
        foreach ($permissionIds as $permissionId) {
            $insert->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    private function validateRoleFields(string $name, ?string $description): void
    {
        if ($name === '' || mb_strlen($name) > 50) {
            throw new HttpException('角色名称不能为空，且最多为 50 个字符。', 422);
        }
        if ($description !== null && mb_strlen($description) > 4000) {
            throw new HttpException('角色说明最多为 4000 个字符。', 422);
        }
    }

    private function selectedIds(mixed $value): array
    {
        if (!is_array($value)) {
            throw new HttpException('选择项格式无效。', 422);
        }
        $ids = [];
        foreach ($value as $item) {
            $id = filter_var($item, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new HttpException('选择项编号无效。', 422);
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($label . '无效。', 422);
        }

        return $id;
    }

    private function requiredString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value)) {
            throw new HttpException('表单字段格式无效。', 422);
        }

        return trim($value);
    }

    private function requiredRawString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value)) {
            throw new HttpException('表单字段格式无效。', 422);
        }

        return $value;
    }

    private function optionalString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value)) {
            throw new HttpException('表单字段格式无效。', 422);
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function transaction(callable $operation): void
    {
        $this->db->beginTransaction();
        try {
            $operation();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
