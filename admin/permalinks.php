<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\functions_permalinks;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_category;
use Piwigo\inc\functions_html;
use Piwigo\inc\functions_url;

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_permalinks.php');

functions::check_input_parameter('cat_id', $_POST, false, PATTERN_ID);

$selected_cat = [];
if (isset($_POST['set_permalink']) and $_POST['cat_id'] > 0) {
    functions::check_pwg_token();
    $permalink = $_POST['permalink'];
    if (empty($permalink)) {
        functions_permalinks::delete_cat_permalink($_POST['cat_id'], isset($_POST['save']));
    } else {
        functions_permalinks::set_cat_permalink($_POST['cat_id'], $permalink, isset($_POST['save']));
    }
    $selected_cat = [$_POST['cat_id']];
} elseif (isset($_GET['delete_permanent'])) {
    functions::check_pwg_token();
    $query = '
DELETE FROM ' . OLD_PERMALINKS_TABLE . '
  WHERE permalink=\'' . functions_mysqli::pwg_db_real_escape_string($_GET['delete_permanent']) . '\'
  LIMIT 1';
    $result = functions_mysqli::pwg_query($query);
    if (functions_mysqli::pwg_db_changes($result) == 0) {
        $page['errors'][] = functions::l10n('Cannot delete the old permalink !');
    }
}

$template->set_filename('permalinks', 'permalinks.tpl');

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'permalinks';
include(PHPWG_ROOT_PATH . 'admin/inc/albums_tab.php');

$query = '
SELECT
  id, permalink,
  CONCAT(id, " - ", name, IF(permalink IS NULL, "", " &radic;") ) AS name,
  uppercats, global_rank
FROM ' . CATEGORIES_TABLE;

functions_category::display_select_cat_wrapper($query, $selected_cat, 'categories', false);

$pwg_token = functions::get_pwg_token();

// --- generate display of active permalinks -----------------------------------
$sort_by = functions::parse_sort_variables(
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
if ($sort_by[0] == 'id' or $sort_by[0] == 'permalink') {
    $query .= ' ORDER BY ' . $sort_by[0];
}
$categories = [];
$result = functions_mysqli::pwg_query($query);
while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
    $row['name'] = functions_html::get_cat_display_name_cache($row['uppercats']);
    $categories[] = $row;
}

if ($sort_by[0] == 'name') {
    usort($categories, '\Piwigo\inc\functions_category::global_rank_compare');
}
$template->assign('permalinks', $categories);

// --- generate display of old permalinks --------------------------------------

$sort_by = functions::parse_sort_variables(
    ['cat_id', 'permalink', 'date_deleted', 'last_hit', 'hit'],
    null,
    'dpsf',
    ['delete_permanent'],
    'SORT_OLD_',
    '#old_permalinks'
);

$url_del_base = functions_url::get_root_url() . 'admin.php?page=permalinks';
$query = 'SELECT * FROM ' . OLD_PERMALINKS_TABLE;
if (count($sort_by)) {
    $query .= ' ORDER BY ' . $sort_by[0];
}
$result = functions_mysqli::pwg_query($query);
$deleted_permalinks = [];
while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
    $row['name'] = functions_html::get_cat_display_name_cache($row['cat_id']);
    $row['U_DELETE'] =
        functions_url::add_url_params(
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
    'U_HELP' => functions_url::get_root_url() . 'admin/popuphelp.php?page=permalinks',
    'deleted_permalinks' => $deleted_permalinks,
    'ADMIN_PAGE_TITLE' => functions::l10n('Albums'),
]);

$template->assign_var_from_handle('ADMIN_CONTENT', 'permalinks');
