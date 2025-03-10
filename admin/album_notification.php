<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\derivative_std_params;
use Piwigo\inc\DerivativeImage;
use Piwigo\inc\functions;
use Piwigo\inc\functions_html;
use Piwigo\inc\functions_mail;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

if (!defined('PHPWG_ROOT_PATH'))
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'inc/functions_mail.php');
include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

functions_user::check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                       variable initialization                         |
// +-----------------------------------------------------------------------+

$page['cat'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                           form submission                             |
// +-----------------------------------------------------------------------+

// info by email to an access granted group of category informations
if (isset($_POST['submitEmail']))
{
  functions_url::set_make_full_url();

  $img = array();

  /* TODO: if $category['representative_picture_id']
    is empty find child representative_picture_id */
  if (!empty($category['representative_picture_id']))
  {
    $query = '
SELECT id, file, path, representative_ext
  FROM '.IMAGES_TABLE.'
  WHERE id = '.$category['representative_picture_id'].'
;';

    $result = functions_mysqli::pwg_query($query);
    if (functions_mysqli::pwg_db_num_rows($result) > 0)
    {
      $element = functions_mysqli::pwg_db_fetch_assoc($result);

      $img = array(
        'link' => functions_url::make_picture_url(
          array(
            'image_id' => $element['id'],
            'image_file' => $element['file'],
            'category' => $category
            )
          ),
        'src' => DerivativeImage::url(derivative_std_params::IMG_THUMB, $element),
        );
    }
  }

  $args = array(
    'subject' => functions::l10n('[%s] Visit album %s', $conf['gallery_title'], functions_plugins::trigger_change('render_category_name', $category['name'], 'admin_cat_list')),
    // TODO : change this language variable to 'Visit album %s'
    // TODO : 'language_selected' => ....
    );
    
  $tpl = array(
    'filename' => 'cat_group_info',
    'assign' => array(
      'IMG' => $img,
      'CAT_NAME' => functions_plugins::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
      'LINK' => functions_url::make_index_url(
        array(
          'category' => array(
            'id' => $category['id'],
            'name' => functions_plugins::trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
            'permalink' => $category['permalink']
            )
          )
        ),
      'CPL_CONTENT' => empty($_POST['mail_content']) ? '' : stripslashes($_POST['mail_content']),
      )
    );

  if ('users' == $_POST['who'] and isset($_POST['users']) and count($_POST['users']) > 0)
  {
    functions::check_input_parameter('users', $_POST, true, PATTERN_ID);

    // TODO code very similar to function pwg_mail_group. We'd better create
    // a function pwg_mail_users that could be called from here and from
    // pwg_mail_group

    // TODO to make checks even better, we should check that theses users
    // have access to this album. No real privacy issue here, even if we
    // send the email to a user without permission.

    $query = '
SELECT
    ui.user_id,
    ui.status,
    ui.language,
    u.'.$conf['user_fields']['email'].' AS email,
    u.'.$conf['user_fields']['username'].' AS username
  FROM '.USER_INFOS_TABLE.' AS ui
    JOIN '.USERS_TABLE.' AS u ON u.'.$conf['user_fields']['id'].' = ui.user_id
  WHERE ui.user_id IN ('.implode(',', $_POST['users']).')
;';
    $users = functions_mysqli::query2array($query);
    $usernames = array();
    
    foreach ($users as $u)
    {
      $usernames[] = $u['username'];
      
      $authkey = functions_user::create_user_auth_key($u['user_id'], $u['status']);
      
      $user_tpl = $tpl;

      if ($authkey !== false)
      {
        $user_tpl['assign']['LINK'] = functions_url::add_url_params($tpl['assign']['LINK'], array('auth' => $authkey['auth_key']));

        if (isset($user_tpl['assign']['IMG']['link']))
        {
          $user_tpl['assign']['IMG']['link'] = functions_url::add_url_params(
            $user_tpl['assign']['IMG']['link'],
            array('auth' => $authkey['auth_key'])
            );
        }
      }

      $user_args = $args;
      if (isset($authkey['auth_key']))
      {
        $user_args['auth_key'] = $authkey['auth_key'];
      }

      functions_mail::switch_lang_to($u['language']);
      functions_mail::pwg_mail($u['email'], $user_args, $user_tpl);
      functions_mail::switch_lang_back();
    }

    $message = functions::l10n_dec('%d mail was sent.', '%d mails were sent.', count($users));
    $message.= ' ('.implode(', ', $usernames).')';
    
    $page['infos'][] = $message;
  }
  elseif ('group' == $_POST['who'] and !empty($_POST['group']))
  {
    functions::check_input_parameter('group', $_POST, false, PATTERN_ID);
    
    functions_mail::pwg_mail_group($_POST['group'], $args, $tpl);

    $query = '
SELECT
    name
  FROM `'.GROUPS_TABLE.'`
  WHERE id = '.$_POST['group'].'
;';
    list($group_name) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));

    $page['infos'][] = functions::l10n('An information email was sent to group "%s"', $group_name);
  }

  functions_url::unset_make_full_url();
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->set_filename('album_notification', 'album_notification.tpl');

