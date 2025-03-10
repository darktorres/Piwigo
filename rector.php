<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php73\Rector\String_\SensitiveHereNowDocRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/admin',
        __DIR__ . '/inc',
        __DIR__ . '/install',
        __DIR__ . '/language',
        __DIR__ . '/plugins',
        __DIR__ . '/themes',
    ])
    ->withSkip([
        __DIR__ . '/themes/bootstrap_darkroom/node_modules',
        // EncapsedStringsToSprintfRector::class,
        // RemoveExtraParametersRector::class,
        // SensitiveHereNowDocRector::class,
        // NullToStrictStringFuncCallArgRector::class,
    ])
    ->withRules([
        NewlineAfterStatementRector::class,
    ])
    ->withRootFiles()
    // ->withPhpSets()
    // ->withPreparedSets(
    //     codeQuality: true,
    //     codingStyle: true,
    //     deadCode: false,
    //     earlyReturn: false,
    //     instanceOf: false,
    //     naming: false,
    //     privatization: false,
    //     typeDeclarations: true
    // )
;
