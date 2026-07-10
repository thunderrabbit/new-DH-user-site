<div class="PagePanel">
    What's up <?= $username ?>? <br />
</div>
<h1>Welcome to the MarbleTrack3 Admin Dashboard</h1>
<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3>";
        echo "<a href='/admin/migrate_tables.php'>Click here to migrate tables</a>";
    }
?>

<div class="fix">
    <p>Sentimental version: <?= $site_version ?></p>
</div>
