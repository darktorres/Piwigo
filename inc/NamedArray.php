<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/ws_core.inc.php';

/**
 * Simple wrapper around an array (keys are consecutive integers starting at 0).
 * Provides naming clues for xml output (xml attributes vs. xml child elements?)
 * Usually returned by web service function implementation.
 */
class NamedArray
{
    /*private*/
    public $_xmlAttributes;

    /**
     * Constructs a named array
     * @param arr $_content (keys must be consecutive integers starting at 0)
     * @param string $_itemName xml element name for values of arr (e.g. image)
     * @param array $xmlAttributes of sub-item attributes that will be encoded as
     *      xml attributes instead of xml child elements
     */
    public function __construct(
        public $_content,
        public $_itemName,
        $xmlAttributes = []
    ) {
        $this->_xmlAttributes = array_flip($xmlAttributes);
    }
}
