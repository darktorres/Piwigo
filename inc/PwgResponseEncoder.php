<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc;

/**
 * Base class for web service response encoder.
 */
abstract class PwgResponseEncoder
{
    /**
     * encodes the web service response to the appropriate output format
     * @param mixed $response the unencoded result of a service method call
     */
    abstract public function encodeResponse($response);

    /**
     * default "Content-Type" http header for this kind of response format
     */
    abstract public function getContentType();

    /**
     * returns true if the parameter is a 'struct' (php array type whose keys are
     * NOT consecutive integers starting with 0)
     */
    public static function is_struct(&$data)
    {
        if (is_array($data)) {
            if (range(0, count($data) - 1) !== array_keys($data)) { # string keys, unordered, non-incremental keys, .. - whatever, make object
                return true;
            }
        }

        return false;
    }

    /**
     * removes all XML formatting from $response (named array, named structs, etc)
     * usually called by every response encoder, except rest xml.
     */
    public static function flattenResponse(&$value)
    {
        self::flatten($value);
    }

    private static function flatten(&$value)
    {
        if (is_object($value)) {
            $class = strtolower(@get_class($value));
            if ($class == 'piwigo\inc\pwgnamedarray') {
                $value = $value->_content;
            }

            if ($class == 'piwigo\inc\pwgnamedstruct') {
                $value = $value->_content;
            }
        }

        if (! is_array($value)) {
            return;
        }

        if (self::is_struct($value)) {
            if (isset($value[WS_XML_ATTRIBUTES])) {
                $value = array_merge($value, $value[WS_XML_ATTRIBUTES]);
                unset($value[WS_XML_ATTRIBUTES]);
            }
        }

        foreach ($value as $key => &$v) {
            self::flatten($v);
        }
    }
}
