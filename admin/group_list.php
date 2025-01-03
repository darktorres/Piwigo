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

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('groups');
$tabsheet->select('group_list');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

if ($_POST !== [] || isset($_GET['delete']) || isset($_GET['toggle_is_default'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames([
    'group_list' => 'group_list.tpl',
]);

$template->assign(
    [
        'F_ADD_ACTION' => get_root_url() . 'admin.php?page=group_list',
        // 'U_HELP' => get_root_url().'admin/popuphelp.php?page=group_list',
        'PWG_TOKEN' => get_pwg_token(),
        'CACHE_KEYS' => get_admin_client_cache_keys(['groups', 'users']),
    ]
);

// +-----------------------------------------------------------------------+
// |                              group list                               |
// +-----------------------------------------------------------------------+

$query = <<<SQL
    SELECT id, name, is_default
    FROM groups_table
    ORDER BY name ASC;
    SQL;
$result = pwg_query($query);

$admin_url = get_root_url() . 'admin.php?page=';
$perm_url = $admin_url . 'group_perm&amp;group_id=';
$users_url = $admin_url . 'user_list&amp;group=';
$del_url = $admin_url . 'group_list&amp;delete=';
$toggle_is_default_url = $admin_url . 'group_list&amp;toggle_is_default=';

$group_counter = 0;

while ($row = pwg_db_fetch_assoc($result)) {
    $query = <<<SQL
        SELECT u.{$conf['user_fields']['username']} AS username
        FROM users AS u
        INNER JOIN user_group AS ug ON u.{$conf['user_fields']['id']} = ug.user_id
        WHERE ug.group_id = {$row['id']};
        SQL;
    $members = [];
    $res = pwg_query($query);
    while ($us = pwg_db_fetch_assoc($res)) {
        $members[] = $us['username'];
    }

    $template->append(
        'groups',
        [
            'NAME' => $row['name'],
            'ID' => $row['id'],
            'IS_DEFAULT' => (get_boolean($row['is_default']) ? ' [' . l10n('default') . ']' : ''),
            'NB_MEMBERS' => count($members),
            'L_MEMBERS' => implode(' <span class="userSeparator">&middot;</span> ', $members),
            'MEMBERS' => l10n_dec('%d member', '%d members', count($members)),
            'U_DELETE' => $del_url . $row['id'] . '&amp;pwg_token=' . get_pwg_token(),
            'U_PERM' => $perm_url . $row['id'],
            'U_USERS' => $users_url . $row['id'],
            'U_ISDEFAULT' => $toggle_is_default_url . $row['id'] . '&amp;pwg_token=' . get_pwg_token(),
        ]
    );

    $group_counter++;
}

$template->assign('ADMIN_PAGE_TITLE', l10n('Groups') . ' <span class="badge-number">' . $group_counter . '</span>');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'group_list');
