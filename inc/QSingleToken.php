<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/functions_search.inc.php';

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
    public $is_single = true; /* the actual word/phrase string*/

    public $variants = [];

    public $scope_data;

    public $idx;

    public function __construct(
        public $term,
        public $modifier,
        public $scope
    ) {
    }

    #[\Override]
    public function __toString(): string
    {
        $s = '';
        if ($this->scope !== null) {
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
