<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\check_status;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
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

$my_base_url = get_root_url() . 'admin.php?page=';

if (isset($_GET['tab'])) {
    check_input_parameter('tab', $_GET, false, '/^(actions|env)$/');
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = 'actions';
}

$tabsheet = new Tabsheet();
$tabsheet->set_id('maintenance');
$tabsheet->select($page['tab']);
$tabsheet->assign();

include(PHPWG_ROOT_PATH . 'admin/maintenance_' . $page['tab'] . '.php');

$template->assign(
    [
        'ADMIN_PAGE_TITLE' => l10n('Maintenance'),
    ]
);
