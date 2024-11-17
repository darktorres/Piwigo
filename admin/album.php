<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// | Basic checks                                                          |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

$admin_album_base_url = get_root_url() . 'admin.php?page=album-' . $_GET['cat_id'];

$query = <<<SQL
    SELECT *
    FROM categories
    WHERE id = {$_GET['cat_id']};
    SQL;
$category = pwg_db_fetch_assoc(pwg_query($query));

if (! isset($category['id'])) {
    die('unknown album');
}

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$page['tab'] = 'properties';

if (isset($_GET['tab'])) {
    $page['tab'] = $_GET['tab'];
}

$tabsheet = new tabsheet();
$tabsheet->set_id('album');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Load the tab                                                          |
// +-----------------------------------------------------------------------+

$category_name = trigger_change(
    'render_category_name',
    $category['name'],
    'get_cat_display_name_cache'
);
$template->assign([
    'ADMIN_PAGE_TITLE' => l10n('Edit album') . ' <strong>' . $category_name . '</strong>',
    'ADMIN_PAGE_OBJECT_ID' => '#' . $category['id'],
]);

if ($page['tab'] == 'properties') {
    require PHPWG_ROOT_PATH . 'admin/cat_modify.php';
} elseif ($page['tab'] == 'sort_order') {
    require PHPWG_ROOT_PATH . 'admin/element_set_ranks.php';
} elseif ($page['tab'] == 'permissions') {
    $_GET['cat'] = $_GET['cat_id'];
    require PHPWG_ROOT_PATH . 'admin/cat_perm.php';
} else {
    require PHPWG_ROOT_PATH . 'admin/album_' . $page['tab'] . '.php';
}