$template->assign(
  array(
    'CATEGORIES_NAV' =>
      trim(
        functions_html::get_cat_display_name_from_id(
          $page['cat'],
          'admin.php?page=album-'
        )
      ),
    'F_ACTION' => $admin_album_base_url.'-notification',
    'PWG_TOKEN' => functions::get_pwg_token(),
    )
  );

if ($conf['auth_key_duration'] > 0)
{
  $template->assign(
    'auth_key_duration',
    functions::time_since(
      strtotime('now -'.$conf['auth_key_duration'].' second'),
      'second',
      null,
      false
      )
    );
}

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

$query = '
SELECT
    id AS group_id
  FROM `'.GROUPS_TABLE.'`
;';
$all_group_ids = functions::array_from_query($query, 'group_id');

if (count($all_group_ids) == 0)
{
  $template->assign('no_group_in_gallery', true);
}
else
{
  if ('private' == $category['status'])
  {
    $query = '
SELECT
    group_id
  FROM '.GROUP_ACCESS_TABLE.'
  WHERE cat_id = '.$category['id'].'
;';
    $group_ids = functions::array_from_query($query, 'group_id');

    if (count($group_ids) == 0)
    {
      $template->assign('permission_url', $admin_album_base_url.'-permissions');
    }
  }
  else
  {
    $group_ids = $all_group_ids;
  }

  if (count($group_ids) > 0)
  {
    $query = '
SELECT
    id,
    name
  FROM `'.GROUPS_TABLE.'`
  WHERE id IN ('.implode(',', $group_ids).')
  ORDER BY name ASC
;';
    $template->assign(
      'group_mail_options',
      functions::simple_hash_from_query($query, 'id', 'name')
      );
  }
}

// all users with status != guest and permitted to this this album (for a
// perfect search, we should also check that album is not only filled with
// private photos)
$query = '
SELECT
    user_id
  FROM '.USER_INFOS_TABLE.'
  WHERE status != \'guest\'
;';
$all_user_ids = functions_mysqli::query2array($query, null, 'user_id');

if ('private' == $category['status'])
{
  $user_ids_access_indirect = array();
  
  if (isset($group_ids) and count($group_ids) > 0)
  {
    $query = '
SELECT
    user_id
  FROM '.USER_GROUP_TABLE.'
  WHERE group_id IN ('.implode(',', $group_ids).') 
';
    $user_ids_access_indirect = functions_mysqli::query2array($query, null, 'user_id');
  }

  $query = '
SELECT
    user_id
  FROM '.USER_ACCESS_TABLE.'
  WHERE cat_id = '.$category['id'].'
;';
  $user_ids_access_direct = functions_mysqli::query2array($query, null, 'user_id');

  $user_ids_access = array_unique(array_merge($user_ids_access_direct, $user_ids_access_indirect));

  $user_ids = array_intersect($user_ids_access, $all_user_ids);
}
else
{
  $user_ids = $all_user_ids;
}

if (count($user_ids) > 0)
{
  $query = '
SELECT
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
  WHERE id IN ('.implode(',', $user_ids).')
;';

  $users = functions_mysqli::query2array($query, 'id', 'username');

  $template->assign('user_options', $users);
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'album_notification');
?>
