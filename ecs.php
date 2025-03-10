<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\RandomApiMigrationFixer;
use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\StringNotation\NoTrailingWhitespaceInStringFixer;
use PhpCsFixer\Fixer\Whitespace\LineEndingFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return ECSConfig::configure()
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
        DeclareStrictTypesFixer::class,
        GeneralPhpdocAnnotationRemoveFixer::class,
        LineLengthFixer::class,
    ])
    ->withRootFiles()
    ->withPreparedSets(
        cleanCode: true,
        common: true,
        psr12: true,
        symplify: true
    )
    ->withRules([
        DeclareStrictTypesFixer::class,
        LineEndingFixer::class,
        NoTrailingWhitespaceInStringFixer::class,
        RandomApiMigrationFixer::class,
        SingleLineEmptyBodyFixer::class,
    ])
    ->withSpacing(indentation: Option::INDENTATION_SPACES, lineEnding: "\n")
;
