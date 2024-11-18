<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'include/functions_plugins.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_user.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_cookie.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_session.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_category.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_html.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_tag.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_url.inc.php';
require_once PHPWG_ROOT_PATH . 'include/derivative_params.inc.php';
require_once PHPWG_ROOT_PATH . 'include/derivative_std_params.inc.php';
require_once PHPWG_ROOT_PATH . 'include/derivative.inc.php';

/**
 * returns the current microsecond since Unix epoch
 */
function micro_seconds(): string
{
    $t1 = explode(' ', microtime());
    $t2 = explode('.', $t1[0]);
    return $t1[1] . substr($t2[1], 0, 6);
}

/**
 * returns a float value corresponding to the number of seconds since
 * the unix epoch (1st January 1970) and the microseconds are precised
 * e.g., 1052343429.89276600
 */
function get_moment(): float
{
    return microtime(true);
}

/**
 * returns the number of seconds (with 3 decimals precision)
 * between the start time and the end time given
 *
 * @return string "$TIME s"
 */
function get_elapsed_time(
    float $start,
    float $end
): string {
    return number_format($end - $start, 3, '.', ' ') . ' s';
}

/**
 * returns the part of the string after the last "."
 */
function get_extension(
    ?string $filename
): string {
    return substr(strrchr((string) $filename, '.'), 1, strlen((string) $filename));
}

/**
 * returns the part of the string before the last ".".
 * get_filename_wo_extension( 'test.tar.gz' ) = 'test.tar'
 */
function get_filename_wo_extension(
    string $filename
): string {
    $pos = strrpos($filename, '.');
    return ($pos === false) ? $filename : substr($filename, 0, $pos);
}

/** no option for mkgetdir() */
define('MKGETDIR_NONE', 0);
/** sets mkgetdir() recursive */
define('MKGETDIR_RECURSIVE', 1);
/** sets mkgetdir() exit script on error */
define('MKGETDIR_DIE_ON_ERROR', 2);
/** sets mkgetdir() add a index.htm file */
define('MKGETDIR_PROTECT_INDEX', 4);
/** sets mkgetdir() add a .htaccess file*/
define('MKGETDIR_PROTECT_HTACCESS', 8);
/** default options for mkgetdir() = MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_INDEX */
define('MKGETDIR_DEFAULT', MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_INDEX);

/**
 * creates directory if not exists and ensures that directory is writable
 *
 * @param int $flags combination of MKGETDIR_xxx
 */
function mkgetdir(
    string $dir,
    int $flags = MKGETDIR_DEFAULT
): bool {
    if (! is_dir($dir)) {
        global $conf;
        $umask = umask(0);
        $mkd = mkdir($dir, $conf['chmod_value'], (bool) ($flags & MKGETDIR_RECURSIVE));
        umask($umask);
        if ($mkd == false) {
            if (($flags & MKGETDIR_DIE_ON_ERROR) !== 0) {
                fatal_error("{$dir} " . l10n('no write access'));
            }

            return false;
        }

        if (($flags & MKGETDIR_PROTECT_HTACCESS) !== 0) {
            $file = $dir . '/.htaccess';
            if (! file_exists($file)) {
                file_put_contents($file, 'deny from all');
            }
        }

        if (($flags & MKGETDIR_PROTECT_INDEX) !== 0) {
            $file = $dir . '/index.htm';
            if (! file_exists($file)) {
                file_put_contents($file, 'Not allowed!');
            }
        }
    }

    if (! is_writable($dir)) {
        if (($flags & MKGETDIR_DIE_ON_ERROR) !== 0) {
            fatal_error("{$dir} " . l10n('no write access'));
        }

        return false;
    }

    return true;
}

/**
 * finds out if a string is in ASCII, UTF-8 or another encoding
 *
 * @return int *0* if _$str_ is ASCII, *1* if UTF-8, *-1* otherwise
 */
function qualify_utf8(
    string $Str
): int {
    $ret = 0;
    for ($i = 0; $i < strlen($Str); $i++) {
        if (ord($Str[$i]) < 0x80) {
            continue;
        }

        # 0bbbbbbb
        $ret = 1;
        if ((ord($Str[$i]) & 0xE0) == 0xC0) {
            $n = 1;
        } # 110bbbbb
        elseif ((ord($Str[$i]) & 0xF0) == 0xE0) {
            $n = 2;
        } # 1110bbbb
        elseif ((ord($Str[$i]) & 0xF8) == 0xF0) {
            $n = 3;
        } # 11110bbb
        elseif ((ord($Str[$i]) & 0xFC) == 0xF8) {
            $n = 4;
        } # 111110bb
        elseif ((ord($Str[$i]) & 0xFE) == 0xFC) {
            $n = 5;
        } # 1111110b
        else {
            return -1;
        }

        # Does not match any model
        for ($j = 0; $j < $n; $j++) { # n bytes matching 10bbbbbb follow ?
            if ((++$i === strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80)) {
                return -1;
            }
        }
    }

    return $ret;
}

/**
 * Remove accents from a UTF-8 or ISO-8859-1 string (from WordPress)
 */
