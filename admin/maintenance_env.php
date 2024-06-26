<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\CheckIntegrity;
use Piwigo\admin\inc\Image;
use Piwigo\inc\FileCombiner;
use Piwigo\inc\ImageStdParams;
use function Piwigo\admin\inc\categories_integrity;
use function Piwigo\admin\inc\clear_derivative_cache;
use function Piwigo\admin\inc\delete_orphan_tags;
use function Piwigo\admin\inc\fetchRemote;
use function Piwigo\admin\inc\images_integrity;
use function Piwigo\admin\inc\invalidate_user_cache;
use function Piwigo\admin\inc\update_category;
use function Piwigo\admin\inc\update_global_rank;
use function Piwigo\admin\inc\update_path;
use function Piwigo\admin\inc\update_uppercats;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\check_status;
use function Piwigo\inc\conf_update_param;
use function Piwigo\inc\dbLayer\do_maintenance_all_tables;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_get_db_version;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\format_date;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use function Piwigo\inc\pwg_session_gc;
use function Piwigo\inc\redirect;
use function Piwigo\inc\time_since;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\update_rating_score;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\IMG_CUSTOM;
use const Piwigo\inc\PHPWG_URL;
use const Piwigo\inc\PHPWG_VERSION;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

if (isset($_GET['action'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'phpinfo':

        phpinfo();
        exit();

    case 'lock_gallery':

        conf_update_param('gallery_locked', 'true');
        redirect(get_root_url() . 'admin.php?page=maintenance');
        break;

    case 'unlock_gallery':

        conf_update_param('gallery_locked', 'false');
        $_SESSION['page_infos'] = [l10n('Gallery unlocked')];
        redirect(get_root_url() . 'admin.php?page=maintenance');
        break;

    case 'categories':

        images_integrity();
        categories_integrity();
        update_uppercats();
        update_category();
        update_global_rank();
        invalidate_user_cache();
        break;

    case 'images':

        images_integrity();
        update_path();
        include_once(PHPWG_ROOT_PATH . 'inc/functions_rate.inc.php');
        update_rating_score();
        invalidate_user_cache();
        break;

    case 'delete_orphan_tags':

        delete_orphan_tags();
        break;

    case 'user_cache':

        invalidate_user_cache();
        break;

    case 'history_detail':

        $query = '
DELETE
  FROM ' . HISTORY_TABLE . '
;';
        pwg_query($query);
        break;

    case 'history_summary':

        $query = '
DELETE
  FROM ' . HISTORY_SUMMARY_TABLE . '
;';
        pwg_query($query);
        break;

    case 'sessions':

        pwg_session_gc();

        // delete all sessions associated to invalid user ids (it should never happen)
        $query = '
SELECT
    id,
    data
  FROM ' . SESSIONS_TABLE . '
;';
        $sessions = query2array($query);

        $query = '
SELECT
    ' . $conf['user_fields']['id'] . ' AS id
  FROM ' . USERS_TABLE . '
;';
        $all_user_ids = query2array($query, 'id');

        $sessions_to_delete = [];

        foreach ($sessions as $session) {
            if (preg_match(
                '/pwg_uid\|i:(\d+);/',
                (string) $session['data'],
                $matches
            ) && ! isset($all_user_ids[$matches[1]])) {
                $sessions_to_delete[] = $session['id'];
            }
        }

        if ($sessions_to_delete !== []) {
            $query = '
DELETE
  FROM ' . SESSIONS_TABLE . '
  WHERE id IN (\'' . implode("','", $sessions_to_delete) . '\')
;';
            pwg_query($query);
        }

        break;

    case 'feeds':

        $query = '
DELETE
  FROM ' . USER_FEED_TABLE . '
  WHERE last_check IS NULL
;';
        pwg_query($query);
        break;

    case 'database':

        do_maintenance_all_tables();
        break;

    case 'c13y':

        $c13y = new CheckIntegrity();
        $c13y->maintenance();
        break;

    case 'search':

        $query = '
DELETE
  FROM ' . SEARCH_TABLE . '
;';
        pwg_query($query);
        break;

    case 'compiled-templates':

        $template->delete_compiled_templates();
        FileCombiner::clear_combined_files();
        $persistent_cache->purge(true);
        break;

    case 'derivatives':

        clear_derivative_cache($_GET['type']);
        break;

    case 'check_upgrade':

        if (! fetchRemote(PHPWG_URL . '/download/latest_version', $result)) {
            $page['errors'][] = l10n('Unable to check for upgrade.');
        } else {
            $versions = [
                'current' => PHPWG_VERSION,
            ];
            $lines = explode("\r\n", (string) $result);

            // if the current version is a BSF (development branch) build, we check
            // the first line, for stable versions, we check the second line
            if (str_starts_with(
                $versions['current'],
                'BSF'
            )) {
                $versions['latest'] = trim($lines[0]);

                // because integer are limited to 4,294,967,296 we need to split BSF
                // versions in date.time
                foreach ($versions as $key => $value) {
                    $versions[$key] =
                      preg_replace('/BSF_(\d{8})(\d{4})/', '$1.$2', $value);
                }
            } else {
                $versions['latest'] = trim($lines[1]);
            }

            if ($versions['latest'] == '') {
                $page['errors'][] = l10n('Check for upgrade failed for unknown reasons.');
            }
            // concatenation needed to avoid automatic transformation by release
            // script generator
            elseif ($versions['current'] == '%PWGVERSION%') {
                $page['infos'][] = l10n('You are running on development sources, no check possible.');
            } elseif (version_compare($versions['current'], $versions['latest']) < 0) {
                $page['infos'][] = l10n('A new version of Piwigo is available.');

                $update_url = PHPWG_ROOT_PATH . 'admin.php?page=updates';
                $page['infos'][] = '<a href="' . $update_url . '">' . l10n(
                    'Update to Piwigo %s',
                    $versions['latest']
                ) . '</a>';
            } else {
                $page['infos'][] = l10n('You are running the latest version of Piwigo.');
            }
        }

        // no break
    default:

        break;

}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames([
    'maintenance' => 'maintenance_env.tpl',
]);

$url_format = get_root_url() . 'admin.php?page=maintenance&amp;action=%s&amp;pwg_token=' . get_pwg_token();

$purge_urls[l10n('All')] = sprintf($url_format, 'derivatives') . '&amp;type=all';
foreach (ImageStdParams::get_defined_type_map() as $params) {
    $purge_urls[l10n($params->type)] = sprintf($url_format, 'derivatives') . '&amp;type=' . $params->type;
}

$purge_urls[l10n(IMG_CUSTOM)] = sprintf($url_format, 'derivatives') . '&amp;type=' . IMG_CUSTOM;

$php_current_timestamp = date('Y-m-d H:i:s');
$db_version = pwg_get_db_version();
[$db_current_date] = pwg_db_fetch_row(pwg_query('SELECT now();'));

$template->assign(
    [
        'U_MAINT_CATEGORIES' => sprintf($url_format, 'categories'),
        'U_MAINT_IMAGES' => sprintf($url_format, 'images'),
        'U_MAINT_ORPHAN_TAGS' => sprintf($url_format, 'delete_orphan_tags'),
        'U_MAINT_USER_CACHE' => sprintf($url_format, 'user_cache'),
        'U_MAINT_HISTORY_DETAIL' => sprintf($url_format, 'history_detail'),
        'U_MAINT_HISTORY_SUMMARY' => sprintf($url_format, 'history_summary'),
        'U_MAINT_SESSIONS' => sprintf($url_format, 'sessions'),
        'U_MAINT_FEEDS' => sprintf($url_format, 'feeds'),
        'U_MAINT_DATABASE' => sprintf($url_format, 'database'),
        'U_MAINT_C13Y' => sprintf($url_format, 'c13y'),
        'U_MAINT_SEARCH' => sprintf($url_format, 'search'),
        'U_MAINT_COMPILED_TEMPLATES' => sprintf($url_format, 'compiled-templates'),
        'U_MAINT_DERIVATIVES' => sprintf($url_format, 'derivatives'),
        'purge_derivatives' => $purge_urls,
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=maintenance',

        'PHPWG_URL' => PHPWG_URL,
        'PWG_VERSION' => PHPWG_VERSION,
        'U_CHECK_UPGRADE' => sprintf($url_format, 'check_upgrade'),
        'OS' => PHP_OS,
        'PHP_VERSION' => PHP_VERSION,
        'DB_ENGINE' => 'MySQL',
        'DB_VERSION' => $db_version,
        'U_PHPINFO' => sprintf($url_format, 'phpinfo'),
        'PHP_DATATIME' => $php_current_timestamp,
        'DB_DATATIME' => $db_current_date,
        'cache_sizes' => $conf['cache_sizes'] ?? null,
        'time_elapsed_since_last_calc' => (isset($conf['cache_sizes'])) ? time_since(
            $conf['cache_sizes'][3]['value'],
            'year'
        ) : null,
    ]
);

// graphics library
switch (Image::get_library()) {
    case 'gd':
        $gd_info = gd_info();
        $template->assign('GRAPHICS_LIBRARY', 'GD ' . $gd_info['GD Version']);
        break;

    case 'vips':
        $library = 'image_vips';
        $template->assign('GRAPHICS_LIBRARY', $library);
        break;
}

if ($conf['gallery_locked']) {
    $template->assign(
        [
            'U_MAINT_UNLOCK_GALLERY' => sprintf($url_format, 'unlock_gallery'),
        ]
    );
} else {
    $template->assign(
        [
            'U_MAINT_LOCK_GALLERY' => sprintf($url_format, 'lock_gallery'),
        ]
    );
}

$query = '
SELECT
    registration_date
  FROM ' . USER_INFOS_TABLE . '
  WHERE user_id = 2
;';
$users = query2array($query);
if ($users !== []) {
    $installed_on = $users[0]['registration_date'];

    if (! empty($installed_on)) {
        $template->assign(
            [
                'INSTALLED_ON' => format_date($installed_on, ['day', 'month', 'year']),
                'INSTALLED_SINCE' => time_since($installed_on, 'day'),
            ]
        );
    }
}

// +-----------------------------------------------------------------------+
// | Define advanced features                                              |
// +-----------------------------------------------------------------------+

$advanced_features = [];

//$advanced_features is array of array composed of CAPTION & URL
$advanced_features = trigger_change(
    'get_admin_advanced_features_links',
    $advanced_features
);

$template->assign('advanced_features', $advanced_features);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle(
    'ADMIN_CONTENT',
    'maintenance'
);
