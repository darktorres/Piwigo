<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\inc\get_root_url;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

if (! $conf['enable_extensions_install'] && ! $conf['enable_core_update']) {
    die('update system is disabled');
}

$my_base_url = get_root_url() . 'admin.php?page=updates';

$page['tab'] = $_GET['tab'] ?? 'pwg';

$tabsheet = new Tabsheet();
$tabsheet->set_id('updates');
$tabsheet->select($page['tab']);
$tabsheet->assign();

include(PHPWG_ROOT_PATH . 'admin/updates_' . $page['tab'] . '.php');
