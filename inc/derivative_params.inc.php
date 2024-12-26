<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Formats a size name into a 2-char identifier usable in filename.
 *
 * @param string $t one of IMG_*
 */
function derivative_to_url(
    string $t
): string {
    return substr($t, 0, 2);
}

/**
 * Formats a size array into an identifier usable in filename.
 *
 * @param int[] $s
 */
function size_to_url(
    array $s
): int|string {
    if ($s[0] == $s[1]) {
        return $s[0];
    }

    return $s[0] . 'x' . $s[1];
}

/**
 * @param int[] $s1
 * @param int[] $s2
 */
function size_equals(
    array $s1,
    array $s2
): bool {
    return $s1[0] == $s2[0] && $s1[1] == $s2[1];
}

/**
 * Converts a char a-z into a float.
 */
function char_to_fraction(
    string $c
): float {
    return (ord($c) - ord('a')) / 25;
}

/**
 * Converts a float into a char a-z.
 */
function fraction_to_char(
    float|int $f
): string {
    return chr((int) (ord('a') + round($f * 25)));
}
