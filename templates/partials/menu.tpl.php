<?php
/**
 * The one menu, included by every layout.
 *
 * Reads two layout vars, both optional:
 *   $username  logged-in user's name; '' or unset means logged out
 *   $is_admin  true shows the Admin link
 */
$menu_logged_in = isset($username) && is_string($username) && $username !== '';
$menu_is_admin = $menu_logged_in && ($is_admin ?? false) === true;
?>
<div class="NavBar">
    <a href="/">Home</a> |
    <?php if ($menu_logged_in): ?>
        <div class="dropdown">
            <a href="/profile/">Profile ▾</a>
            <div class="dropdown-menu">
                <a href="/logout/">Logout</a>
            </div>
        </div>
        <?php if ($menu_is_admin): ?>
            | <a href="/admin/">Admin</a>
        <?php endif; ?>
    <?php else: ?>
        <a href="/login/">Log in</a>
    <?php endif; ?>
</div>
