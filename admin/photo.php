<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\admin\inc\get_image_infos;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\PATTERN_ID;

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
$page['image'] = get_image_infos((int) $_GET['image_id'], true);

if (isset($_GET['cat_id'])) {
    $query = '
SELECT *
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $_GET['cat_id'] . '
;';
    $category = pwg_db_fetch_assoc(pwg_query($query));
}

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'properties';

if (isset($_GET['tab'])) {
    $page['tab'] = $_GET['tab'];
}

$tabsheet = new Tabsheet();
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
    include(PHPWG_ROOT_PATH . 'admin/picture_modify.php');
} elseif ($page['tab'] == 'coi') {
    include(PHPWG_ROOT_PATH . 'admin/picture_coi.php');
} elseif ($page['tab'] == 'formats' && $conf['enable_formats']) {
    include(PHPWG_ROOT_PATH . 'admin/picture_formats.php');
} else {
    include(PHPWG_ROOT_PATH . 'admin/photo_' . $page['tab'] . '.php');
}
