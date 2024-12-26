<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Simple wrapper around a "struct" (php array whose keys are not consecutive
 * integers starting at 0).
 * Provides naming clues for XML output (what is XML attributes and what is element)
 */
class PwgNamedStruct
{
    /*private*/
    public array $_xmlAttributes;

    /**
     * Constructs a named struct (usually returned by web service function
     * implementation)
     * @param array $_content - the actual content (php array)
     * @param ?array $xmlAttributes - containing XML element name
     * @param ?array $xmlElements - name of the keys in $content that will be
     *    encoded as XML attributes (if null - automatically prefer XML attributes
     *    whenever possible)
     */
    public function __construct(
        public array $_content,
        ?array $xmlAttributes = null,
        ?array $xmlElements = null
    ) {
        if (isset($xmlAttributes)) {
            $this->_xmlAttributes = array_flip($xmlAttributes);
        } else {
            $this->_xmlAttributes = [];
            foreach ($this->_content as $key => $value) {
                if (($key !== 0 && ($key !== '' && $key !== '0') && (is_scalar($value) || $value === null)) && ($xmlElements === null || $xmlElements === [] || ! in_array($key, $xmlElements))) {
                    $this->_xmlAttributes[$key] = 1;
                }
            }
        }
    }
}
