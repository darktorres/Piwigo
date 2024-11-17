<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Returns the path to use for the Piwigo cookie.
 * If Piwigo is installed on:
 * http://domain.org/meeting/gallery/
 * it will return: "/meeting/gallery"
 */
function cookie_path(): string
{
    if (isset($_SERVER['REDIRECT_SCRIPT_NAME']) && ! empty($_SERVER['REDIRECT_SCRIPT_NAME'])) {
        $scr = $_SERVER['REDIRECT_SCRIPT_NAME'];
    } elseif (isset($_SERVER['REDIRECT_URL'])) {
        // mod_rewrite is activated for upper level directories. we must set the
        // cookie to the path shown in the browser otherwise it will be discarded.
        if (
            isset($_SERVER['PATH_INFO']) && ! empty($_SERVER['PATH_INFO']) && $_SERVER['REDIRECT_URL'] !== $_SERVER['PATH_INFO'] && str_ends_with((string) $_SERVER['REDIRECT_URL'], (string) $_SERVER['PATH_INFO'])
        ) {
            $scr = substr(
                (string) $_SERVER['REDIRECT_URL'],
                0,
                strlen((string) $_SERVER['REDIRECT_URL']) - strlen((string) $_SERVER['PATH_INFO'])
            );
        } else {
            $scr = $_SERVER['REDIRECT_URL'];
        }
    } else {
        $scr = $_SERVER['SCRIPT_NAME'];
    }

    $scr = substr((string) $scr, 0, strrpos((string) $scr, '/'));

    // add a trailing '/' if needed
    if (strlen($scr) == 0 || $scr[strlen($scr) - 1] !== '/') {
        $scr .= '/';
    }

    if (str_starts_with(PHPWG_ROOT_PATH, '../')) { // this is maybe a plugin inside pwg directory
        // TODO - what if it is an external script outside PWG ?
        $scr .= PHPWG_ROOT_PATH;
        while (1) {
            $new = preg_replace('#[^/]+/\.\.(/|$)#', '', (string) $scr);
            if ($new === $scr) {
                break;
            }

            $scr = $new;
        }
    }

    return $scr;
}

/**
 * Persistently stores a variable in pwg cookie.
 * Set $value to null to delete the cookie.
 *
 * @param mixed $value
 */
function pwg_set_cookie_var(
    mixed $var,
    string $value,
    ?int $expire = null
): bool {
    if ($value == null || $expire === 0) {
        unset($_COOKIE['pwg_' . $var]);
        return setcookie('pwg_' . $var, '', [
            'expires' => 0,
            'path' => cookie_path(),
        ]);

    }

    $_COOKIE['pwg_' . $var] = $value;
    $expire = is_numeric($expire) ? $expire : strtotime('+10 years');
    return setcookie('pwg_' . $var, $value, [
        'expires' => $expire,
        'path' => cookie_path(),
    ]);

}

/**
 * Retrieves the value of a persistent variable in pwg cookie
 * @see pwg_set_cookie_var
 */
function pwg_get_cookie_var(
    string $var,
    mixed $default = null
): mixed {
    return $_COOKIE['pwg_' . $var] ?? $default;

}
