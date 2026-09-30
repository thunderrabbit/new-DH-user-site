<?php
/**
 * @var list<\Auth\Device> $devices
 * @var string $error_message
 * @var string $success_message
 */
$others = count(array_filter($devices, fn(\Auth\Device $d) => !$d->is_current));
?>
<div class="PagePanel">
    <div class="head"><h5 class="iUser">Signed-in Devices</h5></div>

    <?php if (!empty($success_message)): ?>
        <div class="success-message" style="color: green; padding: 10px; margin: 10px 0; border: 1px solid green; background-color: #e8f5e8;">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="error-message" style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red; background-color: #ffe8e8;">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <p>Each browser you log in on stays signed in on its own. Sign out any you don't recognise or no longer use.</p>

    <ul class="DeviceList">
        <?php foreach ($devices as $device): ?>
            <li class="Device<?= $device->is_current ? ' current' : '' ?>">
                <div class="DeviceName">
                    <?= htmlspecialchars($device->label()) ?>
                    <?php if ($device->is_current): ?>
                        <span class="DeviceBadge">This device</span>
                    <?php endif; ?>
                </div>
                <div class="DeviceMeta">
                    Last used <?= htmlspecialchars($device->last_access !== '' ? $device->last_access : 'unknown') ?>
                    · signed in <?= htmlspecialchars($device->created_at) ?>
                    <?php if ($device->ip_address !== ''): ?>
                        from <?= htmlspecialchars($device->ip_address) ?>
                    <?php endif; ?>
                    · expires <?= htmlspecialchars($device->expires_at) ?>
                </div>
                <?php /* No button on this device: /logout/ signs out every device, not just this one. */ ?>
                <?php if (!$device->is_current): ?>
                    <form action="/profile/devices/" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="revoke" />
                        <input type="hidden" name="cookie_id" value="<?= (int) $device->cookie_id ?>" />
                        <input type="submit" value="Sign out" class="greyishBtn" />
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($others > 0): ?>
        <form action="/profile/devices/" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="revoke_others" />
            <input type="submit" value="Sign out every other device" class="greyishBtn" />
        </form>
    <?php endif; ?>
</div>
