<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\admin\inc\get_upload_form_config;
use function Piwigo\inc\check_status;
use function Piwigo\inc\get_root_url;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;

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
include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_upload.inc.php');

define(
    'PHOTOS_ADD_BASE_URL',
    get_root_url() . 'admin.php?page=photos_add'
);

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                          Load configuration                           |
// +-----------------------------------------------------------------------+

$upload_form_config = get_upload_form_config();

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['tab'] = $_GET['section'];

    // backward compatibility
    if ($page['tab'] == 'ploader') {
        $page['tab'] = 'applications';
    }
} else {
    $page['tab'] = 'direct';
}

$tabsheet = new Tabsheet();
$tabsheet->set_id('photos_add');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'photos_add' => 'photos_add_' . $page['tab'] . '.tpl',
    ]
);

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

include(PHPWG_ROOT_PATH . 'admin/photos_add_' . $page['tab'] . '.php');