function remove_accents(
    string $string
): string {
    $utf = qualify_utf8($string);
    if ($utf == 0) {
        return $string; // ascii
    }

    if ($utf > 0) {
        $chars = [
            // Decompositions for Latin-1 Supplement
            "\xc3\x80" => 'A',
            "\xc3\x81" => 'A',
            "\xc3\x82" => 'A',
            "\xc3\x83" => 'A',
            "\xc3\x84" => 'A',
            "\xc3\x85" => 'A',
            "\xc3\x87" => 'C',
            "\xc3\x88" => 'E',
            "\xc3\x89" => 'E',
            "\xc3\x8a" => 'E',
            "\xc3\x8b" => 'E',
            "\xc3\x8c" => 'I',
            "\xc3\x8d" => 'I',
            "\xc3\x8e" => 'I',
            "\xc3\x8f" => 'I',
            "\xc3\x91" => 'N',
            "\xc3\x92" => 'O',
            "\xc3\x93" => 'O',
            "\xc3\x94" => 'O',
            "\xc3\x95" => 'O',
            "\xc3\x96" => 'O',
            "\xc3\x99" => 'U',
            "\xc3\x9a" => 'U',
            "\xc3\x9b" => 'U',
            "\xc3\x9c" => 'U',
            "\xc3\x9d" => 'Y',
            "\xc3\x9f" => 's',
            "\xc3\xa0" => 'a',
            "\xc3\xa1" => 'a',
            "\xc3\xa2" => 'a',
            "\xc3\xa3" => 'a',
            "\xc3\xa4" => 'a',
            "\xc3\xa5" => 'a',
            "\xc3\xa7" => 'c',
            "\xc3\xa8" => 'e',
            "\xc3\xa9" => 'e',
            "\xc3\xaa" => 'e',
            "\xc3\xab" => 'e',
            "\xc3\xac" => 'i',
            "\xc3\xad" => 'i',
            "\xc3\xae" => 'i',
            "\xc3\xaf" => 'i',
            "\xc3\xb1" => 'n',
            "\xc3\xb2" => 'o',
            "\xc3\xb3" => 'o',
            "\xc3\xb4" => 'o',
            "\xc3\xb5" => 'o',
            "\xc3\xb6" => 'o',
            "\xc3\xb9" => 'u',
            "\xc3\xba" => 'u',
            "\xc3\xbb" => 'u',
            "\xc3\xbc" => 'u',
            "\xc3\xbd" => 'y',
            "\xc3\xbf" => 'y',
            // Decompositions for Latin Extended-A
            "\xc4\x80" => 'A',
            "\xc4\x81" => 'a',
            "\xc4\x82" => 'A',
            "\xc4\x83" => 'a',
            "\xc4\x84" => 'A',
            "\xc4\x85" => 'a',
            "\xc4\x86" => 'C',
            "\xc4\x87" => 'c',
            "\xc4\x88" => 'C',
            "\xc4\x89" => 'c',
            "\xc4\x8a" => 'C',
            "\xc4\x8b" => 'c',
            "\xc4\x8c" => 'C',
            "\xc4\x8d" => 'c',
            "\xc4\x8e" => 'D',
            "\xc4\x8f" => 'd',
            "\xc4\x90" => 'D',
            "\xc4\x91" => 'd',
            "\xc4\x92" => 'E',
            "\xc4\x93" => 'e',
            "\xc4\x94" => 'E',
            "\xc4\x95" => 'e',
            "\xc4\x96" => 'E',
            "\xc4\x97" => 'e',
            "\xc4\x98" => 'E',
            "\xc4\x99" => 'e',
            "\xc4\x9a" => 'E',
            "\xc4\x9b" => 'e',
            "\xc4\x9c" => 'G',
            "\xc4\x9d" => 'g',
            "\xc4\x9e" => 'G',
            "\xc4\x9f" => 'g',
            "\xc4\xa0" => 'G',
            "\xc4\xa1" => 'g',
            "\xc4\xa2" => 'G',
            "\xc4\xa3" => 'g',
            "\xc4\xa4" => 'H',
            "\xc4\xa5" => 'h',
            "\xc4\xa6" => 'H',
            "\xc4\xa7" => 'h',
            "\xc4\xa8" => 'I',
            "\xc4\xa9" => 'i',
            "\xc4\xaa" => 'I',
            "\xc4\xab" => 'i',
            "\xc4\xac" => 'I',
            "\xc4\xad" => 'i',
            "\xc4\xae" => 'I',
            "\xc4\xaf" => 'i',
            "\xc4\xb0" => 'I',
            "\xc4\xb1" => 'i',
            "\xc4\xb2" => 'IJ',
            "\xc4\xb3" => 'ij',
            "\xc4\xb4" => 'J',
            "\xc4\xb5" => 'j',
            "\xc4\xb6" => 'K',
            "\xc4\xb7" => 'k',
            "\xc4\xb8" => 'k',
            "\xc4\xb9" => 'L',
            "\xc4\xba" => 'l',
            "\xc4\xbb" => 'L',
            "\xc4\xbc" => 'l',
            "\xc4\xbd" => 'L',
            "\xc4\xbe" => 'l',
            "\xc4\xbf" => 'L',
            "\xc5\x80" => 'l',
            "\xc5\x81" => 'L',
            "\xc5\x82" => 'l',
            "\xc5\x83" => 'N',
            "\xc5\x84" => 'n',
            "\xc5\x85" => 'N',
            "\xc5\x86" => 'n',
            "\xc5\x87" => 'N',
            "\xc5\x88" => 'n',
            "\xc5\x89" => 'N',
            "\xc5\x8a" => 'n',
            "\xc5\x8b" => 'N',
            "\xc5\x8c" => 'O',
            "\xc5\x8d" => 'o',
            "\xc5\x8e" => 'O',
            "\xc5\x8f" => 'o',
            "\xc5\x90" => 'O',
            "\xc5\x91" => 'o',
            "\xc5\x92" => 'OE',
            "\xc5\x93" => 'oe',
            "\xc5\x94" => 'R',
            "\xc5\x95" => 'r',
            "\xc5\x96" => 'R',
            "\xc5\x97" => 'r',
            "\xc5\x98" => 'R',
            "\xc5\x99" => 'r',
            "\xc5\x9a" => 'S',
            "\xc5\x9b" => 's',
            "\xc5\x9c" => 'S',
            "\xc5\x9d" => 's',
            "\xc5\x9e" => 'S',
            "\xc5\x9f" => 's',
            "\xc5\xa0" => 'S',
            "\xc5\xa1" => 's',
            "\xc5\xa2" => 'T',
            "\xc5\xa3" => 't',
            "\xc5\xa4" => 'T',
            "\xc5\xa5" => 't',
            "\xc5\xa6" => 'T',
            "\xc5\xa7" => 't',
            "\xc5\xa8" => 'U',
            "\xc5\xa9" => 'u',
            "\xc5\xaa" => 'U',
            "\xc5\xab" => 'u',
            "\xc5\xac" => 'U',
            "\xc5\xad" => 'u',
            "\xc5\xae" => 'U',
            "\xc5\xaf" => 'u',
            "\xc5\xb0" => 'U',
            "\xc5\xb1" => 'u',
            "\xc5\xb2" => 'U',
            "\xc5\xb3" => 'u',
            "\xc5\xb4" => 'W',
            "\xc5\xb5" => 'w',
            "\xc5\xb6" => 'Y',
            "\xc5\xb7" => 'y',
            "\xc5\xb8" => 'Y',
            "\xc5\xb9" => 'Z',
            "\xc5\xba" => 'z',
            "\xc5\xbb" => 'Z',
            "\xc5\xbc" => 'z',
            "\xc5\xbd" => 'Z',
            "\xc5\xbe" => 'z',
            "\xc5\xbf" => 's',
            // Decompositions for Latin Extended-B
            "\xc8\x98" => 'S',
            "\xc8\x99" => 's',
            "\xc8\x9a" => 'T',
            "\xc8\x9b" => 't',
            // Euro Sign
            "\xe2\x82\xac" => 'E',
            // GBP (Pound) Sign
            "\xc2\xa3" => '',
        ];

        $string = strtr($string, $chars);
    } else {
        // Assume ISO-8859-1 if not UTF-8
        $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
          . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
          . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
          . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
          . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
          . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
          . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
          . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
          . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
          . chr(252) . chr(253) . chr(255);

        $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

        $string = strtr($string, $chars['in'], $chars['out']);
        $double_chars['in'] = [chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254)];
        $double_chars['out'] = ['OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th'];
        $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }

    return $string;
}

if (function_exists('mb_strtolower')) {
    /**
     * removes accents from a string and converts it to lower case
     */
    function pwg_transliterate(
        string $term
    ): string {
        return remove_accents(mb_strtolower($term, 'utf-8'));
    }
} else {
    function pwg_transliterate(
        string $term
    ): string {
        return remove_accents(strtolower($term));
    }
}

/**
 * simplify a string to insert it into a URL
 *
 * @return string
 */
function str2url(
    string $str
): array|string {
    $str = pwg_transliterate($str);
    $safe = $str;
    $str = preg_replace('/[^\x80-\xffa-z0-9_\s\'\:\/\[\],-]/', '', $str);
    $str = preg_replace('/[\s\'\:\/\[\],-]+/', ' ', trim((string) $str));

    $res = str_replace(' ', '_', $str);

    if (empty($res)) {
        $res = str_replace(' ', '_', $safe);
    }

    return $res;
}

/**
 * returns an array with a list of {language_code => language_name}
 *
 * @return string[]
 */
function get_languages(): array
{
    $query = <<<SQL
        SELECT id, name
        FROM languages
        ORDER BY name ASC;
        SQL;
    $result = pwg_query($query);

    $languages = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        if (is_dir(PHPWG_ROOT_PATH . 'language/' . $row['id'])) {
            $languages[$row['id']] = $row['name'];
        }
    }

    return $languages;
}

/**
 * Must the current user log visits in history table?
 *
 * @since 14
 */
function do_log(
    ?int $image_id = null,
    ?string $image_type = null
): bool {
    global $conf;

    $do_log = $conf['log'];
    if (is_admin()) {
        $do_log = $conf['history_admin'];
    }

    if (is_a_guest()) {
        $do_log = $conf['history_guest'];
    }

    return trigger_change('pwg_log_allowed', $do_log, $image_id, $image_type);
}

/**
 * log the visit into history table
 */
