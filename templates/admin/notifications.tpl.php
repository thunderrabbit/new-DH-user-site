<?php
/**
 * Due notifications for admins (mg #858). A message and a link are whatever
 * a migration wrote: both are escaped, and a link is drawn only when it is a
 * path on this site.
 *
 * The "Remind me" form adds one for a later day; the upcoming list shows what
 * is waiting. An admin typed those, and they are escaped the same.
 *
 * Reads: $due and $upcoming (lists of Admin\Notification), $message (the last
 * press's result), $form (date, message, link: empty, or a refused form's).
 */
?>
<div class="PagePanel">
    <h2>Notifications</h2>
    <p><a href="/admin/">&larr; Admin</a></p>
    <?php if ($message !== ''): ?>
        <p><strong><?= htmlspecialchars($message) ?></strong></p>
    <?php endif; ?>
    <?php if ($due === []): ?>
        <p>Nothing is due.</p>
    <?php endif; ?>
    <p><small>Pending migrations are not listed here and can't be completed: the only way to clear one is to
        <a href="/admin/migrate_tables.php">apply it</a>.</small></p>
</div>

<?php foreach ($due as $notification): ?>
<div class="PagePanel">
    <p><?= htmlspecialchars($notification->message) ?></p>
    <p><small>Due since <?= htmlspecialchars($notification->show_from) ?> UTC</small>
        <?php if ($notification->link !== null && \Admin\Notifications::isSitePath($notification->link)): ?>
            &middot; <a href="<?= htmlspecialchars($notification->link) ?>"><?= htmlspecialchars($notification->link) ?></a>
        <?php endif; ?></p>
    <form action="/admin/notifications/" method="POST">
        <?= $csrf->field() ?>
        <input type="hidden" name="do" value="complete">
        <input type="hidden" name="notification_id" value="<?= (int) $notification->notification_id ?>">
        <button type="submit">Completed</button>
    </form>
</div>
<?php endforeach; ?>

<div class="PagePanel">
    <h2>Remind me</h2>
    <form action="/admin/notifications/" method="POST">
        <?= $csrf->field() ?>
        <input type="hidden" name="do" value="remind">
        <p><label>Show from
            <input type="date" name="date" value="<?= htmlspecialchars($form['date']) ?>" required></label> UTC</p>
        <p><label>Message<br>
            <textarea name="message" rows="3" cols="50" maxlength="<?= \Admin\Notifications::MESSAGE_MAX ?>"
                required><?= htmlspecialchars($form['message']) ?></textarea></label></p>
        <p><label>Link (optional)
            <input type="text" name="link" size="30" maxlength="<?= \Admin\Notifications::LINK_MAX ?>"
                placeholder="/profile/" value="<?= htmlspecialchars($form['link']) ?>"></label><br>
            <small>A path on this site, starting with /</small></p>
        <button type="submit">Remind me</button>
    </form>
</div>

<div class="PagePanel">
    <h2>Upcoming</h2>
    <?php if ($upcoming === []): ?>
        <p>Nothing is waiting.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($upcoming as $notification): ?>
            <li><?= htmlspecialchars(substr($notification->show_from, 0, 10)) ?> UTC:
                <?= htmlspecialchars($notification->message) ?>
                <?php if ($notification->link !== null && \Admin\Notifications::isSitePath($notification->link)): ?>
                    &middot; <a href="<?= htmlspecialchars($notification->link) ?>"><?= htmlspecialchars($notification->link) ?></a>
                <?php endif; ?></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
