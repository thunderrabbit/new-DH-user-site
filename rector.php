<?php

declare(strict_types=1);

// Run by ~/work/rob/standards-mcp/run-rector.sh: a dry run by default,
// --apply --only=<rule class> to land one rule per commit.
// Paths match phpcs.xml; templates/*.tpl.php are out of scope there too.

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/classes',
        __DIR__ . '/wwwroot',
        __DIR__ . '/prepend.php',
        __DIR__ . '/Tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/Tests/_output',
        __DIR__ . '/Tests/Support/_generated',
        // The old per-site config, gitignored; Config\Loader still reads one.
        __DIR__ . '/classes/Config/Config.php',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::TYPE_DECLARATION,
    ]);