function pwg_log(
    string|int|null $image_id = null,
    ?string $image_type = null,
    ?string $format_id = null
): bool {
    global $conf, $user, $page;

    $update_last_visit = false;
    if (empty($user['last_visit']) || strtotime((string) $user['last_visit']) < time() - $conf['session_length']) {
        $update_last_visit = true;
    }

    $update_last_visit = trigger_change('pwg_log_update_last_visit', $update_last_visit);

    if ($update_last_visit) {
        $query = <<<SQL
            UPDATE user_infos
            SET last_visit = NOW(), lastmodified = lastmodified
            WHERE user_id = {$user['id']};
            SQL;
        pwg_query($query);
    }

    if (! do_log((int) $image_id, $image_type)) {
        return false;
    }

    $tags_string = null;
    if ($page['section'] == 'tags') {
        $tags_string = implode(',', $page['tag_ids']);

        if (strlen($tags_string) > 50) {
            // we need to truncate, mysql won't accept a too long string
            $tags_string = substr($tags_string, 0, 50);
            // the last tag_id may have been truncated itself, so we must remove it
            $tags_string = substr($tags_string, 0, strrpos($tags_string, ','));
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    // In case of "too long" ipv6 address, we take only the 15 first chars.
    //
    // It would be "cleaner" to increase length of history.IP to 50 chars, but
    // the alter table is very long on such a big table. We should plan this
    // for a future version, once history table is kept "smaller".
    if (str_contains((string) $ip, ':') && strlen((string) $ip) > 15) {
        $ip = substr((string) $ip, 0, 15);
    }

    // If plugin developers add their own sections, Piwigo will automatically add it in the history.section enum column
    if (isset($page['section'])) {
        // set cache if not available
        if (! isset($conf['history_sections_cache'])) {
            conf_update_param('history_sections_cache', get_enums('history', 'section'), true);
        }

        if (
            in_array($page['section'], $conf['history_sections_cache']) || in_array(strtolower((string) $page['section']), array_map('strtolower', $conf['history_sections_cache']))
        ) {
            $section = $page['section'];
        } elseif (preg_match('/^[a-zA-Z0-9_-]+$/', (string) $page['section'])) {
            $history_sections = get_enums('history', 'section');
            $history_sections[] = $page['section'];

            // alter history table structure, to include a new section
            $unique_sections = implode("','", array_unique($history_sections));
            $query = <<<SQL
                ALTER TABLE history
                CHANGE section section enum('{$unique_sections}') DEFAULT NULL;
                SQL;
            pwg_query($query);

            // and refresh cache
            conf_update_param('history_sections_cache', get_enums('history', 'section'), true);

            $section = $page['section'];
        }
    }

    $sectionValue = isset($section) ? "'{$section}'" : 'NULL';
    $categoryIdValue = $page['category']['id'] ?? 'NULL';
    $searchIdValue = $page['search_id'] ?? 'NULL';
    $imageIdValue = $image_id ?? 'NULL';
    $imageTypeValue = isset($image_type) ? "'{$image_type}'" : 'NULL';
    $formatIdValue = $format_id ?? 'NULL';
    $authKeyIdValue = $page['auth_key_id'] ?? 'NULL';
    $tagsStringValue = isset($tags_string) ? "'{$tags_string}'" : 'NULL';
    $query = <<<SQL
        INSERT INTO history
            (date, time, user_id, IP, section, category_id, search_id, image_id, image_type, format_id, auth_key_id, tag_ids)
        VALUES
            (CURRENT_DATE, CURRENT_TIME, {$user['id']}, '{$ip}', {$sectionValue}, {$categoryIdValue}, {$searchIdValue},
            {$imageIdValue}, {$imageTypeValue}, {$formatIdValue}, {$authKeyIdValue}, {$tagsStringValue});
        SQL;
    pwg_query($query);

    $history_id = pwg_db_insert_id();
    if ($history_id % 1000 == 0) {
        require_once PHPWG_ROOT_PATH . 'admin/include/functions_history.inc.php';
        history_summarize(50000);
    }

    if ($conf['history_autopurge_every'] > 0 && $history_id % $conf['history_autopurge_every'] == 0) {
        require_once PHPWG_ROOT_PATH . 'admin/include/functions_history.inc.php';
        history_autopurge();
    }

    return true;
}

function pwg_activity(
    string $object,
    mixed $object_id,
    string $action,
    array $details = []
): void {
    global $user;

    // in case of uploadAsync, do not log the automatic login as an independent activity
    if (isset($_REQUEST['method']) && $_REQUEST['method'] == 'pwg.images.uploadAsync' && $action === 'login') {
        return;
    }

    if (isset($_REQUEST['method']) && $_REQUEST['method'] == 'pwg.plugins.performAction' && $_REQUEST['action'] != $action) {
        // for example, if you "restore" a plugin, the internal sequence will perform deactivate/uninstall/install/activate.
        // We only want to keep the last call to pwg_activity with the "restore" action.
        return;
    }

    $object_ids = $object_id;
    if (! is_array($object_id)) {
        $object_ids = [$object_id];
    }

    if (isset($_REQUEST['method'])) {
        $details['method'] = $_REQUEST['method'];
    } else {
        $details['script'] = script_basename();

        if ($details['script'] === 'admin' && isset($_GET['page'])) {
            $details['script'] .= '/' . $_GET['page'];
        }
    }

    if ($action === 'autoupdate') {
        // autoupdate on a plugin can happen anywhere, the "script/method" is not meaningful
        unset($details['method']);
        unset($details['script']);
    }

    $user_agent = null;
    if ($object === 'user' && $action === 'login' && isset($_SERVER['HTTP_USER_AGENT'])) {
        $user_agent = strip_tags((string) $_SERVER['HTTP_USER_AGENT']);
    }

    if ($object === 'photo' && $action === 'add' && ! isset($details['sync'])) {
        $details['added_with'] = 'app';
        if (isset($_SERVER['HTTP_REFERER']) && preg_match('/page=photos_add/', (string) $_SERVER['HTTP_REFERER'])) {
            $details['added_with'] = 'browser';
        }
    }

    if (in_array($object, ['album', 'photo']) && $action === 'delete' && isset($_GET['page']) && $_GET['page'] == 'site_update') {
        $details['sync'] = true;
    }

    if ($object === 'tag' && $action === 'delete' && isset($_POST['destination_tag'])) {
        $details['action'] = 'merge';
        $details['destination_tag'] = $_POST['destination_tag'];
    }

    $inserts = [];
    $details_insert = pwg_db_real_escape_string(serialize($details));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $session_id = session_id() ?: 'none';

    foreach ($object_ids as $loop_object_id) {
        $performed_by = $user['id'] ?? 0; // on a plugin autoupdate, $user is not yet loaded

        if ($action === 'logout') {
            $performed_by = $loop_object_id;
        }

        $inserts[] = [
            'object' => $object,
            'object_id' => $loop_object_id,
            'action' => $action,
            'performed_by' => $performed_by,
            'session_idx' => $session_id,
            'ip_address' => $ip_address,
            'details' => $details_insert,
            'user_agent' => pwg_db_real_escape_string($user_agent),
        ];
    }

    mass_inserts('activity', array_keys($inserts[0]), $inserts);
}

/**
 * Computes the difference between two dates.
 * returns a DateInterval object or a stdClass with the same attributes
 * http://stephenharris.info/date-intervals-in-php-5-2
 */
function dateDiff(
    DateTime $date1,
    DateTime $date2
): DateInterval|stdClass {
    if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
        return $date1->diff($date2);
    }

    $diff = new stdClass();

    //Make sure $date1 is earlier
    $diff->invert = $date2 < $date1;
    if ($diff->invert) {
        [$date1, $date2] = [$date2, $date1];
    }

    //Calculate R values
    $R = ($date1 <= $date2 ? '+' : '-');
    $r = ($date1 <= $date2 ? '' : '-');

    //Calculate total days
    $diff->days = round(abs($date1->format('U') - $date2->format('U')) / 86400);

    //A leap year work around - consistent with DateInterval
    $leap_year = $date1->format('m-d') === '02-29';
    if ($leap_year) {
        $date1->modify('-1 day');
    }

    //Years, months, days, hours
    $periods = [
        'years' => -1,
        'months' => -1,
        'days' => -1,
        'hours' => -1,
    ];

    foreach ($periods as $period => &$i) {
        if ($period === 'days' && $leap_year) {
            $date1->modify('+1 day');
        }

        while ($date1 <= $date2) {
            $date1->modify('+1 ' . $period);
            $i++;
        }

        //Reset date and record increments
        $date1->modify('-1 ' . $period);
    }

    [$diff->y, $diff->m, $diff->d, $diff->h] = array_values($periods);

    //Minutes, seconds
    $diff->s = round(abs($date1->format('U') - $date2->format('U')));
    $diff->i = floor($diff->s / 60);
    $diff->s -= $diff->i * 60;

    return $diff;
}

/**
 * converts a string into a DateTime object
 *
 * @param int|string|null $original timestamp or datetime string
 * @param ?string $format input format respecting date() syntax
 * @return DateTime|false
 */
function str2DateTime(
    int|string|null $original,
    ?string $format = null
): bool|DateTime {
    if ($original === 0 || ($original === '' || $original === '0') || $original === null) {
        return false;
    }

    if ($original instanceof DateTime) {
        return $original;
    }

    if ($format !== null && $format !== '' && $format !== '0' && version_compare(PHP_VERSION, '5.3.0') >= 0) {// from known date format
        return DateTime::createFromFormat('!' . $format, $original); // ! char to reset fields to UNIX epoch
    }

    $t = trim((string) $original, '0123456789');
    if ($t === '' || $t === '0') { // from timestamp
        return new DateTime('@' . $original);
    }

    // from unknown date format (assuming something like Y-m-d H:i:s)

    $ymdhms = [];
    $tok = strtok($original, '- :/');
    while ($tok !== false) {
        $ymdhms[] = $tok;
        $tok = strtok('- :/');
    }

    if (count($ymdhms) < 3) {
        return false;
    }

    if (! isset($ymdhms[3])) {
        $ymdhms[3] = 0;
    }

    if (! isset($ymdhms[4])) {
        $ymdhms[4] = 0;
    }

    if (! isset($ymdhms[5])) {
        $ymdhms[5] = 0;
    }

    $date = new DateTime();
    $date->setDate((int) $ymdhms[0], (int) $ymdhms[1], (int) $ymdhms[2]);
    $date->setTime((int) $ymdhms[3], (int) $ymdhms[4], (int) $ymdhms[5]);
    return $date;

}

/**
 * returns a formatted and localized date for display
 *
 * @param int|string|DateTime|null $original timestamp or datetime string
 * @param ?array $show list of components displayed, default is ['day_name', 'day', 'month', 'year']
 *    THIS PARAMETER IS PLANNED TO CHANGE
 * @param ?string $format input format respecting date() syntax
 */
function format_date(
    int|string|DateTime|null $original,
    ?array $show = null,
    ?string $format = null
): string {
    global $lang;

    $date = str2DateTime($original, $format);

    if (! $date) {
        return l10n('N/A');
    }

    if ($show === null || $show === true) {
        $show = ['day_name', 'day', 'month', 'year'];
    }

    // TODO use IntlDateFormatter for proper i18n

    $print = '';
    if (in_array('day_name', $show)) {
        $print .= $lang['day'][$date->format('w')] . ' ';
    }

    if (in_array('day', $show)) {
        $print .= $date->format('j') . ' ';
    }

    if (in_array('month', $show)) {
        $print .= $lang['month'][$date->format('n')] . ' ';
    }

    if (in_array('year', $show)) {
        $print .= $date->format('Y') . ' ';
    }

    if (in_array('time', $show)) {
        $temp = $date->format('H:i');
        if ($temp != '00:00') {
            $print .= $temp . ' ';
        }
    }

    return trim($print);
}

/**
 * Format a "From ... to ..." string from two dates
 */
function format_fromto(
    string $from,
    string $to,
    bool $full = false
): string {
    $from = str2DateTime($from);
    $to = str2DateTime($to);

    if ($from->format('Y-m-d') == $to->format('Y-m-d')) {
        return format_date($from);
    }

    if ($full || $from->format('Y') != $to->format('Y')) {
        $from_str = format_date($from);
    } elseif ($from->format('m') != $to->format('m')) {
        $from_str = format_date($from, ['day_name', 'day', 'month']);
    } else {
        $from_str = format_date($from, ['day_name', 'day']);
    }

    $to_str = format_date($to);

    return l10n('from %s to %s', $from_str, $to_str);

}

/**
 * Works out the time since the given date
 *
 * @param int|string $original timestamp or datetime string
 * @param string $stop year,month,week,day,hour,minute,second
 * @param ?string $format input format respecting date() syntax
 * @param bool $with_text append "ago" or "in the future"
 */
function time_since(
    int|string|null $original,
    string $stop = 'minute',
    ?string $format = null,
    bool $with_text = true,
    bool $with_week = true,
    bool $only_last_unit = false
): string {
    $date = str2DateTime($original, $format);

    if (! $date) {
        return l10n('N/A');
    }

    $now = new DateTime();
    $diff = dateDiff($now, $date);

    $chunks = [
        'year' => $diff->y,
        'month' => $diff->m,
        'week' => 0,
        'day' => $diff->d,
        'hour' => $diff->h,
        'minute' => $diff->i,
        'second' => $diff->s,
    ];

    // DateInterval does not contain the number of weeks
    if ($with_week) {
        $chunks['week'] = (int) floor($chunks['day'] / 7);
        $chunks['day'] -= $chunks['week'] * 7;
    }

    $j = array_search($stop, array_keys($chunks), true);

    $print = '';
    $i = 0;

    if (! $only_last_unit) {
        foreach ($chunks as $name => $value) {
            if ($value != 0) {
                $print .= ' ' . l10n_dec('%d ' . $name, '%d ' . $name . 's', $value);
            }

            if ($print !== '' && $print !== '0' && $i >= $j) {
                break;
            }

            $i++;
        }
    } else {
        $reversed_chunks_names = array_keys($chunks);
        while ($print === '' && $i < count($reversed_chunks_names)) {
            $name = $reversed_chunks_names[$i];
            $value = $chunks[$name];
            if ($value != 0) {
                $print = l10n_dec('%d ' . $name, '%d ' . $name . 's', $value);
            }

            if ($print !== '' && $print !== '0' && $i >= $j) {
                break;
            }

            $i++;
        }
    }

    $print = trim($print);

    if ($with_text) {
        $print = $diff->invert ? l10n('%s ago', $print) : l10n('%s in the future', $print);
    }

    return $print;
}

/**
 * transform a date string from a format to another (MySQL to d/M/Y for instance)
 *
 * @param string $format_in respecting date() syntax
 * @param string $format_out respecting date() syntax
 * @param ?string $default if _$original_ is empty
 */
function transform_date(
    string $original,
    string $format_in,
    string $format_out,
    ?string $default = null
): string {
    if ($original === '' || $original === '0') {
        return $default;
    }

    $date = str2DateTime($original, $format_in);
    return $date->format($format_out);
}

/**
 * append a variable to _$debug_ global
 */
function pwg_debug(
    string $string
): void {
    global $debug,$t2,$page;

    $now = explode(' ', microtime());
    $now2 = explode('.', $now[0]);
    $now2 = $now[1] . '.' . $now2[1];

    $time = number_format($now2 - $t2, 3, '.', ' ') . ' s';
    $debug .= '<p>';
    $debug .= '[' . $time . ', ';
    $debug .= $page['count_queries'] . ' queries] : ' . $string;
    $debug .= "</p>\n";
}

/**
 * Redirects to the given URL (HTTP method).
 * Once this function is called, the execution doesn't go further
 * (presence of an exit() instruction.
 */
function redirect_http(
    string $url
): never {
    if (ob_get_length() !== false) {
        ob_clean();
    }

    // default url is on html format
    $url = html_entity_decode($url);
    header('Request-URI: ' . $url);
    header('Content-Location: ' . $url);
    header('Location: ' . $url);
    exit();
}

/**
 * Redirects to the given URL (HTML method).
 * Once this function is called, the execution doesn't go further
 * (presence of an exit() instruction.
 */
function redirect_html(
    string $url,
    string $msg = '',
    int $refresh_time = 0
): never {
    global $user, $template, $lang_info, $conf, $lang, $t2, $page, $debug;

    if (! isset($lang_info) || ! isset($template)) {
        $user = build_user($conf['guest_id'], true);
        load_language('common.lang');
        trigger_notify('loading_lang');
        load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, [
            'no_fallback' => true,
            'local' => true,
        ]);
        $template = new Template(PHPWG_ROOT_PATH . 'themes', get_default_theme());
    } elseif (defined('IN_ADMIN') && IN_ADMIN) {
        $template = new Template(PHPWG_ROOT_PATH . 'themes', get_default_theme());
    }

    if ($msg === '' || $msg === '0') {
        $msg = nl2br(l10n('Redirection...'));
    }

    $refresh = $refresh_time;
    $url_link = $url;
    $title = 'redirection';

    $template->set_filenames([
        'redirect' => 'redirect.tpl',
    ]);

    require PHPWG_ROOT_PATH . 'include/page_header.php';

    $template->set_filenames([
        'redirect' => 'redirect.tpl',
    ]);
    $template->assign('REDIRECT_MSG', $msg);

    $template->parse('redirect');

    require PHPWG_ROOT_PATH . 'include/page_tail.php';

    exit();
}

