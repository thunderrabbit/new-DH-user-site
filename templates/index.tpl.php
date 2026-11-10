<div class="PagePanel">
    Welcome back, <?= $username ?>!
</div>

<h1><?= htmlspecialchars($site_title) ?></h1>

<div class="PagePanel">
    <h2>Quick Actions</h2>
    <ul>
        <?php if (!empty($is_admin)): ?>
            <li><a href="/admin/">Admin Dashboard</a></li>
        <?php endif; ?>
        <li><a href="/profile/">Profile Settings</a></li>
    </ul>
</div>

<div class="PagePanel">
    <h2>Site Status</h2>
    <p>Everything is running smoothly.</p>
    <?php if (isset($site_version)): ?>
        <p><small>Version: <?= $site_version ?></small></p>
    <?php endif; ?>
</div>
