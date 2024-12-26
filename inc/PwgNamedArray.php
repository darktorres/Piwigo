<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Simple wrapper around an array (keys are consecutive integers starting at 0).
 * Provides naming clues for XML output (XML attributes vs. XML child elements?)
 * Usually returned by web service function implementation.
 */
class PwgNamedArray
{
    /*private*/
    public array $_xmlAttributes;

    /**
     * Constructs a named array
     * @param array $_content (keys must be consecutive integers starting at 0)
     * @param string $_itemName xml element name for values of arr (e.g. image)
     * @param array $xmlAttributes of sub-item attributes that will be encoded as
     *      xml attributes instead of XML child elements
     */
    public function __construct(
        public array $_content,
        public string $_itemName,
        array $xmlAttributes = []
    ) {
        $this->_xmlAttributes = array_flip($xmlAttributes);
    }
}
