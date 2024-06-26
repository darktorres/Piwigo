<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__,
    ])
    ->withSkip([
        __DIR__ . '/_data',
        __DIR__ . '/vendor',
    ])
    ->withRootFiles()
    // add sets - group of rules
    ->withPreparedSets(
        cleanCode: true,
        common: true,
        psr12: true,
        symplify: true
    );