/**
 * Redirects to the given URL (automatically choose HTTP or HTML method).
 * once this function is called, the execution doesn't go further
 * (presence of an exit() instruction.
 */
function redirect(
    string $url,
    string $msg = '',
    int $refresh_time = 0
): never {
    global $conf;

    // with RefreshTime <> 0, only HTML must be used
    if ($conf['default_redirect_method'] == 'http' && $refresh_time == 0 && ! headers_sent()
    ) {
        redirect_http($url);
    } else {
        redirect_html($url, $msg, $refresh_time);
    }
}

/**
 * returns available themes
 *
 * @return array
 */
function get_pwg_themes(
    bool $show_mobile = false
): mixed {
    global $conf;

    $themes = [];

    $query = <<<SQL
        SELECT id, name
        FROM themes
        ORDER BY name ASC;
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['id'] == $conf['mobile_theme']) {
            if (! $show_mobile) {
                continue;
            }

            $row['name'] .= ' (' . l10n('Mobile') . ')';
        }

        if (check_theme_installed($row['id'])) {
            $themes[$row['id']] = $row['name'];
        }
    }

    // plugins want to remove some themes based on user status maybe?
    $themes = trigger_change('get_pwg_themes', $themes);

    return $themes;
}

/**
 * check if a theme is installed (directory exists)
 */
