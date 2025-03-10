<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_category;
use Piwigo\inc\functions_html;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
functions_user::check_status(ACCESS_ADMINISTRATOR);

if (!empty($_POST))
{
  functions::check_pwg_token();
  functions::check_input_parameter('cat_true', $_POST, true, PATTERN_ID);
  functions::check_input_parameter('cat_false', $_POST, true, PATTERN_ID);
}

// +-----------------------------------------------------------------------+
// |                            variables init                             |
// +-----------------------------------------------------------------------+

if (!isset($_GET['group_id']))
{
  functions_html::fatal_error('group_id URL parameter is missing');
}

functions::check_input_parameter('group_id', $_GET, false, PATTERN_ID);

$page['group'] = $_GET['group_id'];

// +-----------------------------------------------------------------------+
// |                                updates                                |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify'])
    and isset($_POST['cat_true'])
    and count($_POST['cat_true']) > 0)
{
  // if you forbid access to a category, all sub-categories become
  // automatically forbidden
  $subcats = functions_category::get_subcat_ids($_POST['cat_true']);
  $query = '
DELETE
  FROM '.GROUP_ACCESS_TABLE.'
  WHERE group_id = '.$page['group'].'
  AND cat_id IN ('.implode(',', $subcats).')
;';
  functions_mysqli::pwg_query($query);
}
else if (isset($_POST['trueify'])
         and isset($_POST['cat_false'])
         and count($_POST['cat_false']) > 0)
{
  $uppercats = \Piwigo\admin\inc\functions::get_uppercat_ids($_POST['cat_false']);
  $private_uppercats = array();

  $query = '
SELECT id
  FROM '.CATEGORIES_TABLE.'
  WHERE id IN ('.implode(',', $uppercats).')
  AND status = \'private\'
;';
  $result = functions_mysqli::pwg_query($query);
  while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
  {
    $private_uppercats[] = $row['id'];
  }

  // retrying to authorize a category which is already authorized may cause
  // an error (in SQL statement), so we need to know which categories are
  // accesible
  $authorized_ids = array();

  $query = '
SELECT cat_id
  FROM '.GROUP_ACCESS_TABLE.'
  WHERE group_id = '.$page['group'].'
;';
  $result = functions_mysqli::pwg_query($query);

  while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
  {
    $authorized_ids[] = $row['cat_id'];
  }

  $inserts = array();
  $to_autorize_ids = array_diff($private_uppercats, $authorized_ids);
  foreach ($to_autorize_ids as $to_autorize_id)
  {
    $inserts[] = array(
      'group_id' => $page['group'],
      'cat_id' => $to_autorize_id
      );
  }

  functions_mysqli::mass_inserts(GROUP_ACCESS_TABLE, array('group_id','cat_id'), $inserts);
  \Piwigo\admin\inc\functions::invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'group_perm' => 'group_perm.tpl',
    'double_select' => 'double_select.tpl'
    )
  );

$template->assign(
  array(
    'TITLE' =>
      functions::l10n(
        'Manage permissions for group "%s"',
        \Piwigo\admin\inc\functions::get_groupname($page['group'])
        ),
    'L_CAT_OPTIONS_TRUE'=>functions::l10n('Authorized'),
    'L_CAT_OPTIONS_FALSE'=>functions::l10n('Forbidden'),

    'F_ACTION' =>
        functions_url::get_root_url().
        'admin.php?page=group_perm&amp;group_id='.
        $page['group']
    )
  );

// only private categories are listed
$query_true = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.' INNER JOIN '.GROUP_ACCESS_TABLE.' ON cat_id = id
  WHERE status = \'private\'
    AND group_id = '.$page['group'].'
;';
functions_category::display_select_cat_wrapper($query_true,array(),'category_option_true');

$result = functions_mysqli::pwg_query($query_true);
$authorized_ids = array();
while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
{
  $authorized_ids[] = $row['id'];
}

$query_false = '
SELECT id,name,uppercats,global_rank
  FROM '.CATEGORIES_TABLE.'
  WHERE status = \'private\'';
if (count($authorized_ids) > 0)
{
  $query_false.= '
    AND id NOT IN ('.implode(',', $authorized_ids).')';
}
$query_false.= '
;';
functions_category::display_select_cat_wrapper($query_false,array(),'category_option_false');

$template->assign('PWG_TOKEN', functions::get_pwg_token());

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
$template->assign_var_from_handle('ADMIN_CONTENT', 'group_perm');

?>
