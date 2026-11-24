<div class="PagePanel">
    What's up <?= $username ?>? <span class="PhpVersion">Running PHP <?= PHP_VERSION ?></span><br />
</div>
<h1>Welcome to the <?= htmlspecialchars($site_title) ?> Admin Dashboard</h1>
<p>Registration is <strong><?= $allow_registration ? 'open' : 'closed' ?></strong> to new users.
   Change <code>$allow_registration</code> in <code>classes/Config/Config.php</code>.</p>
<p>Password hashing: <?= htmlspecialchars($password_hashing ?? 'not checked') ?>.</p>
<p><a href="/admin/notifications/">Notifications</a>: <?= (int) ($due_notifications ?? 0) ?> due.</p>
<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3>";
        echo "<a href='/admin/migrate_tables.php'>Click here to migrate tables</a>";
    }
?>

<div class="fix">
    <p>Sentimental version: <?= $site_version ?></p>
</div>
