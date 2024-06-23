<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__,
    ])
    ->withSkip([
        __DIR__ . '/vendor',
    ])
    ->withRootFiles()
    ->withPhpSets()
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        deadCode: false,
        earlyReturn: false,
        instanceOf: false,
        naming: false,
        privatization: false,
        typeDeclarations: false
    );
