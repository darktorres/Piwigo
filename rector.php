<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__,
    ])
    ->withSkip([
        __DIR__ . '/_data',
        __DIR__ . '/galleries',
        __DIR__ . '/node_modules',
        __DIR__ . '/tests',
        __DIR__ . '/themes/bootstrap_darkroom/node_modules',
        __DIR__ . '/vendor',
        EncapsedStringsToSprintfRector::class,
        RemoveExtraParametersRector::class,
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
        typeDeclarations: true,
    )
;
