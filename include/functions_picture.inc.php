<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Returns slideshow default params.
 * - period
 * - repeat
 * - play
 */
function get_default_slideshow_params(): array
{
    global $conf;

    return [
        'period' => $conf['slideshow_period'],
        'repeat' => $conf['slideshow_repeat'],
        'play' => true,
    ];
}

/**
 * Checks and corrects slideshow params
 */
function correct_slideshow_params(
    array $params = []
): array {
    global $conf;

    if ($params['period'] < $conf['slideshow_period_min']) {
        $params['period'] = $conf['slideshow_period_min'];
    } elseif ($params['period'] > $conf['slideshow_period_max']) {
        $params['period'] = $conf['slideshow_period_max'];
    }

    return $params;
}

/**
 * Decodes slideshow string params into array
 */
function decode_slideshow_params(
    string $encode_params = null
): array {
    global $conf;

    $result = get_default_slideshow_params();

    if (is_numeric($encode_params)) {
        $result['period'] = $encode_params;
    } else {
        $matches = [];
        if (preg_match_all('/([a-z]+)-(\d+)/', (string) $encode_params, $matches)) {
            $matchcount = count($matches[1]);
            for ($i = 0; $i < $matchcount; $i++) {
                $result[$matches[1][$i]] = $matches[2][$i];
            }
        }

        if (preg_match_all('/([a-z]+)-(true|false)/', (string) $encode_params, $matches)) {
            $matchcount = count($matches[1]);
            for ($i = 0; $i < $matchcount; $i++) {
                $result[$matches[1][$i]] = get_boolean($matches[2][$i]);
            }
        }
    }

    return correct_slideshow_params($result);
}

/**
 * Encodes slideshow array params into a string
 */
function encode_slideshow_params(
    array $decode_params = []
): string {
    global $conf;

    $params = array_diff_assoc(correct_slideshow_params($decode_params), get_default_slideshow_params());
    $result = '';

    foreach ($params as $name => $value) {
        // boolean_to_string return $value, if it's not a bool
        $result .= '+' . $name . '-' . boolean_to_string($value);
    }

    return $result;
}

/**
 * Increase the number of visits for a given photo.
 *
 * Code moved from picture.php to be used by both the API and picture.php
 *
 * @since 14
 * @param int $image_id
 */
function increase_image_visit_counter($image_id): void
{
    // avoiding auto update of "lastmodified" field
    $query = "UPDATE images SET hit = hit + 1, lastmodified = lastmodified WHERE id = {$image_id};";
    pwg_query($query);
}
