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
require_once PHPWG_ROOT_PATH . 'admin/include/image.class.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

if (isset($_GET['action'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';
$my_base_url = get_root_url() . 'admin.php?page=';

if (isset($_GET['tab'])) {
    check_input_parameter('tab', $_GET, false, '/^(actions|env)$/');
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = 'actions';
}

$tabsheet = new tabsheet();
$tabsheet->set_id('maintenance');
$tabsheet->select($page['tab']);
$tabsheet->assign();

require PHPWG_ROOT_PATH . 'admin/maintenance_' . $page['tab'] . '.php';

$template->assign(
    [
        'ADMIN_PAGE_TITLE' => l10n('Maintenance'),
    ]
);