function check_theme_installed(
    string $theme_id
): bool {
    global $conf;

    return file_exists($conf['themes_dir'] . '/' . $theme_id . '/' . 'themeconf.inc.php');
}

/**
 * Transforms an original path to its pwg representative
 */
function original_to_representative(
    string $path,
    string $representative_ext
): string {
    $pos = strrpos($path, '/');
    $path = substr_replace($path, 'pwg_representative/', $pos + 1, 0);
    $pos = strrpos($path, '.');
    return substr_replace($path, $representative_ext, $pos + 1);
}

/**
 * Transforms an original path to its format
 */
function original_to_format(
    string $path,
    string $format_ext
): string {
    $pos = strrpos($path, '/');
    $path = substr_replace($path, 'pwg_format/', $pos + 1, 0);
    $pos = strrpos($path, '.');
    return substr_replace($path, $format_ext, $pos + 1);
}

/**
 * get the full path of an image
 *
 * @param array $element_info element information from db (at least 'path')
 */
function get_element_path(
    array $element_info
): string {
    $path = $element_info['path'];
    if (! url_is_remote($path)) {
        $path = PHPWG_ROOT_PATH . $path;
    }

    return $path;
}

/**
 * fill the current user caddie with given elements, if not already in caddie
 *
 * @param int[] $elements_id
 */
function fill_caddie(
    array $elements_id
): void {
    global $user;

    $query = <<<SQL
        SELECT element_id
        FROM caddie
        WHERE user_id = {$user['id']};
        SQL;
    $in_caddie = query2array($query, null, 'element_id');

    $caddiables = array_diff($elements_id, $in_caddie);

    $datas = [];

    foreach ($caddiables as $caddiable) {
        $datas[] = [
            'element_id' => $caddiable,
            'user_id' => $user['id'],
        ];
    }

    if ($caddiables !== []) {
        mass_inserts('caddie', ['element_id', 'user_id'], $datas);
    }
}

/**
 * returns the element name from its filename.
 * removes file extension and replace underscores by spaces
 *
 * @return string name
 */
function get_name_from_file(
    string $filename
): string {
    return str_replace('_', ' ', get_filename_wo_extension($filename));
}

/**
 * translation function.
 * returns the corresponding value from _$lang_ if existing else the key is returned
 * if more than one parameter is provided sprintf is applied
 */
function l10n(
    string $key
): string {
    global $lang, $conf;

    if (($val = ($lang[$key] ?? null)) === null) {
        if ($conf['debug_l10n'] && ! isset($lang[$key]) && ($key !== '' && $key !== '0')) {
            trigger_error('[l10n] language key "' . $key . '" not defined', E_USER_WARNING);
        }

        $val = $key;
    }

    if (func_num_args() > 1) {
        $args = func_get_args();
        $val = vsprintf($val, array_slice($args, 1));
    }

    return $val;
}

/**
 * returns the printf value for strings including %d
 * returned value is concorded with decimal value (singular, plural)
 */
function l10n_dec(
    string $singular_key,
    string $plural_key,
    string|int $decimal
): string {
    global $lang_info;

    return sprintf(
        l10n((
            ($decimal > 1 || $decimal == 0 && $lang_info['zero_plural'])
            ? $plural_key
            : $singular_key
        )),
        $decimal
    );
}

/**
 * returns a single element to use with l10n_args
 *
 * @param string $key translation key
 * @param mixed $args arguments to use on sprintf($key, args)
 *   if args is an array, each value is used on sprintf
 */
function get_l10n_args(
    string $key,
    mixed $args = ''
): array {
    $key_arg = is_array($args) ? array_merge([$key], $args) : [$key, $args];

    return [
        'key_args' => $key_arg,
    ];
}

/**
 * returns a string formated with l10n elements.
 * it is useful to "prepare" a text and translate it later
 * @see get_l10n_args()
 *
 * @param array $key_args one l10n_args element or array of l10n_args elements
 * @param string $sep used when translated elements are concatenated
 */
function l10n_args(
    array $key_args,
    string $sep = "\n"
): string {
    if (is_array($key_args)) {
        foreach ($key_args as $key => $element) {
            if (isset($result)) {
                $result .= $sep;
            } else {
                $result = '';
            }

            if ($key === 'key_args') {
                array_unshift($element, l10n(array_shift($element))); // translate the key
                $result .= sprintf(...$element);
            } else {
                $result .= l10n_args($element, $sep);
            }
        }
    } else {
        fatal_error('l10n_args: Invalid arguments');
    }

    return $result;
}

/**
 * returns the corresponding value from $themeconf if existing or an empty string
 */
function get_themeconf(
    string $key
): string {
    return $GLOBALS['template']->get_themeconf($key);
}

/**
 * Returns webmaster mail address depending on $conf['webmaster_id']
 */
