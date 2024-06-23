<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
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

$admin_album_base_url = get_root_url() . 'admin.php?page=album-' . $_GET['cat_id'];

$query = '
SELECT *
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $_GET['cat_id'] . '
;';
$category = pwg_db_fetch_assoc(pwg_query($query));

if (! isset($category['id'])) {
    die('unknown album');
}

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'properties';

if (isset($_GET['tab'])) {
    $page['tab'] = $_GET['tab'];
}

$tabsheet = new Tabsheet();
$tabsheet->set_id('album');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Load the tab                                                          |
// +-----------------------------------------------------------------------+

$template->assign([
    'ADMIN_PAGE_TITLE' => l10n('Edit album') . ' <strong>' . $category['name'] . '</strong>',
    'ADMIN_PAGE_OBJECT_ID' => '#' . $category['id'],
]);

if ($page['tab'] == 'properties') {
    include(PHPWG_ROOT_PATH . 'admin/cat_modify.php');
} elseif ($page['tab'] == 'sort_order') {
    include(PHPWG_ROOT_PATH . 'admin/element_set_ranks.php');
} elseif ($page['tab'] == 'permissions') {
    $_GET['cat'] = $_GET['cat_id'];
    include(PHPWG_ROOT_PATH . 'admin/cat_perm.php');
} else {
    include(PHPWG_ROOT_PATH . 'admin/album_' . $page['tab'] . '.php');
}
