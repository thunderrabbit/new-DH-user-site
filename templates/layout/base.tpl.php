<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content=""/>
    <title><?= htmlspecialchars($page_title ?? 'Site') ?></title>
    <link rel="stylesheet" href="/css/<?= \Version\Site::SEMVER ?>/theme.css">
    <link rel="stylesheet" href="/css/<?= \Version\Site::SEMVER ?>/styles.css">
    <link rel="stylesheet" href="/css/<?= \Version\Site::SEMVER ?>/menu.css">
</head>
<body>
    <?php include __DIR__ . '/../partials/menu.tpl.php'; ?>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
</body>
</html>

