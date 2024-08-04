<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * API method
 * Returns permissions
 * @param mixed[] $params
 *    @option int[] cat_id (optional)
 *    @option int[] group_id (optional)
 *    @option int[] user_id (optional)
 */
function ws_permissions_getList($params, &$service)
{
    $my_params = array_intersect(array_keys($params), ['cat_id', 'group_id', 'user_id']);
    if (count($my_params) > 1) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'Too many parameters, provide cat_id OR user_id OR group_id');
    }

    $cat_filter = '';
    if (! empty($params['cat_id'])) {
        $cat_filter = 'WHERE cat_id IN (' . implode(',', $params['cat_id']) . ')';
    }

    $perms = [];

    // direct users
    $query = "SELECT user_id, cat_id FROM user_access {$cat_filter};";
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }
        $perms[$row['cat_id']]['users'][] = intval($row['user_id']);
    }

    // indirect users
    $query = "SELECT ug.user_id, ga.cat_id FROM user_group AS ug INNER JOIN group_access AS ga ON ug.group_id = ga.group_id {$cat_filter};";
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }
        $perms[$row['cat_id']]['users_indirect'][] = intval($row['user_id']);
    }

    // groups
    $query = "SELECT group_id, cat_id FROM group_access {$cat_filter};";
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }
        $perms[$row['cat_id']]['groups'][] = intval($row['group_id']);
    }

    // filter by group and user
    foreach ($perms as $cat_id => &$cat) {
        if (isset($params['group_id'])) {
            if (empty($cat['groups']) or count(array_intersect($cat['groups'], $params['group_id'])) == 0) {
                unset($perms[$cat_id]);
                continue;
            }
        }
        if (isset($params['user_id'])) {
            if (
                (empty($cat['users_indirect']) or count(array_intersect($cat['users_indirect'], $params['user_id'])) == 0)
                and (empty($cat['users']) or count(array_intersect($cat['users'], $params['user_id'])) == 0)
            ) {
                unset($perms[$cat_id]);
                continue;
            }
        }

        $cat['groups'] = ! empty($cat['groups']) ? array_values(array_unique($cat['groups'])) : [];
        $cat['users'] = ! empty($cat['users']) ? array_values(array_unique($cat['users'])) : [];
        $cat['users_indirect'] = ! empty($cat['users_indirect']) ? array_values(array_unique($cat['users_indirect'])) : [];
    }
    unset($cat);

    return [
        'categories' => new PwgNamedArray(
            array_values($perms),
            'category',
            ['id']
        ),
    ];
}

/**
 * API method
 * Add permissions
 * @param mixed[] $params
 *    @option int[] cat_id
 *    @option int[] group_id (optional)
 *    @option int[] user_id (optional)
 *    @option bool recursive
 */
function ws_permissions_add($params, &$service)
{
    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    if (! empty($params['group_id'])) {
        $cat_ids = get_uppercat_ids($params['cat_id']);
        if ($params['recursive']) {
            $cat_ids = array_merge($cat_ids, get_subcat_ids($params['cat_id']));
        }

        $cat_ids_ = implode(',', $cat_ids);
        $query = "SELECT id FROM categories WHERE id IN ({$cat_ids_}) AND status = 'private';";
        $private_cats = array_from_query($query, 'id');

        $inserts = [];
        foreach ($private_cats as $cat_id) {
            foreach ($params['group_id'] as $group_id) {
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

    if (! empty($params['user_id'])) {
        if ($params['recursive']) {
            $_POST['apply_on_sub'] = true;
        }
        add_permission_on_category($params['cat_id'], $params['user_id']);
    }

    return $service->invoke('pwg.permissions.getList', [
        'cat_id' => $params['cat_id'],
    ]);
}

/**
 * API method
 * Removes permissions
 * @param mixed[] $params
 *    @option int[] cat_id
 *    @option int[] group_id (optional)
 *    @option int[] user_id (optional)
 */
function ws_permissions_remove($params, &$service)
{
    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    $cat_ids = get_subcat_ids($params['cat_id']);

    if (! empty($params['group_id'])) {
        $group_id_ = implode(',', $params['group_id']);
        $cat_ids_ = implode(',', $cat_ids);
        $query = "DELETE FROM group_access WHERE group_id IN ({$group_id_}) AND cat_id IN ({$cat_ids_});";
        pwg_query($query);
    }

    if (! empty($params['user_id'])) {
        $user_id_ = implode(',', $params['user_id']);
        $cat_ids_ = implode(',', $cat_ids);
        $query = "DELETE FROM user_access WHERE user_id IN ({$user_id_}) AND cat_id IN ({$cat_ids_});";
        pwg_query($query);
    }

    return $service->invoke('pwg.permissions.getList', [
        'cat_id' => $params['cat_id'],
    ]);
}
