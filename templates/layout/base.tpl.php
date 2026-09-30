<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content=""/>
    <title><?= htmlspecialchars($page_title ?? 'Site') ?></title>
    <link rel="stylesheet" href="/css/theme.css?v=2">
    <link rel="stylesheet" href="/css/styles.css?v=3">
    <link rel="stylesheet" href="/css/menu.css?v=2">
</head>
<body>
    <?php include __DIR__ . '/../partials/menu.tpl.php'; ?>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
</body>
</html>

