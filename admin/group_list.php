<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\tabsheet;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$my_base_url = functions_url::get_root_url().'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('groups');
$tabsheet->select('group_list');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
functions_user::check_status(ACCESS_ADMINISTRATOR);

if (!empty($_POST) or isset($_GET['delete']) or isset($_GET['toggle_is_default']))
{
  functions::check_pwg_token();
}


// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('group_list' => 'group_list.tpl'));

$template->assign(
  array(
    'F_ADD_ACTION' => functions_url::get_root_url().'admin.php?page=group_list',
    // 'U_HELP' => \Piwigo\inc\functions_url::get_root_url().'admin/popuphelp.php?page=group_list',
    'PWG_TOKEN' => functions::get_pwg_token(),
    'CACHE_KEYS' => \Piwigo\admin\inc\functions::get_admin_client_cache_keys(array('groups', 'users')),
    )
  );

// +-----------------------------------------------------------------------+
// |                              group list                               |
// +-----------------------------------------------------------------------+

$query = '
SELECT id, name, is_default
  FROM `'.GROUPS_TABLE.'`
  ORDER BY name ASC
;';
$result = functions_mysqli::pwg_query($query);

$admin_url = functions_url::get_root_url().'admin.php?page=';
$perm_url    = $admin_url.'group_perm&amp;group_id=';
$users_url = $admin_url.'user_list&amp;group=';
$del_url     = $admin_url.'group_list&amp;delete=';
$toggle_is_default_url     = $admin_url.'group_list&amp;toggle_is_default=';

$group_counter = 0;

while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
{
  $query = '
SELECT u.'. $conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.' AS u
  INNER JOIN '.USER_GROUP_TABLE.' AS ug
    ON u.'.$conf['user_fields']['id'].' = ug.user_id
  WHERE ug.group_id = '.$row['id'].'
;';
  $members=array();
  $res=functions_mysqli::pwg_query($query);
  while ($us= functions_mysqli::pwg_db_fetch_assoc($res))
  {
    $members[]=$us['username'];
  }
  $template->append(
    'groups',
    array(
      'NAME' => $row['name'],
      'ID' => $row['id'],
      'IS_DEFAULT' => (functions_mysqli::get_boolean($row['is_default']) ? ' ['.functions::l10n('default').']' : ''),
      'NB_MEMBERS' => count($members),
      'L_MEMBERS' => implode(' <span class="userSeparator">&middot;</span> ', $members),
      'MEMBERS' => functions::l10n_dec('%d member', '%d members', count($members)),
      'U_DELETE' => $del_url.$row['id'].'&amp;pwg_token='.functions::get_pwg_token(),
      'U_PERM' => $perm_url.$row['id'],
      'U_USERS' => $users_url.$row['id'],
      'U_ISDEFAULT' => $toggle_is_default_url.$row['id'].'&amp;pwg_token='.functions::get_pwg_token(),
      )
    );

  $group_counter++;
}

$template->assign('ADMIN_PAGE_TITLE', functions::l10n('Groups').' <span class="badge-number">'.$group_counter.'</span>');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'group_list');

?>