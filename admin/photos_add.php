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

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';
require_once PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php';
require_once PHPWG_ROOT_PATH . 'admin/include/image.class.php';

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

$tabsheet = new tabsheet();
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

require PHPWG_ROOT_PATH . 'admin/photos_add_' . $page['tab'] . '.php';
