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

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$my_base_url = get_root_url() . 'admin.php?page=plugins';

if (isset($_GET['tab'])) {
    $page['tab'] = $_GET['tab'];
} else {
    $page['tab'] = 'installed';
}

$tabsheet = new tabsheet();
$tabsheet->set_id('plugins');
$tabsheet->select($page['tab']);
$tabsheet->assign();

if ($page['tab'] == 'update') {
    require PHPWG_ROOT_PATH . 'admin/updates_ext.php';
    $template->assign('ADMIN_PAGE_TITLE', l10n('Plugins'));
} else {
    require PHPWG_ROOT_PATH . 'admin/plugins_' . $page['tab'] . '.php';
}