function get_webmaster_mail_address(): string
{
    global $conf;

    $query = <<<SQL
        SELECT {$conf['user_fields']['email']}
        FROM users
        WHERE {$conf['user_fields']['id']} = {$conf['webmaster_id']};
        SQL;
    [$email] = pwg_db_fetch_row(pwg_query($query));

    return trigger_change('get_webmaster_mail_address', $email);
}

function is_serialized(mixed $data, bool $strict = true): bool
{
    // If it isn't a string, it isn't serialized.
    if (! is_string($data)) {
        return false;
    }

    $data = trim($data);

    if ($data === 'N;') {
        return true;
    }

    if (strlen($data) < 4) {
        return false;
    }

    if ($data[1] !== ':') {
        return false;
    }

    if ($strict) {
        $lastc = substr($data, -1);

        if ($lastc !== ';' && $lastc !== '}') {
            return false;
        }
    } else {
        $semicolon = strpos($data, ';');
        $brace = strpos($data, '}');

        // Either ; or } must exist.
        if ($semicolon === false && $brace === false) {
            return false;
        }

        // But neither must be in the first X characters.
        if ($semicolon !== false && $semicolon < 3) {
            return false;
        }

        if ($brace !== false && $brace < 4) {
            return false;
        }
    }

    $token = $data[0];

    switch ($token) {
        case 's':
            if ($strict) {
                if (substr($data, -2, 1) !== '"') {
                    return false;
                }
            } elseif (! str_contains($data, '"')) {
                return false;
            }
            // no break
        case 'a':
        case 'O':
        case 'E':
            return (bool) preg_match(sprintf('/^%s:[0-9]+:/s', $token), $data);
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';
            return (bool) preg_match(sprintf('/^%s:[0-9.E+-]+;%s/', $token, $end), $data);
    }

    return false;
}

/**
 * Add configuration parameters from database to global $conf array
 *
 * @param string $condition SQL condition
 */
function load_conf_from_db(
    string $condition = ''
): void {
    global $conf;

    $condition = $condition === '' || $condition === '0' ? '' : 'WHERE ' . $condition;
    $query = <<<SQL
        SELECT param, value
        FROM config
        {$condition};
        SQL;
    $result = pwg_query($query);

    if (pwg_db_num_rows($result) == 0 && ($condition !== '' && $condition !== '0')) {
        fatal_error('No configuration data');
    }

    while ($row = pwg_db_fetch_assoc($result)) {
        $val = $row['value'] ?? '';

        if ($val === 'true') {
            $val = true;
        } elseif ($val === 'false') {
            $val = false;
        } elseif (is_serialized($val)) {
            if (DB_ENGINE === 'PostgreSQL') {
                $val = stripslashes((string) $val);
            }

            $val = unserialize($val);
        } elseif (is_numeric($val)) {
            $val = str_contains($val, '.') ? (float) $val : (int) $val;
        }

        $conf[$row['param']] = $val;
    }

    trigger_notify('load_conf', $condition);
}

/**
 * Is the config table currently writeable?
 *
 * @since 14
 */
function pwg_is_dbconf_writeable(): bool
{
    [$param, $value] = ['pwg_is_dbconf_writeable_' . generate_key(12), date('c') . ' ' . generate_key(20)];

    conf_update_param($param, $value);
    $query = <<<SQL
        SELECT value FROM config WHERE param = '{$param}';
        SQL;
    [$dbvalue] = pwg_db_fetch_row(pwg_query($query));

    if ($dbvalue != $value) {
        return false;
    }

    conf_delete_param($param);
    return true;
}

/**
 * Add or update a config parameter
 *
 * @param bool $updateGlobal update global *$conf* variable
 * @param ?callable $parser function to apply to the value before save in database
 *     (eg: serialize, json_encode) will not be applied to *$conf* if *$parser* is *true*
 */
function conf_update_param(
    string $param,
    mixed $value,
    bool $updateGlobal = false,
    ?callable $parser = null
): void {
    if ($parser != null) {
        $dbValue = call_user_func($parser, $value);
    } elseif (is_array($value) || is_object($value)) {
        $dbValue = addslashes(serialize($value));
    } else {
        $dbValue = boolean_to_string($value);
    }

    $query = <<<SQL
        INSERT INTO config
            (param, value)
        VALUES
            ('{$param}', '{$dbValue}')\n
        SQL;

    if (DB_ENGINE === 'MySQL') {
        $query .= "ON DUPLICATE KEY UPDATE value = '{$dbValue}'\n";
    }

    if (DB_ENGINE === 'PostgreSQL') {
        $query .= "ON CONFLICT (param) DO UPDATE SET value = EXCLUDED.value\n";
    }

    $query .= ';';
    pwg_query($query);

    if ($updateGlobal) {
        global $conf;
        $conf[$param] = $value;
    }
}

/**
 * Delete one or more config parameters
 * @since 2.6
 *
 * @param string|string[] $params
 */
function conf_delete_param(
    array|string $params
): void {
    global $conf;

    if (! is_array($params)) {
        $params = [$params];
    }

    if ($params === []) {
        return;
    }

    $implodedParams = implode("','", $params);
    $query = <<<SQL
        DELETE FROM config
        WHERE param IN ('{$implodedParams}');
        SQL;
    pwg_query($query);

    foreach ($params as $param) {
        unset($conf[$param]);
    }
}

/**
 * Return a default value for a configuration parameter.
 * @since 2.8
 *
 * @param string $param the configuration value to be extracted (if it exists)
 * @param mixed $default_value the default value for the configuration value if it does not exist.
 *
 * @return mixed The configuration value if the variable exists, otherwise the default.
 */
function conf_get_param(
    string $param,
    mixed $default_value = null
): mixed {
    global $conf;
    return $conf[$param] ?? $default_value;
}

/**
 * Apply *json_decode* on a value only if it is a string
 * @since 2.7
 */
function safe_json_decode(
    array|string $value
): array {
    if (is_string($value)) {
        return json_decode($value, true);
    }

    return $value;
}

/**
 * Prepends and appends strings at each value of the given array.
 */
function prepend_append_array_items(
    array $array,
    string $prepend_str,
    string $append_str
): array {
    array_walk($array, function (string &$value, string $key) use ($prepend_str, $append_str): void { $value = "{$prepend_str}{$value}{$append_str}"; });
    return $array;
}

/**
 * Return the basename of the current script.
 * The lowercase case filename of the current script without extension
 */
function script_basename(): string
{
    global $conf;

    foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF'] as $value) {
        if (! empty($_SERVER[$value])) {
            $filename = strtolower((string) $_SERVER[$value]);
            if ($conf['php_extension_in_urls'] && get_extension($filename) !== 'php') {
                continue;
            }

            $basename = basename($filename, '.php');
            if ($basename !== '' && $basename !== '0') {
                return $basename;
            }
        }
    }

    return '';
}

/**
 * Return $conf['filter_pages'] value for the current page
 */
function get_filter_page_value(
    string $value_name
): mixed {
    global $conf;

    $page_name = script_basename();

    if (isset($conf['filter_pages'][$page_name][$value_name])) {
        return $conf['filter_pages'][$page_name][$value_name];
    } elseif (isset($conf['filter_pages']['default'][$value_name])) {
        return $conf['filter_pages']['default'][$value_name];
    }

    return null;

}

/**
 * returns the parent (fallback) language of a language.
 * if _$lang_id_ is null it applies to the current language
 * @since 2.6
 */
function get_parent_language(
    ?string $lang_id = null
): ?string {
    if ($lang_id === null || $lang_id === '' || $lang_id === '0') {
        global $lang_info;
        return empty($lang_info['parent']) ? null : $lang_info['parent'];
    }

    $f = PHPWG_ROOT_PATH . 'language/' . $lang_id . '/common.lang.php';
    if (file_exists($f)) {
        require $f;
        return empty($lang_info['parent']) ? null : $lang_info['parent'];
    }

    return null;
}

