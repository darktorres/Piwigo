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
 * Base class for web service response encoder.
 */
abstract class ResponseEncoder
{
    /** encodes the web service response to the appropriate output format
     */
    abstract public function encodeResponse(
        mixed $response
    );

    /** default "Content-Type" http header for this kind of response format
     */
    abstract public function getContentType();

    /**
     * returns true if the parameter is a 'struct' (php array type whose keys are
     * NOT consecutive integers starting with 0)
     */
    public static function is_struct(
        &$data
    ) {
        # string keys, unordered, non-incremental keys, .. - whatever, make object
        return is_array($data) && range(
            0,
            count($data) - 1
        ) !== array_keys(
            $data
        );
    }

    /**
     * removes all XML formatting from $response (named array, named structs, etc)
     * usually called by every response encoder, except rest xml.
     */
    public static function flattenResponse(
        &$value
    ) {
        self::flatten($value);
    }

    private static function flatten(&$value)
    {
        if (is_object($value)) {
            $class = strtolower(@$value::class);
            if ($class === 'namedarray') {
                $value = $value->_content;
            }

            if ($class === 'namedstruct') {
                $value = $value->_content;
            }
        }

        if (! is_array($value)) {
            return;
        }

        if (self::is_struct($value) && isset($value[WS_XML_ATTRIBUTES])) {
            $value = array_merge($value, $value[WS_XML_ATTRIBUTES]);
            unset($value[WS_XML_ATTRIBUTES]);
        }

        foreach ($value as $key => &$v) {
            self::flatten($v);
        }
    }
}
