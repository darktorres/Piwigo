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

if (! empty($_POST)) {
    check_pwg_token();

    if ($category['status'] != $_POST['status'] or ($category['status'] != 'public' and isset($_POST['apply_on_sub']))) {
        $cat_ids = [$page['cat']];
        if (isset($_POST['apply_on_sub'])) {
            $cat_ids = array_merge($cat_ids, get_subcat_ids([$page['cat']]));
        }
        set_cat_status($cat_ids, $_POST['status']);
        $category['status'] = $_POST['status'];
    }

    if ($_POST['status'] == 'private') {
        //
        // manage groups
        //
        $query = <<<SQL
            SELECT group_id
            FROM group_access
            WHERE cat_id = {$page['cat']};
            SQL;
        $groups_granted = query2array($query, null, 'group_id');

        if (! isset($_POST['groups'])) {
            $_POST['groups'] = [];
        }

        //
        // remove permissions to groups
        //
        $deny_groups = array_diff($groups_granted, $_POST['groups']);
        if (count($deny_groups) > 0) {
            // if you forbid access to an album, all sub-albums become
            // automatically forbidden
            $imploded_deny_groups = implode(',', $deny_groups);
            $imploded_subcat_ids = implode(',', get_subcat_ids([$page['cat']]));
            $query = <<<SQL
                DELETE FROM group_access
                WHERE group_id IN ({$imploded_deny_groups})
                    AND cat_id IN ({$imploded_subcat_ids});
                SQL;
            pwg_query($query);
        }

        //
        // add permissions to groups
        //
        $grant_groups = $_POST['groups'];
        if (count($grant_groups) > 0) {
            $cat_ids = get_uppercat_ids([$page['cat']]);
            if (isset($_POST['apply_on_sub'])) {
                $cat_ids = array_merge($cat_ids, get_subcat_ids([$page['cat']]));
            }

            $imploded_cat_ids = implode(',', $cat_ids);
            $query = <<<SQL
                SELECT id
                FROM categories
                WHERE id IN ({$imploded_cat_ids})
                    AND status = 'private';
                SQL;
            $private_cats = query2array($query, null, 'id');

            $inserts = [];
            foreach ($private_cats as $cat_id) {
                foreach ($grant_groups as $group_id) {
                    $inserts[] = [
                        'group_id' => $group_id,
                        'cat_id' => $cat_id,
                    ];
                }
            }

            mass_inserts(
                'group_access',
                ['group_id', 'cat_id'],
                $inserts,
                [
                    'ignore' => true,
                ]
            );
        }

        //
        // users
        //
        $query = <<<SQL
            SELECT user_id
            FROM user_access
            WHERE cat_id = {$page['cat']};
            SQL;
        $users_granted = query2array($query, null, 'user_id');

        if (! isset($_POST['users'])) {
            $_POST['users'] = [];
        }

        //
        // remove permissions to users
        //
        $deny_users = array_diff($users_granted, $_POST['users']);
        if (count($deny_users) > 0) {
            // if you forbid access to an album, all sub-album become automatically
            // forbidden
            $deny_users_imploded = implode(',', $deny_users);
            $subcat_ids_imploded = implode(',', get_subcat_ids([$page['cat']]));
            $query = <<<SQL
                DELETE FROM user_access
                WHERE user_id IN ({$deny_users_imploded})
                    AND cat_id IN ({$subcat_ids_imploded});
                SQL;
            pwg_query($query);
        }

        //
        // add permissions to users
        //
        $grant_users = $_POST['users'];
        if (count($grant_users) > 0) {
            add_permission_on_category($page['cat'], $grant_users);
        }
    }

    $page['infos'][] = l10n('Album updated successfully');
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->set_filename('cat_perm', 'cat_perm.tpl');

$template->assign(
    [
        'CATEGORIES_NAV' =>
          get_cat_display_name_from_id(
              $page['cat'],
              'admin.php?page=album-'
          ),
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=cat_perm',
        'F_ACTION' => $admin_album_base_url . '-permissions',
        'private' => ($category['status'] == 'private'),
    ]
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

// groups denied are the groups not granted. So we need to find all groups
// minus groups granted to find groups denied.

$groups = [];

$query = <<<SQL
    SELECT id, name
    FROM groups_table
    ORDER BY name ASC;
    SQL;
$groups = query2array($query, 'id', 'name');
$template->assign('groups', $groups);

// groups granted to access the category
$query = <<<SQL
    SELECT group_id
    FROM group_access
    WHERE cat_id = {$page['cat']};
    SQL;
$group_granted_ids = query2array($query, null, 'group_id');
$template->assign('groups_selected', $group_granted_ids);

// users...
$users = [];

$query = <<<SQL
    SELECT {$conf['user_fields']['id']} AS id, {$conf['user_fields']['username']} AS username
    FROM users;
    SQL;
$users = query2array($query, 'id', 'username');
$template->assign('users', $users);

$query = <<<SQL
    SELECT user_id
    FROM user_access
    WHERE cat_id = {$page['cat']};
    SQL;
$user_granted_direct_ids = query2array($query, null, 'user_id');
$template->assign('users_selected', $user_granted_direct_ids);

$user_granted_indirect_ids = [];
if (count($group_granted_ids) > 0) {
    $granted_groups = [];

    $group_granted_ids_imploded = implode(',', $group_granted_ids);
    $query = <<<SQL
        SELECT user_id, group_id
        FROM user_group
        WHERE group_id IN ({$group_granted_ids_imploded});
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($granted_groups[$row['group_id']])) {
            $granted_groups[$row['group_id']] = [];
        }
        $granted_groups[$row['group_id']][] = $row['user_id'];
    }

    $user_granted_by_group_ids = [];

    foreach ($granted_groups as $group_users) {
        $user_granted_by_group_ids = array_merge($user_granted_by_group_ids, $group_users);
    }

    $user_granted_by_group_ids = array_unique($user_granted_by_group_ids);

    $user_granted_indirect_ids = array_diff(
        $user_granted_by_group_ids,
        $user_granted_direct_ids
    );

    $template->assign('nb_users_granted_indirect', count($user_granted_indirect_ids));

    foreach ($granted_groups as $group_id => $group_users) {
        $group_usernames = [];
        foreach ($group_users as $user_id) {
            if (in_array($user_id, $user_granted_indirect_ids)) {
                $group_usernames[] = $users[$user_id];
            }
        }

        $template->append(
            'user_granted_indirect_groups',
            [
                'group_name' => $groups[$group_id],
                'group_users' => implode(', ', $group_usernames),
            ]
        );
    }
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
$template->assign([
    'PWG_TOKEN' => get_pwg_token(),
    'INHERIT' => $conf['inheritance_by_default'],
    'CACHE_KEYS' => get_admin_client_cache_keys(['groups', 'users']),
]);

$template->assign_var_from_handle('ADMIN_CONTENT', 'cat_perm');