/**
 * includes a language file or returns the content of a language file
 *
 * tries to load in descending order:
 *   param language, user language, default language
 *
 * @param mixed $options can contain
 *     @option string language - language to load
 *     @option bool return - if true the file content is returned
 *     @option bool no_fallback - if true do not load default language
 *     @option bool|string force_fallback - force preloading of another language
 *        default language if *true* or specified language
 *     @option bool local - if true load file from local directory
 */
function load_language(
    string $filename,
    string $dirname = '',
    array $options = []
): bool|string {
    global $user, $language_files;

    // keep trace of plugins loaded files for switch_lang_to() function
    if ($dirname !== '' && $dirname !== '0' && ($filename !== '' && $filename !== '0') && ! ($options['return'] ?? null)
      && ! isset($language_files[$dirname][$filename])) {
        $language_files[$dirname][$filename] = $options;
    }

    if (! ($options['return'] ?? null)) {
        $filename .= '.php';
    }

    if ($dirname === '' || $dirname === '0') {
        $dirname = PHPWG_ROOT_PATH;
    }

    $dirname .= 'language/';

    $default_language = (defined('PHPWG_INSTALLED') && ! defined('UPGRADES_PATH')) ?
        get_default_language() : PHPWG_DEFAULT_LANGUAGE;

    // construct list of potential languages
    $languages = [];
    if (! empty($options['language'])) { // explicit language
        $languages[] = $options['language'];
    }

    if (! empty($user['language'])) { // use language
        $languages[] = $user['language'];
    }

    if (($parent = get_parent_language()) != null) { // parent language
        // this is only for when the "child" language is missing
        $languages[] = $parent;
    }

    if (isset($options['force_fallback'])) { // fallback language
        // this is only for when the main language is missing
        if ($options['force_fallback'] === true) {
            $options['force_fallback'] = $default_language;
        }

        $languages[] = $options['force_fallback'];
    }

    if (! ($options['no_fallback'] ?? null)) { // default language
        $languages[] = $default_language;
    }

    $languages = array_unique($languages);

    // find first existing
    $source_file = '';
    $selected_language = '';
    foreach ($languages as $language) {
        $f = ($options['local'] ?? null) ?
          $dirname . $language . '.' . $filename :
          $dirname . $language . '/' . $filename;

        if (file_exists($f)) {
            $selected_language = $language;
            $source_file = $f;
            break;
        }
    }

    if ($source_file !== '' && $source_file !== '0') {
        if (! ($options['return'] ?? null)) {
            // load forced fallback
            if (isset($options['force_fallback']) && $options['force_fallback'] != $selected_language) {
                $tmp = str_replace($selected_language, $options['force_fallback'], $source_file);
                if (file_exists($tmp)) {
                    require $tmp;
                }
            }

            // load language content
            if (file_exists($source_file)) {
                require $source_file;
            }

            $load_lang = $lang;
            $load_lang_info = $lang_info ?? null;

            // access already existing values
            global $lang, $lang_info;
            if (! isset($lang)) {
                $lang = [];
            }

            if (! isset($lang_info)) {
                $lang_info = [];
            }

            // load parent language content directly in global
            if (! empty($load_lang_info['parent'])) {
                $parent_language = $load_lang_info['parent'];
            } elseif (! empty($lang_info['parent'])) {
                $parent_language = $lang_info['parent'];
            } else {
                $parent_language = null;
            }

            if (! empty($parent_language) && $parent_language != $selected_language) {
                $tmp = str_replace($selected_language, $parent_language, $source_file);
                if (file_exists($tmp)) {
                    require $tmp;
                }
            }

            // merge contents
            $lang = array_merge($lang, (array) $load_lang);
            $lang_info = array_merge($lang_info, (array) $load_lang_info);
            return true;
        }

        //Note: target charset is always utf-8 $content = convert_charset($content, 'utf-8', $target_charset);
        return file_get_contents($source_file);

    }

    return false;
}

/**
 * converts a string from a character set to another character set
 */
function convert_charset(
    string $str,
    string $source_charset,
    string $dest_charset
): array|bool|string {
    if ($source_charset === $dest_charset) {
        return $str;
    }

    if ($source_charset === 'iso-8859-1' && $dest_charset === 'utf-8') {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }

    if ($source_charset === 'utf-8' && $dest_charset === 'iso-8859-1') {
        return mb_convert_encoding($str, 'ISO-8859-1');
    }

    if (function_exists('iconv')) {
        return iconv($source_charset, $dest_charset . '//TRANSLIT', $str);
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, $dest_charset, $source_charset);
    }

    return $str; // TODO
}

/**
 * makes sure an index.htm protects the directory from browser file listing
 */
function secure_directory(
    string $dir
): void {
    $file = $dir . '/index.htm';
    if (! file_exists($file)) {
        file_put_contents($file, 'Not allowed!');
    }
}

/**
 * returns a "secret key" that is to be sent back when a user posts a form
 *
 * @param int $valid_after_seconds - key validity start time from now
 */
function get_ephemeral_key(
    int $valid_after_seconds,
    string $aditionnal_data_to_hash = ''
): string {
    global $conf;
    $time = round(microtime(true), 1);
    return $time . ':' . $valid_after_seconds . ':'
        . hash_hmac(
            'md5',
            $time . substr((string) $_SERVER['REMOTE_ADDR'], 0, 5) . $valid_after_seconds . $aditionnal_data_to_hash,
            (string) $conf['secret_key']
        );
}

/**
 * verify a key sent back with a form
 */
function verify_ephemeral_key(
    string $key,
    string $aditionnal_data_to_hash = ''
): bool {
    global $conf;
    $time = microtime(true);
    $key = explode(':', $key);
    if (count($key) != 3 || $key[0] > $time - (float) $key[1] || $key[0] < $time - 3600 || hash_hmac(
        'md5',
        $key[0] . substr((string) $_SERVER['REMOTE_ADDR'], 0, 5) . $key[1] . $aditionnal_data_to_hash,
        (string) $conf['secret_key']
    ) !== $key[2]
    ) {
        return false;
    }

    return true;
}

/**
 * return an array which will be sent to template to display navigation bar
 *
 * @param string $url base url of all links
 */
function create_navigation_bar(
    string $url,
    int $nb_element,
    int $start,
    int $nb_element_page,
    bool $clean_url = false,
    string $param_name = 'start'
): array {
    global $conf;

    $navbar = [];
    $pages_around = $conf['paginate_pages_around'];
    $start_str = $clean_url ? '/' . $param_name . '-' : (str_contains($url, '?') ? '&amp;' : '?') . $param_name . '=';

    if ($start < 0) {
        $start = 0;
    }

    // navigation bar useful only if more than one page to display!
    if ($nb_element > $nb_element_page) {
        $url_start = $url . $start_str;

        $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
        $maximum = ceil($nb_element / $nb_element_page);

        $start = $nb_element_page * round($start / $nb_element_page);
        $previous = $start - $nb_element_page;
        $next = $start + $nb_element_page;
        $last = ($maximum - 1) * $nb_element_page;

        // link to first page and previous page?
        if ($cur_page != 1) {
            $navbar['URL_FIRST'] = $url;
            $navbar['URL_PREV'] = $previous > 0 ? $url_start . $previous : $url;
        }

        // link on next page and last page?
        if ($cur_page != $maximum) {
            $navbar['URL_NEXT'] = $url_start . ($next < $last ? $next : $last);
            $navbar['URL_LAST'] = $url_start . $last;
        }

        // pages to display
        $navbar['pages'] = [];
        $navbar['pages'][1] = $url;
        for ($i = max(floor($cur_page) - $pages_around, 2), $stop = min(ceil($cur_page) + $pages_around + 1, $maximum);
            $i < $stop; $i++) {
            $navbar['pages'][$i] = $url . $start_str . (($i - 1) * $nb_element_page);
        }

        $navbar['pages'][$maximum] = $url_start . $last;
        $navbar['NB_PAGE'] = $maximum;
    }

    return $navbar;
}

