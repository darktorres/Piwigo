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
 * Simple wrapper around a "struct" (php array whose keys are not consecutive
 * integers starting at 0). Provides naming clues for xml output (what is xml
 * attributes and what is element)
 */
class NamedStruct
{
    /*private*/
    public $_xmlAttributes;

    /**
     * Constructs a named struct (usually returned by web service function
     * implementation)
     * @param name $xmlAttributes string - containing xml element name
     * @param content $xmlElements array - the actual content (php array)
     * @param xmlAttributes array - name of the keys in $content that will be
     *    encoded as xml attributes (if null - automatically prefer xml attributes
     *    whenever possible)
     */
    public function __construct(
        public $_content,
        $xmlAttributes = null,
        $xmlElements = null
    ) {
        if (isset($xmlAttributes)) {
            $this->_xmlAttributes = array_flip($xmlAttributes);
        } else {
            $this->_xmlAttributes = [];
            foreach ($this->_content as $key => $value) {
                if ((! empty($key) && (is_scalar($value) || $value === null)) && (empty($xmlElements) || ! in_array(
                    $key,
                    $xmlElements
                ))) {
                    $this->_xmlAttributes[$key] = 1;
                }
            }
        }
    }
}
