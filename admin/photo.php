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
check_input_parameter('image_id', $_GET, false, PATTERN_ID);

$admin_photo_base_url = get_root_url() . 'admin.php?page=photo-' . $_GET['image_id'];

// retrieving direct information about picture
$page['image'] = get_image_infos($_GET['image_id'], true);

if (isset($_GET['cat_id'])) {
    $query = <<<SQL
        SELECT *
        FROM categories
        WHERE id = {$_GET['cat_id']};
        SQL;
    $category = pwg_db_fetch_assoc(pwg_query($query));
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
$tabsheet->set_id('photo');
$tabsheet->select($page['tab']);
$tabsheet->assign();

$template->assign(
    [
        'ADMIN_PAGE_TITLE' => l10n('Edit photo') . ' <span class="image-id">#' . $_GET['image_id'] . '</span>',
    ]
);

// +-----------------------------------------------------------------------+
// | Load the tab                                                          |
// +-----------------------------------------------------------------------+

if ($page['tab'] == 'properties') {
    require PHPWG_ROOT_PATH . 'admin/picture_modify.php';
} elseif ($page['tab'] == 'coi') {
    require PHPWG_ROOT_PATH . 'admin/picture_coi.php';
} elseif ($page['tab'] == 'formats' && $conf['enable_formats']) {
    require PHPWG_ROOT_PATH . 'admin/picture_formats.php';
} else {
    require PHPWG_ROOT_PATH . 'admin/photo_' . $page['tab'] . '.php';
}
