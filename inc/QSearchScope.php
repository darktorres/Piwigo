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
 * A search scope applies to a single token and restricts the search to a subset of searchable fields.
 */
class QSearchScope
{
    public function __construct(
        public $id,
        public $aliases,
        public $nullable = false,
        public $is_text = true
    ) {
    }

    public function parse($token)
    {
        return ! (! $this->nullable && strlen($token->term) == 0);
    }

    public function process_char(&$ch, &$crt_token)
    {
        return false;
    }
}
