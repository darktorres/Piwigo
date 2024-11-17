<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\RandomApiMigrationFixer;
use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\NoTrailingWhitespaceInStringFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPhpCsFixerSets(perCS: true, perCSRisky: true)
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
        LineLengthFixer::class,
    ])
    ->withRootFiles()
    ->withPhpCsFixerSets(perCS: true, php83Migration: true)
    ->withPreparedSets(
        cleanCode: true,
        common: true,
        psr12: true,
        symplify: true,
    )
    ->withRules([
        DeclareStrictTypesFixer::class,
        NoTrailingWhitespaceInStringFixer::class,
        SingleLineEmptyBodyFixer::class,
        RandomApiMigrationFixer::class,
    ])
;
