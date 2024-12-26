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
 * Base class for web service response encoder.
 */
abstract class PwgResponseEncoder
{
    /** encodes the web service response to the appropriate output format
     * @param array|bool|string|PwgError|null $response the unencoded result of a service method call
     */
    abstract public function encodeResponse(
        array|bool|string|PwgError|null $response
    );

    /** default "Content-Type" http header for this kind of response format
     */
    abstract public function getContentType();

    /**
     * returns true if the parameter is a 'struct' (php array type whose keys are
     * NOT consecutive integers starting with 0)
     */
    public static function is_struct(
        array &$data
    ): bool {
        # string keys, unordered, non-incremental keys, ... - whatever, make object
        return is_array($data) && range(0, count($data) - 1) !== array_keys($data);
    }

    /**
     * removes all XML formatting from $response (named array, named structs, etc.)
     * usually called by every response encoder, except rest XML.
     */
    public static function flattenResponse(
        array|bool|null &$value
    ): void {
        self::flatten($value);
    }

    private static function flatten(
        PwgNamedArray|PwgNamedStruct|array|string|int|float|bool|null &$value
    ): void {
        if (is_object($value)) {
            $class = strtolower($value::class);
            if ($class === 'pwgnamedarray') {
                $value = $value->_content;
            }

            if ($class === 'pwgnamedstruct') {
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

        foreach ($value as &$v) {
            self::flatten($v);
        }
    }
}
