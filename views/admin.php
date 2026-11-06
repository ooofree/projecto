<?php
ob_start();
$isSection = static fn (string $key): bool => $activeSection === $key;
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">平台设置</p>
        <h1>系统设置</h1>
        <p class="muted">管理系统默认配置、人员与角色权限、插件状态和运行参数。</p>
    </div>
</section>

<div class="admin-shell">
    <aside class="admin-sidebar">
        <nav class="admin-nav" aria-label="系统设置导航">
            <?php foreach ($sections as $key => $label): ?>
                <a
                    href="?page=admin&amp;section=<?= $escape($key) ?>"
                    <?= $isSection($key) ? 'aria-current="page"' : '' ?>
                ><?= $escape($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="admin-content">
        <?php if ($isSection('settings')): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>系统配置</h2>
                        <p class="muted">平台名称用于顶栏、登录页和浏览器标题。默认国家/币种初始为 CN / CNY，使用 ISO 代码；这不会转换已有金额或汇率。</p>
                    </div>
                </div>
                <form method="post" action="" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="admin.settings.save">
                    <label>
                        平台名称
                        <input name="platform_name" value="<?= $escape($settings['platform_name']) ?>" maxlength="80" required>
                    </label>
                    <label>
                        系统主题
                        <select name="theme">
                            <option value="light" <?= $settings['theme'] === 'light' ? 'selected' : '' ?>>浅色</option>
                            <option value="dark" <?= $settings['theme'] === 'dark' ? 'selected' : '' ?>>深色</option>
                        </select>
                    </label>
                    <label>
                        默认国家代码
                        <input name="default_country_code" value="<?= $escape($settings['default_country_code']) ?>" maxlength="2" pattern="[A-Za-z]{2}" required>
                    </label>
                    <label>
                        默认币种代码
                        <select name="default_currency_code" required>
                            <?php foreach ($currencies as $code => $name): ?>
                                <option value="<?= $escape($code) ?>" <?= $code === $settings['default_currency_code'] ? 'selected' : '' ?>><?= $escape($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        项目默认时区
                        <select name="timezone" required>
                            <?php foreach ($timezones as $zone): ?>
                                <option value="<?= $escape($zone) ?>" <?= $zone === $settings['timezone'] ? 'selected' : '' ?>><?= $escape($zone) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        管理界面语言
                        <input value="简体中文（zh-CN）" readonly>
                    </label>
                    <div class="field-wide">
                        <button class="button button-primary" type="submit">保存系统配置</button>
                    </div>
                </form>
            </section>
        <?php elseif ($isSection('people')): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>人员管理</h2>
                        <p class="muted">
                            账号通过停用保留历史关联，不提供硬删除。人员可在<a href="?page=profile">个人设置</a>中更改自己的密码；管理员可在此重设密码。
                        </p>
                    </div>
                    <span class="badge"><?= count($users) ?> 人</span>
                </div>
                <details class="entry-disclosure">
                    <summary>新增人员</summary>
                    <form method="post" action="" class="form-grid admin-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="admin.user.create">
                        <label>
                            用户名
                            <input name="username" maxlength="255" required>
                        </label>
                        <label>
                            初始密码（至少 12 位）
                            <input type="password" name="password" minlength="12" autocomplete="new-password" required>
                        </label>
                        <label>
                            国家代码
                            <input name="country_code" value="<?= $escape($settings['default_country_code']) ?>" maxlength="2" pattern="[A-Za-z]{2}" required>
                        </label>
                        <fieldset class="field-wide checkbox-group">
                            <legend>分配角色（至少选择一个）</legend>
                            <?php foreach ($roles as $role): ?>
                                <label class="checkbox-field">
                                    <input type="checkbox" name="role_ids[]" value="<?= (int) $role['role_id'] ?>">
                                    <span><?= $escape($role['role_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <div class="field-wide">
                            <button class="button button-primary" type="submit">创建人员</button>
                        </div>
                    </form>
                </details>

                <?php if ($users === []): ?>
                    <p class="empty-state">暂无人员。</p>
                <?php else: ?>
                    <div class="admin-record-list">
                        <?php foreach ($users as $user): ?>
                            <form method="post" action="" class="admin-record">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="admin.user.update">
                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                <div class="form-grid">
                                    <label>
                                        用户名
                                        <input name="username" value="<?= $escape($user['username']) ?>" maxlength="255" required>
                                    </label>
                                    <label>
                                        国家代码
                                        <input name="country_code" value="<?= $escape($user['country_code']) ?>" maxlength="2" pattern="[A-Za-z]{2}" required>
                                    </label>
                                    <label>
                                        账号状态
                                        <select name="is_active">
                                            <option value="1" <?= (int) $user['is_active'] === 1 ? 'selected' : '' ?>>启用</option>
                                            <option value="0" <?= (int) $user['is_active'] !== 1 ? 'selected' : '' ?>>停用</option>
                                        </select>
                                    </label>
                                    <label>
                                        重设密码（留空不变）
                                        <input type="password" name="password" minlength="12" autocomplete="new-password">
                                    </label>
                                    <fieldset class="field-wide checkbox-group">
                                        <legend>角色</legend>
                                        <?php foreach ($roles as $role): ?>
                                            <label class="checkbox-field">
                                                <input
                                                    type="checkbox"
                                                    name="role_ids[]"
                                                    value="<?= (int) $role['role_id'] ?>"
                                                    <?= in_array((int) $role['role_id'], $user['role_ids'], true) ? 'checked' : '' ?>
                                                >
                                                <span><?= $escape($role['role_name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <div class="field-wide">
                                        <button class="button button-secondary" type="submit">保存人员</button>
                                    </div>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($isSection('roles')): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>角色与权限</h2>
                        <p class="muted">平台管理权限可委派给其他角色；至少保留一个启用且具备平台管理权限的账号。</p>
                    </div>
                    <span class="badge"><?= count($roles) ?> 个角色</span>
                </div>
                <details class="entry-disclosure">
                    <summary>新增角色</summary>
                    <form method="post" action="" class="admin-record">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="admin.role.create">
                        <div class="form-grid">
                            <label>
                                新角色名称
                                <input name="role_name" maxlength="50" required>
                            </label>
                            <label class="field-wide">
                                说明
                                <textarea name="description" maxlength="4000"></textarea>
                            </label>
                            <fieldset class="field-wide checkbox-group">
                                <legend>权限</legend>
                                <?php foreach ($permissions as $permission): ?>
                                    <label class="checkbox-field">
                                        <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['permission_id'] ?>">
                                        <span><?= $escape($permission['module']) ?> · <?= $escape($permission['display_name'] ?: $permission['permission_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <div class="field-wide">
                                <button class="button button-primary" type="submit">创建角色</button>
                            </div>
                        </div>
                    </form>
                </details>

                <?php foreach ($roles as $role): ?>
                    <form method="post" action="" class="admin-record">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="admin.role.update">
                        <input type="hidden" name="role_id" value="<?= (int) $role['role_id'] ?>">
                        <div class="form-grid">
                            <label>
                                角色名称
                                <input name="role_name" value="<?= $escape($role['role_name']) ?>" maxlength="50" required>
                            </label>
                            <label class="field-wide">
                                说明
                                <textarea name="description" maxlength="4000"><?= $escape($role['description']) ?></textarea>
                            </label>
                            <fieldset class="field-wide checkbox-group">
                                <legend>权限</legend>
                                <?php foreach ($permissions as $permission): ?>
                                    <label class="checkbox-field">
                                        <input
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="<?= (int) $permission['permission_id'] ?>"
                                            <?= in_array((int) $permission['permission_id'], $role['permission_ids'], true) ? 'checked' : '' ?>
                                        >
                                        <span><?= $escape($permission['module']) ?> · <?= $escape($permission['display_name'] ?: $permission['permission_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <div class="field-wide">
                                <button class="button button-secondary" type="submit">保存角色权限</button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </section>
        <?php elseif ($isSection('plugins')): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>插件管理</h2>
                        <p class="muted">停用不会删除插件数据；必需插件及仍被依赖的插件不能停用。</p>
                    </div>
                    <span class="badge"><?= count($plugins) ?> 个插件</span>
                </div>
                <?php if ($plugins === []): ?>
                    <p class="empty-state">没有发现插件清单。</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>插件</th>
                                <th>版本</th>
                                <th>依赖</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($plugins as $plugin): ?>
                                <tr>
                                    <td>
                                        <strong><?= $escape($plugin['name']) ?></strong>
                                        <div class="muted"><?= $escape($plugin['id']) ?></div>
                                    </td>
                                    <td><?= $escape($plugin['version']) ?></td>
                                    <td><?= $plugin['requires'] === [] ? '—' : $escape(implode(', ', $plugin['requires'])) ?></td>
                                    <td>
                                        <span class="<?= $plugin['enabled'] ? 'selected-label' : 'badge badge-neutral' ?>">
                                            <?= $plugin['enabled'] ? '已启用' : '已停用' ?>
                                        </span>
                                        <?php if ($plugin['required']): ?>
                                            <span class="badge">必需</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$plugin['required']): ?>
                                            <form method="post" action="">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="admin.plugin.toggle">
                                                <input type="hidden" name="plugin_id" value="<?= $escape($plugin['id']) ?>">
                                                <input type="hidden" name="enabled" value="<?= $plugin['enabled'] ? '0' : '1' ?>">
                                                <button class="button button-secondary" type="submit">
                                                    <?= $plugin['enabled'] ? '停用' : '启用' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">不可停用</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>运行平台参数</h2>
                        <p class="muted">只展示诊断所需信息，不显示数据库地址、账号、密码、密钥或服务器路径。</p>
                    </div>
                </div>
                <dl class="runtime-grid">
                    <div><dt>PHP 版本</dt><dd><?= $escape($runtime['php_version']) ?></dd></div>
                    <div><dt>PHP SAPI</dt><dd><?= $escape($runtime['php_sapi']) ?></dd></div>
                    <div><dt>应用环境</dt><dd><?= $escape($runtime['environment']) ?></dd></div>
                    <div><dt>时区</dt><dd><?= $escape($runtime['timezone']) ?></dd></div>
                    <div><dt>数据库驱动</dt><dd><?= $escape($runtime['database_driver']) ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
