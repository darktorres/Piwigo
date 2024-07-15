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

if (! $conf['enable_extensions_install'] && ! $conf['enable_core_update']) {
    die('update system is disabled');
}

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$my_base_url = get_root_url() . 'admin.php?page=updates';

$page['tab'] = $_GET['tab'] ?? 'pwg';

$tabsheet = new tabsheet();
$tabsheet->set_id('updates');
$tabsheet->select($page['tab']);
$tabsheet->assign();

require PHPWG_ROOT_PATH . 'admin/updates_' . $page['tab'] . '.php';
