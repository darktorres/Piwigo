<?php

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$my_base_url = get_root_url() . 'admin.php?page=languages';

if (isset($_GET['tab'])) {
    check_input_parameter('tab', $_GET, false, '/^(installed|update|new)$/');
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = 'installed';
}

$tabsheet = new Tabsheet();
$tabsheet->set_id('languages');
$tabsheet->select($page['tab']);
$tabsheet->assign();

if ($page['tab'] == 'update') {
    require(__DIR__ . '/../admin/updates_ext.php');
    $template->assign('ADMIN_PAGE_TITLE', l10n('Languages'));
} else {
    require(__DIR__ . '/../admin/languages_' . $page['tab'] . '.php');
}
