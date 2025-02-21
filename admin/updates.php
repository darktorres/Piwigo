<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\tabsheet;

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

if (!$conf['enable_extensions_install'] and !$conf['enable_core_update'])
{
  die('update system is disabled');
}

$my_base_url = get_root_url().'admin.php?page=updates';

if (isset($_GET['tab']))
  $page['tab'] = $_GET['tab'];
else
  $page['tab'] = 'pwg';

$tabsheet = new tabsheet();
$tabsheet->set_id('updates');
$tabsheet->select($page['tab']);
$tabsheet->assign();

include(PHPWG_ROOT_PATH.'admin/updates_'.$page['tab'].'.php');

?>