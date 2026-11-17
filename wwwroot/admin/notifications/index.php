<?php

/**
 * Due notifications for admins (mg #858): everything the Admin link counts
 * other than pending migrations, each with a Completed button. A second
 * press on the same one (a stale tab, a double click) does nothing and says
 * so. Migrations are never listed here: applying them is the only way out.
 *
 * Below them, the "Remind me" form adds a notification for a later day, and
 * the upcoming ones are listed so a typo shows before its day comes. A
 * refused form is drawn again with what was typed.
 */

declare(strict_types=1);

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

/**
 * Set up by prepend.php.
 *
 * @var \Config\Config $config
 * @var \PDO $mla_database
 * @var \Auth\IsLoggedIn $is_logged_in
 * @var \Security\CSRFProtectaroo $csrfProtect
 * @var int $admin_count
 */

if (!$is_logged_in->isLoggedIn() || !$is_logged_in->isAdmin()) {
    header('Location: /login/');
    exit;
}
$notifications = new \Admin\Notifications($mla_database);

/** Words for the result of the last press, keyed by ?done=. */
$said = [
    'completed' => 'Completed.',
    'gone' => 'That one was already completed.',
    'reminded' => \Admin\RemindResult::Added->says(),
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'complete') {
    $id = $_POST['notification_id'] ?? '';
    $id = is_string($id) && ctype_digit($id) ? (int) $id : 0;
    $done = $notifications->complete($id, $is_logged_in->loggedInID()) ? 'completed' : 'gone';
    header('Location: /admin/notifications/?done=' . $done, true, 303);
    exit;
}

/** What the "Remind me" form shows: empty, or what a refused one held. */
$form = ['date' => '', 'message' => '', 'link' => ''];
$refused = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['do'] ?? '') === 'remind') {
    foreach (array_keys($form) as $field) {
        $typed = $_POST[$field] ?? '';
        $form[$field] = is_string($typed) ? $typed : '';
    }
    $result = $notifications->remind($form['date'], $form['message'], $form['link']);
    if ($result === \Admin\RemindResult::Added) {
        header('Location: /admin/notifications/?done=reminded', true, 303);
        exit;
    }
    $refused = $result->says();
}

$done = $_GET['done'] ?? '';

$page = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
$page->setTemplate("admin/notifications.tpl.php");
$page->set("due", $notifications->listDue());
$page->set("upcoming", $notifications->listUpcoming());
$page->set("form", $form);
$page->set("message", $refused !== '' ? $refused : (is_string($done) ? ($said[$done] ?? '') : ''));
$inner = $page->grabTheGoods();

$layout = new \View\Template(config: $config, csrf: $csrfProtect, admin_count: $admin_count);
$layout->setTemplate("layout/base.tpl.php");
// @phpstan-ignore nullCoalesce.property (an older Config.php may not declare it)
$layout->set("page_title", ($config->site_title ?? 'Site') . " Notifications");
$layout->set("username", $is_logged_in->getLoggedInUsername());
$layout->set("is_admin", true);
$layout->set("page_content", $inner);
$layout->echoToScreen();
