<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\inc\DerivativeImage;
use function Piwigo\inc\add_url_params;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_status;
use function Piwigo\inc\create_user_auth_key;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_db_num_rows;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\get_cat_display_name_from_id;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\l10n;
use function Piwigo\inc\l10n_dec;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\make_picture_url;
use function Piwigo\inc\pwg_mail;
use function Piwigo\inc\pwg_mail_group;
use function Piwigo\inc\set_make_full_url;
use function Piwigo\inc\switch_lang_back;
use function Piwigo\inc\switch_lang_to;
use function Piwigo\inc\time_since;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\unset_make_full_url;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\IMG_THUMB;
use const Piwigo\inc\PATTERN_ID;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'inc/functions_mail.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                       variable initialization                         |
// +-----------------------------------------------------------------------+

$page['cat'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                           form submission                             |
// +-----------------------------------------------------------------------+

// info by email to an access granted group of category informations
if (isset($_POST['submitEmail'])) {
    set_make_full_url();

    $img = [];

    /* TODO: if $category['representative_picture_id']
      is empty find child representative_picture_id */
    if (! empty($category['representative_picture_id'])) {
        $query = '
SELECT id, file, path, representative_ext
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $category['representative_picture_id'] . '
;';

        $result = pwg_query($query);
        if (pwg_db_num_rows($result) > 0) {
            $element = pwg_db_fetch_assoc($result);

            $img = [
                'link' => make_picture_url(
                    [
                        'image_id' => $element['id'],
                        'image_file' => $element['file'],
                        'category' => $category,
                    ]
                ),
                'src' => DerivativeImage::url(IMG_THUMB, $element),
            ];
        }
    }

    $args = [
        'subject' => l10n(
            '[%s] Visit album %s',
            $conf['gallery_title'],
            trigger_change('render_category_name', $category['name'], 'admin_cat_list')
        ),
        // TODO : change this language variable to 'Visit album %s'
        // TODO : 'language_selected' => ....
    ];

    $tpl = [
        'filename' => 'cat_group_info',
        'assign' => [
            'IMG' => $img,
            'CAT_NAME' => trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
            'LINK' => make_index_url(
                [
                    'category' => [
                        'id' => $category['id'],
                        'name' => trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                        'permalink' => $category['permalink'],
                    ],
                ]
            ),
            'CPL_CONTENT' => empty($_POST['mail_content']) ? '' : stripslashes((string) $_POST['mail_content']),
        ],
    ];

    if ($_POST['who'] == 'users' && isset($_POST['users']) && count($_POST['users']) > 0) {
        check_input_parameter('users', $_POST, true, PATTERN_ID);

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
    u.' . $conf['user_fields']['email'] . ' AS email,
    u.' . $conf['user_fields']['username'] . ' AS username
  FROM ' . USER_INFOS_TABLE . ' AS ui
    JOIN ' . USERS_TABLE . ' AS u ON u.' . $conf['user_fields']['id'] . ' = ui.user_id
  WHERE ui.user_id IN (' . implode(',', $_POST['users']) . ')
;';
        $users = query2array($query);
        $usernames = [];

        foreach ($users as $u) {
            $usernames[] = $u['username'];

            $authkey = create_user_auth_key($u['user_id'], $u['status']);

            $user_tpl = $tpl;

            if ($authkey !== false) {
                $user_tpl['assign']['LINK'] = add_url_params($tpl['assign']['LINK'], [
                    'auth' => $authkey['auth_key'],
                ]);

                if (isset($user_tpl['assign']['IMG']['link'])) {
                    $user_tpl['assign']['IMG']['link'] = add_url_params(
                        $user_tpl['assign']['IMG']['link'],
                        [
                            'auth' => $authkey['auth_key'],
                        ]
                    );
                }
            }

            $user_args = $args;
            if (isset($authkey)) {
                $user_args['auth_key'] = $authkey['auth_key'];
            }

            switch_lang_to($u['language']);
            pwg_mail($u['email'], $user_args, $user_tpl);
            switch_lang_back();
        }

        $message = l10n_dec('%d mail was sent.', '%d mails were sent.', count($users));
        $message .= ' (' . implode(', ', $usernames) . ')';

        $page['infos'][] = $message;
    } elseif ($_POST['who'] == 'group' && ! empty($_POST['group'])) {
        check_input_parameter('group', $_POST, false, PATTERN_ID);

        pwg_mail_group($_POST['group'], $args, $tpl);

        $query = '
SELECT
    name
  FROM ' . GROUPS_TABLE . '
  WHERE id = ' . $_POST['group'] . '
;';
        [$group_name] = pwg_db_fetch_row(pwg_query($query));

        $page['infos'][] = l10n('An information email was sent to group "%s"', $group_name);
    }

    unset_make_full_url();
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->set_filename(
    'album_notification',
    'album_notification.tpl'
);

$template->assign(
    [
        'CATEGORIES_NAV' =>
          trim(
              get_cat_display_name_from_id(
                  $page['cat'],
                  'admin.php?page=album-'
              )
          ),
        'F_ACTION' => $admin_album_base_url . '-notification',
        'PWG_TOKEN' => get_pwg_token(),
    ]
);

if ($conf['auth_key_duration'] > 0) {
    $template->assign(
        'auth_key_duration',
        time_since(
            strtotime('now -' . $conf['auth_key_duration'] . ' second'),
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
  FROM ' . GROUPS_TABLE . '
;';
$all_group_ids = query2array($query, null, 'group_id');

if (count($all_group_ids) == 0) {
    $template->assign('no_group_in_gallery', true);
} else {
    if ($category['status'] == 'private') {
        $query = '
SELECT
    group_id
  FROM ' . GROUP_ACCESS_TABLE . '
  WHERE cat_id = ' . $category['id'] . '
;';
        $group_ids = query2array($query, null, 'group_id');

        if (count($group_ids) == 0) {
            $template->assign('permission_url', $admin_album_base_url . '-permissions');
        }
    } else {
        $group_ids = $all_group_ids;
    }

    if ($group_ids !== []) {
        $query = '
SELECT
    id,
    name
  FROM ' . GROUPS_TABLE . '
  WHERE id IN (' . implode(',', $group_ids) . ')
  ORDER BY name ASC
;';
        $template->assign(
            'group_mail_options',
            query2array($query, 'id', 'name')
        );
    }
}

// all users with status != guest and permitted to this this album (for a
// perfect search, we should also check that album is not only filled with
// private photos)
$query = '
SELECT
    user_id
  FROM ' . USER_INFOS_TABLE . '
  WHERE status != \'guest\'
;';
$all_user_ids = query2array($query, null, 'user_id');

if ($category['status'] == 'private') {
    $user_ids_access_indirect = [];

    if (isset($group_ids) && count($group_ids) > 0) {
        $query = '
SELECT
    user_id
  FROM ' . USER_GROUP_TABLE . '
  WHERE group_id IN (' . implode(',', $group_ids) . ') 
';
        $user_ids_access_indirect = query2array($query, null, 'user_id');
    }

    $query = '
SELECT
    user_id
  FROM ' . USER_ACCESS_TABLE . '
  WHERE cat_id = ' . $category['id'] . '
;';
    $user_ids_access_direct = query2array($query, null, 'user_id');

    $user_ids_access = array_unique(array_merge($user_ids_access_direct, $user_ids_access_indirect));

    $user_ids = array_intersect($user_ids_access, $all_user_ids);
} else {
    $user_ids = $all_user_ids;
}

if ($user_ids !== []) {
    $query = '
SELECT
    ' . $conf['user_fields']['id'] . ' AS id,
    ' . $conf['user_fields']['username'] . ' AS username
  FROM ' . USERS_TABLE . '
  WHERE id IN (' . implode(',', $user_ids) . ')
;';

    $users = query2array($query, 'id', 'username');

    $template->assign('user_options', $users);
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle(
    'ADMIN_CONTENT',
    'album_notification'
);
