<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\RandomApiMigrationFixer;
use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\NoTrailingWhitespaceInStringFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
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
    // add sets - group of rules
    ->withPreparedSets(
        cleanCode: true,
        common: true,
        psr12: true,
        symplify: true
    )
    // ->withRules([
    //     DeclareStrictTypesFixer::class,
    //     NoTrailingWhitespaceInStringFixer::class,
    //     SingleLineEmptyBodyFixer::class,
    //     RandomApiMigrationFixer::class,
    // ])
;
