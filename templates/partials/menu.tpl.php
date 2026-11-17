<?php
/**
 * The one menu, included by every layout.
 *
 * Reads two layout vars, both optional:
 *   $username  logged-in user's name; '' or unset means logged out
 *   $is_admin  true shows the Admin link
 * and $admin_count, which every Template sets: above 0 draws the count inside
 * an admin's Admin link (mg #858).
 */
$menu_logged_in = isset($username) && is_string($username) && $username !== '';
$menu_is_admin = $menu_logged_in && ($is_admin ?? false) === true;
$menu_admin_count = (int) ($admin_count ?? 0);
?>
<div class="NavBar">
    <a href="/">Home</a> |
    <?php if ($menu_logged_in): ?>
        <div class="dropdown">
            <a href="/profile/">Profile ▾</a>
            <div class="dropdown-menu">
                <a href="/profile/devices/">Devices</a>
                <a href="/logout/">Logout</a>
            </div>
        </div>
        <?php if ($menu_is_admin): ?>
            <?php if ($menu_admin_count > 0): ?>
                <?php // Inside the link, so the chip is clicked too; the label names the count for screen readers. ?>
                | <a href="/admin/" aria-label="Admin, <?= $menu_admin_count ?> waiting for an admin">Admin
                    <span class="AdminCount" aria-hidden="true"><?= $menu_admin_count ?></span></a>
            <?php else: ?>
                | <a href="/admin/">Admin</a>
            <?php endif; ?>
            <span class="PhpVersion">PHP <?= PHP_VERSION ?></span>
        <?php endif; ?>
    <?php else: ?>
        <a href="/login/">Log in</a>
    <?php endif; ?>
</div>
