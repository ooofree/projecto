<?php
$content = '';
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">账户</p>
        <h1>个人设置</h1>
        <p class="muted">当前账号：<?= $escape($currentUsername) ?>。项目时间统一以 UTC 存储；页面按你的当地时区显示。</p>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>显示时区</h2>
            <p class="muted">当前生效时区：<?= $escape($effectiveTimezone) ?>，当前时间：<?= $escape($currentLocalTime) ?>。未选择时使用项目默认时区（<?= $escape($defaultTimezone) ?>）。</p>
        </div>
    </div>
    <form method="post" action="" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="profile.save">
        <label>
            时区
            <select name="timezone">
                <option value="" <?= $userTimezone === '' ? 'selected' : '' ?>>使用项目默认（<?= $escape($defaultTimezone) ?>）</option>
                <?php foreach ($timezones as $zone): ?>
                    <option value="<?= $escape($zone) ?>" <?= $userTimezone === $zone ? 'selected' : '' ?>><?= $escape($zone) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="field-wide">
            <button class="button button-primary" type="submit">保存个人设置</button>
        </div>
    </form>
</section>

<section class="panel" id="password">
    <div class="panel-heading">
        <div>
            <h2>更改密码</h2>
            <p class="muted">验证当前密码后设置新密码，新密码至少 12 个字符。修改成功后，下次登录请使用新密码。</p>
        </div>
    </div>
    <form method="post" action="" class="form-grid profile-password-form">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="profile.password">
        <label>
            当前密码
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label>
            新密码（至少 12 位）
            <input type="password" name="new_password" minlength="12" autocomplete="new-password" required>
        </label>
        <label>
            确认新密码
            <input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required>
        </label>
        <div class="field-wide">
            <button class="button button-primary" type="submit">更新密码</button>
        </div>
    </form>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__) . '/views/layout.php'; ?>
