<?php

declare(strict_types=1);

namespace Piwigo\admin;

use function Piwigo\admin\inc\delete_cat_permalink;
use function Piwigo\admin\inc\set_cat_permalink;
use function Piwigo\inc\add_url_params;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\dbLayer\pwg_db_changes;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_real_escape_string;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\display_select_cat_wrapper;
use function Piwigo\inc\fatal_error;
use function Piwigo\inc\get_cat_display_name_cache;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use const Piwigo\inc\PATTERN_ID;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

function parse_sort_variables(
    $sortable_by,
    $default_field,
    $get_param,
    $get_rejects,
    $template_var,
    string $anchor = ''
): array {
    global $template;

    $url_components = parse_url((string) $_SERVER['REQUEST_URI']);

    $base_url = $url_components['path'];

    parse_str($url_components['query'], $vars);
    $is_first = true;
    foreach ($vars as $key => $value) {
        if (! in_array($key, $get_rejects) && $key != $get_param) {
            $base_url .= $is_first ? '?' : '&amp;';
            $is_first = false;

            if (! in_array($key, ['page', 'psf', 'dpsf', 'pwg_token'])) {
                fatal_error('unexpected URL get key');
            }

            $base_url .= urlencode($key) . '=' . urlencode($value);
        }
    }

    $ret = [];
    foreach ($sortable_by as $field) {
        $url = $base_url;
        $disp = '↓'; // TODO: an small image is better

        if ($field !== ($_GET[$get_param] ?? null)) {
            if (! isset($default_field) || $default_field != $field) { // the first should be the default
                $url = add_url_params($url, [
                    $get_param => $field,
                ]);
            } elseif (isset($default_field) && ! isset($_GET[$get_param])) {
                $ret[] = $field;
                $disp = '<em>' . $disp . '</em>';
            }
        } else {
            $ret[] = $field;
            $disp = '<em>' . $disp . '</em>';
        }

        if (isset($template_var)) {
            $template->assign(
                $template_var . strtoupper((string) $field),
                '<a href="' . $url . $anchor . '" title="' . l10n('Sort order') . '">' . $disp . '</a>'
            );
        }
    }

    return $ret;
}

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_permalinks.php');

check_input_parameter('cat_id', $_POST, false, PATTERN_ID);

$selected_cat = [];
if (isset($_POST['set_permalink']) && $_POST['cat_id'] > 0) {
    check_pwg_token();
    $permalink = $_POST['permalink'];
    if (empty($permalink)) {
        delete_cat_permalink($_POST['cat_id'], isset($_POST['save']));
    } else {
        set_cat_permalink($_POST['cat_id'], $permalink, isset($_POST['save']));
    }

    $selected_cat = [$_POST['cat_id']];
} elseif (isset($_GET['delete_permanent'])) {
    check_pwg_token();
    $query = '
DELETE FROM ' . OLD_PERMALINKS_TABLE . '
  WHERE permalink=\'' . pwg_db_real_escape_string($_GET['delete_permanent']) . '\'
  LIMIT 1';
    $result = pwg_query($query);
    if (pwg_db_changes() == 0) {
        $page['errors'][] = l10n('Cannot delete the old permalink !');
    }
}

$template->set_filename('permalinks', 'permalinks.tpl');

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'permalinks';
include(PHPWG_ROOT_PATH . 'admin/inc/albums_tab.inc.php');

$query = '
SELECT
  id, permalink,
  CONCAT(id, " - ", name, IF(permalink IS NULL, "", " &radic;") ) AS name,
  uppercats, global_rank
FROM ' . CATEGORIES_TABLE;

display_select_cat_wrapper($query, $selected_cat, 'categories', false);

$pwg_token = get_pwg_token();

// --- generate display of active permalinks -----------------------------------
$sort_by = parse_sort_variables(
    ['id', 'name', 'permalink'],
    'name',
    'psf',
    ['delete_permanent'],
    'SORT_'
);

$query = '
SELECT id, permalink, uppercats, global_rank
  FROM ' . CATEGORIES_TABLE . '
  WHERE permalink IS NOT NULL
';
if ($sort_by[0] == 'id' || $sort_by[0] == 'permalink') {
    $query .= ' ORDER BY ' . $sort_by[0];
}

$categories = [];
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['uppercats']);
    $categories[] = $row;
}

if ($sort_by[0] == 'name') {
    usort($categories, '\Piwigo\inc\global_rank_compare');
}

$template->assign('permalinks', $categories);

// --- generate display of old permalinks --------------------------------------

$sort_by = parse_sort_variables(
    ['cat_id', 'permalink', 'date_deleted', 'last_hit', 'hit'],
    null,
    'dpsf',
    ['delete_permanent'],
    'SORT_OLD_',
    '#old_permalinks'
);

$url_del_base = get_root_url() . 'admin.php?page=permalinks';
$query = 'SELECT * FROM ' . OLD_PERMALINKS_TABLE;
if ($sort_by !== []) {
    $query .= ' ORDER BY ' . $sort_by[0];
}

$result = pwg_query($query);
$deleted_permalinks = [];
while ($row = pwg_db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['cat_id']);
    $row['U_DELETE'] =
        add_url_params(
            $url_del_base,
            [
                'delete_permanent' => $row['permalink'],
                'pwg_token' => $pwg_token,
            ]
        );
    $deleted_permalinks[] = $row;
}

$template->assign([
    'PWG_TOKEN' => $pwg_token,
    'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=permalinks',
    'deleted_permalinks' => $deleted_permalinks,
    'ADMIN_PAGE_TITLE' => l10n('Albums'),
]);

$template->assign_var_from_handle('ADMIN_CONTENT', 'permalinks');
