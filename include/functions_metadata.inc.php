<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * returns information from IPTC metadata, mapping is done in this function.
 */
function get_iptc_data(
    string $filename,
    array $map,
    string $array_sep = ','
): array {
    global $conf;

    $result = [];

    $imginfo = [];
    if (getimagesize($filename, $imginfo) == false) {
        return $result;
    }

    if (isset($imginfo['APP13'])) {
        $iptc = iptcparse($imginfo['APP13']);
        if (is_array($iptc)) {
            $rmap = array_flip($map);
            foreach (array_keys($rmap) as $iptc_key) {
                if (isset($iptc[$iptc_key][0])) {
                    if ($iptc_key == '2#025') {
                        $value = implode(
                            $array_sep,
                            array_map(clean_iptc_value(...), $iptc[$iptc_key])
                        );
                    } else {
                        $value = clean_iptc_value($iptc[$iptc_key][0]);
                    }

                    foreach (array_keys($map, $iptc_key) as $pwg_key) {
                        $result[$pwg_key] = $value;

                        if (! $conf['allow_html_in_metadata']) {
                            // in case the origin of the photo is unsecure (user upload), we
                            // remove HTML tags to avoid XSS (malicious execution of
                            // JavaScript)
                            $result[$pwg_key] = strip_tags($result[$pwg_key]);
                        }
                    }
                }
            }
        }
    }

    return $result;
}

/**
 * return a cleaned IPTC value.
 */
function clean_iptc_value(
    string $value
): string {
    // strip leading zeros (weird Kodak Scanner software)
    while (isset($value[0]) && $value[0] === chr(0)) {
        $value = substr($value, 1);
    }

    // remove binary nulls
    $value = str_replace(chr(0x00), ' ', $value);

    if (preg_match('/[\x80-\xff]/', $value)) {
        // apparently, Mac uses some MacRoman crap encoding. I don't know
        // how to detect it, so a plugin should do the trick.
        $value = trigger_change('clean_iptc_value', $value);
        if (($qual = qualify_utf8($value)) != 0) {// has non ascii chars
            if ($qual > 0) {
                $input_encoding = 'utf-8';
            } else {
                $input_encoding = 'iso-8859-1';
                if (function_exists('iconv') || function_exists('mb_convert_encoding')) {
                    // Using windows-1252 because it supports additional characters
                    // such as "oe" in a single character (ligature). About the
                    // difference between Windows-1252 and ISO-8859-1: the characters
                    // 0x80-0x9F will not convert correctly. But these are control
                    // characters that are almost never used.
                    $input_encoding = 'windows-1252';
                }
            }

            $value = convert_charset($value, $input_encoding, 'utf-8');
        }
    }

    return $value;
}

/**
 * returns information from EXIF metadata, mapping is done in this function.
 */
function get_exif_data(
    string $filename,
    array $map
): array {
    global $conf, $logger;

    $result = [];

    if (! function_exists('exif_read_data')) {
        die('Exif extension not available, admin should disable exif use');
    }

    getimagesize($filename, $info);

    // Check if the APP1 segment exists in the info array
    if (! isset($info['APP1']) || ! str_starts_with((string) $info['APP1'], 'Exif')) {
        return [];
    }

    // Read EXIF data
    // https://github.com/php/php-src/issues/11020
    if ($exif = @exif_read_data($filename) || $exif2 = trigger_change('format_exif_data', $exif = null, $filename, $map)) {
        $exif = empty($exif2) ? trigger_change('format_exif_data', $exif, $filename, $map) : $exif2;
        // configured fields
        foreach ($map as $key => $field) {
            if (! str_contains((string) $field, ';')) {
                if (isset($exif[$field])) {
                    $result[$key] = $exif[$field];
                }
            } else {
                $tokens = explode(';', (string) $field);
                if (isset($exif[$tokens[0]][$tokens[1]])) {
                    $result[$key] = $exif[$tokens[0]][$tokens[1]];
                }
            }
        }

        // GPS data
        $gps_exif = array_intersect_key($exif, array_flip(['GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude']));
        if (count($gps_exif) == 4 && (is_array($gps_exif['GPSLatitude']) && in_array($gps_exif['GPSLatitudeRef'], ['S', 'N']) && is_array($gps_exif['GPSLongitude']) && in_array($gps_exif['GPSLongitudeRef'], ['W', 'E']))) {
            $latitude = parse_exif_gps_data($gps_exif['GPSLatitude'], $gps_exif['GPSLatitudeRef']);
            $longitude = parse_exif_gps_data($gps_exif['GPSLongitude'], $gps_exif['GPSLongitudeRef']);
            if ($latitude >= -90.0 && $latitude <= 90.0 && $longitude >= -180.0 && $longitude <= 180.0) {
                $result['latitude'] = $latitude;
                $result['longitude'] = $longitude;
            } else {
                $logger->info('[' . __FUNCTION__ . '][filename=' . $filename . '] invalid GPS coordinates, latitude=' . $latitude . ' longitude=' . $longitude);
            }
        }
    }

    if (! $conf['allow_html_in_metadata']) {
        foreach ($result as $key => $value) {
            // in case the origin of the photo is unsecure (user upload), we remove
            // HTML tags to avoid XSS (malicious execution of JavaScript)
            if (is_array($value)) {
                array_walk_recursive($value, 'strip_html_in_metadata');
            } else {
                $result[$key] = strip_tags((string) $value);
            }
        }
    }

    return $result;
}

function strip_html_in_metadata(&$v, $k): void
{
    $v = strip_tags((string) $v);
}

/**
 * Converts EXIF GPS format to a float value.
 * @since 2.6
 *
 * @param string[] $raw eg:
 *    - 41/1
 *    - 54/1
 *    - 9843/500
 * @param string $ref 'S', 'N', 'E', 'W'. eg: 'N'
 * @return float eg: 41.905468
 */
function parse_exif_gps_data(
    array $raw,
    string $ref
): float {
    foreach ($raw as &$i) {
        $i = explode('/', $i);
        $i = $i[1] == 0 ? 0 : $i[0] / $i[1];
    }

    unset($i);

    $v = $raw[0] + $raw[1] / 60 + $raw[2] / 3600;

    $ref = strtoupper($ref);
    if ($ref === 'S' || $ref === 'W') {
        $v = -$v;
    }

    return $v;
}
