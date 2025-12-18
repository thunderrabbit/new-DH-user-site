<h1>MarbleTrack3 Table Migration Dashboard</h1>
<script>
    function applyMigration(migration, buttonElement) {
        // AJAX request to apply the migration
        fetch('/admin/apply_migration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ migration: migration })
        })
        .then(response => {
            if (response.ok) {
                // Remove the list item from the DOM
                buttonElement.parentElement.remove();
            } else {
                alert("Failed to apply migration. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error applying migration:", error);
            alert("An error occurred while applying the migration.");
        });
    }
</script>
<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3><ul>";
        foreach ($pending_migrations as $migration) {
            echo "<li>$migration <button onclick=\"applyMigration('$migration', this)\">Apply</button></li>";
        }
        echo "</ul>";
    }
?>

<div class="PagePanel">
    <a href="/admin/">admin</a> <br />
</div>
