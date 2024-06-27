<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__,
    ])
    ->withSkip([
        __DIR__ . '/_data',
        __DIR__ . '/node_modules',
        __DIR__ . '/tests',
        __DIR__ . '/vendor',
    ])
    ->withRootFiles()
    // ->withPhpSets()
    ->withPreparedSets(
        codeQuality: false,
        codingStyle: false,
        deadCode: false,
        earlyReturn: false,
        instanceOf: false,
        naming: false,
        privatization: false,
        typeDeclarations: false
    );
