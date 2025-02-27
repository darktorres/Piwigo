<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/inc/tabsheet.php');

$my_base_url = get_root_url().'admin.php?page=themes';

if (isset($_GET['tab']))
  $page['tab'] = $_GET['tab'];
else
  $page['tab'] = 'installed';

$tabsheet = new tabsheet();
$tabsheet->set_id('themes');
$tabsheet->select($page['tab']);
$tabsheet->assign();

if ($page['tab'] == 'update') {
  include(PHPWG_ROOT_PATH.'admin/updates_ext.php');
  $template->assign('ADMIN_PAGE_TITLE', l10n('Themes'));
}
else
{
  include(PHPWG_ROOT_PATH.'admin/themes_'.$page['tab'].'.php');
}
?>