/**
 * return an array which will be sent to template to display recent icon
 *
 * @return array
 */
function get_icon(
    ?string $date,
    bool $is_child_date = false
): array|bool {
    global $cache, $user;

    if ($date === null || $date === '' || $date === '0') {
        return false;
    }

    if (! isset($cache['get_icon']['title'])) {
        $cache['get_icon']['title'] = l10n(
            'photos posted during the last %d days',
            $user['recent_period']
        );
    }

    $icon = [
        'TITLE' => $cache['get_icon']['title'],
        'IS_CHILD_DATE' => $is_child_date,
    ];

    if (isset($cache['get_icon'][$date])) {
        return $cache['get_icon'][$date] ? $icon : [];
    }

    if (! isset($cache['get_icon']['sql_recent_date'])) {
        // Use MySql date in order to standardize all recent "actions/queries"
        $cache['get_icon']['sql_recent_date'] = pwg_db_get_recent_period($user['recent_period']);
    }

    $cache['get_icon'][$date] = $date > $cache['get_icon']['sql_recent_date'];

    return $cache['get_icon'][$date] ? $icon : [];
}

/**
 * check token coming from form posted or get params to prevent csrf attacks.
 * if pwg_token is empty action doesn't require token
 * else pwg_token is compared to server token
 */
function check_pwg_token(): void
{
    if (! empty($_REQUEST['pwg_token'])) {
        if (get_pwg_token() != $_REQUEST['pwg_token']) {
            access_denied();
        }
    } else {
        bad_request('missing token');
    }
}

/**
 * get pwg_token used to prevent csrf attacks
 */
function get_pwg_token(): string
{
    global $conf;

    return hash_hmac('md5', session_id(), (string) $conf['secret_key']);
}

/**
 * breaks the script execution if the given value doesn't match the given
 * pattern. This should happen only during hacking attempts.
 */
function check_input_parameter(
    string $param_name,
    array $param_array,
    bool $is_array,
    string $pattern,
    bool $mandatory = false
): ?bool {
    $param_value = null;
    if (isset($param_array[$param_name])) {
        $param_value = $param_array[$param_name];
    }

    // it's ok if the input parameter is null
    if (empty($param_value)) {
        if ($mandatory) {
            fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
        }

        return true;
    }

    if ($is_array) {
        if (! is_array($param_value)) {
            fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" should be an array');
        }

        foreach ($param_value as $key => $item_to_check) {
            if (! preg_match(PATTERN_ID, (string) $key) || ! preg_match($pattern, (string) $item_to_check)) {
                fatal_error('[Hacking attempt] an item is not valid in input parameter "' . $param_name . '"');
            }
        }
    } elseif (! preg_match($pattern, (string) $param_value)) {
        fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
    }

    return null;
}

/**
 * get localized privacy level values
 *
 * @return string[]
 */
function get_privacy_level_options(): array
{
    global $conf;

    $options = [];
    $label = '';
    foreach (array_reverse($conf['available_permission_levels']) as $level) {
        if ($level == 0) {
            $label = l10n('Everybody');
        } else {
            if (strlen($label) !== 0) {
                $label .= ', ';
            }

            $label .= l10n(sprintf('Level %d', $level));
        }

        $options[$level] = $label;
    }

    return $options;
}

/**
 * Return the branch from the version. For example, version 11.1.2 is on branch 11
 */
function get_branch_from_version(
    string $version
): string {
    // The algorithm is a bit complicated to just retrieve the first digits before
    // the first ".". It's because before version 11.0.0, we used to take the 2 first
    // digits, i.e., version 2.2.4 was on branch 2.2
    return implode('.', array_slice(explode('.', $version), 0, 1));
}

/**
 * return the device type: mobile, tablet or desktop
 */
function get_device(): string
{
    $device = pwg_get_session_var('device');

    if ($device === null) {
        $uagent_obj = new uagent_info();
        if ($uagent_obj->DetectSmartphone()) {
            $device = 'mobile';
        } elseif ($uagent_obj->DetectTierTablet()) {
            $device = 'tablet';
        } else {
            $device = 'desktop';
        }

        pwg_set_session_var('device', $device);
    }

    return $device;
}

/**
 * return true if mobile theme should be loaded
 */
function mobile_theme(): bool
{
    global $conf;

    if (empty($conf['mobile_theme'])) {
        return false;
    }

    if (isset($_GET['mobile'])) {
        $is_mobile_theme = get_boolean($_GET['mobile']);
        pwg_set_session_var('mobile_theme', $is_mobile_theme);
    } else {
        $is_mobile_theme = pwg_get_session_var('mobile_theme');
    }

    if ($is_mobile_theme === null) {
        $is_mobile_theme = (get_device() === 'mobile');
        pwg_set_session_var('mobile_theme', $is_mobile_theme);
    }

    return $is_mobile_theme;
}

/**
 * check url format
 */
function url_check_format(
    string $url
): bool {
    if (str_contains($url, '"')) {
        return false;
    }

    if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
        return false;
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * check email format
 */
function email_check_format(
    string $mail_address
): bool {
    return filter_var($mail_address, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * returns the number of available comments for the connected user
 */
function get_nb_available_comments(): int
{
    global $user;
    if (! isset($user['nb_available_comments'])) {
        $where = [];
        if (! is_admin()) {
            $where[] = "validated = 'true'";
        }

        $where[] = get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'ic.image_id',
            ],
            '',
            true
        );

        $whereClause = implode(' AND ', $where);
        $query = <<<SQL
            SELECT COUNT(DISTINCT(com.id))
            FROM image_category AS ic
            INNER JOIN comments AS com ON ic.image_id = com.image_id
            WHERE {$whereClause};
            SQL;
        [$user['nb_available_comments']] = pwg_db_fetch_row(pwg_query($query));

        single_update(
            'user_cache',
            [
                'nb_available_comments' => $user['nb_available_comments'],
            ],
            [
                'user_id' => $user['id'],
            ]
        );
    }

    return $user['nb_available_comments'];
}

/**
 * Compare two versions with version_compare after having converted
 * single chars to their decimal values.
 * Needed because version_compare does not understand versions like '2.5.c'.
 * @since 2.6
 */
function safe_version_compare(
    string $a,
    string $b,
    ?string $op = null
): bool|int {
    $replace_chars = fn ($m): int => ord(strtolower((string) $m[1]));

    // add dot before groups of letters (version_compare does the same thing)
    $a = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $a);
    $b = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $b);

    // apply ord() to any single letter
    $a = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, (string) $a);
    $b = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, (string) $b);

    if ($op === null || $op === '' || $op === '0') {
        return version_compare($a, $b);
    }

    return version_compare($a, $b, $op);

}

/**
 * Checks if the lounge needs to be emptied automatically.
 *
 * @since 12
 */
function check_lounge(): void
{
    global $conf;

    if (! isset($conf['lounge_active']) || ! $conf['lounge_active']) {
        return;
    }

    if (isset($_REQUEST['method']) && in_array($_REQUEST['method'], ['pwg.images.upload', 'pwg.images.uploadAsync'])) {
        return;
    }

    // is the oldest photo in the lounge older than lounge maximum waiting time?
    $query = <<<SQL
        SELECT image_id, date_available, NOW() AS dbnow
        FROM lounge
        JOIN images ON image_id = id
        ORDER BY image_id ASC
        LIMIT 1;
        SQL;
    $voyagers = query2array($query);
    if ($voyagers !== []) {
        $voyager = $voyagers[0];
        $age = strtotime((string) $voyager['dbnow']) - strtotime((string) $voyager['date_available']);

        if ($age > $conf['lounge_max_duration']) {
            require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
            empty_lounge();
        }
    }
}
