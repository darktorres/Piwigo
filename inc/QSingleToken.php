<?php

declare(strict_types=1);

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Analyzes and splits the quick/query search query $q into tokens.
 * q='john bill' => 2 tokens 'john' 'bill'
 * Special characters for MySql full text search (+,<,>,~) appear in the token modifiers.
 * The query can contain a phrase: 'Pierre "New York"' will return 'pierre' qnd 'new york'.
 *
 * @param string $term
 */

/** Represents a single word or quoted phrase to be searched.*/
class QSingleToken implements \Stringable
{
    public bool $is_single = true; /* the actual word/phrase string*/

    public array $variants = [];

    public array $scope_data;

    public int $idx;

    public function __construct(
        public string $term,
        public int $modifier,
        public ?QSearchScope $scope
    ) {}

    #[\Override]
    public function __toString(): string
    {
        $s = '';
        if (isset($this->scope)) {
            $s .= $this->scope->id . ':';
        }

        if (($this->modifier & QST_WILDCARD_BEGIN) !== 0) {
            $s .= '*';
        }

        if (($this->modifier & QST_QUOTED) !== 0) {
            $s .= '"';
        }

        $s .= $this->term;
        if (($this->modifier & QST_QUOTED) !== 0) {
            $s .= '"';
        }

        if (($this->modifier & QST_WILDCARD_END) !== 0) {
            $s .= '*';
        }

        return $s;
    }
}
