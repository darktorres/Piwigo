<?php

declare(strict_types=1);

namespace Piwigo\inc\ws_functions;

use Piwigo\inc\Error;
use Piwigo\inc\NamedArray;
use function Piwigo\admin\inc\add_permission_on_category;
use function Piwigo\admin\inc\get_uppercat_ids;
use function Piwigo\inc\dbLayer\mass_inserts;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_subcat_ids;
use const Piwigo\inc\WS_ERR_INVALID_PARAM;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * API method
 * Returns permissions
 * @option int[] cat_id (optional)
 * @option int[] group_id (optional)
 * @option int[] user_id (optional)
 */
function ws_permissions_getList(
    array $params,
    &$service
): array|Error {
    $my_params = array_intersect(array_keys($params), ['cat_id', 'group_id', 'user_id']);
    if (count($my_params) > 1) {
        return new Error(WS_ERR_INVALID_PARAM, 'Too many parameters, provide cat_id OR user_id OR group_id');
    }

    $cat_filter = '';
    if (! empty($params['cat_id'])) {
        $cat_filter = 'WHERE cat_id IN(' . implode(',', $params['cat_id']) . ')';
    }

    $perms = [];

    // direct users
    $query = '
SELECT user_id, cat_id
  FROM ' . USER_ACCESS_TABLE . '
  ' . $cat_filter . '
;';
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }

        $perms[$row['cat_id']]['users'][] = intval($row['user_id']);
    }

    // indirect users
    $query = '
SELECT ug.user_id, ga.cat_id
  FROM ' . USER_GROUP_TABLE . ' AS ug
    INNER JOIN ' . GROUP_ACCESS_TABLE . ' AS ga
    ON ug.group_id = ga.group_id
  ' . $cat_filter . '
;';
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }

        $perms[$row['cat_id']]['users_indirect'][] = intval($row['user_id']);
    }

    // groups
    $query = '
SELECT group_id, cat_id
  FROM ' . GROUP_ACCESS_TABLE . '
  ' . $cat_filter . '
;';
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($perms[$row['cat_id']])) {
            $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
        }

        $perms[$row['cat_id']]['groups'][] = intval($row['group_id']);
    }

    // filter by group and user
    foreach ($perms as $cat_id => &$cat) {
        if (isset($params['group_id']) && (empty($cat['groups']) || count(
            array_intersect($cat['groups'], $params['group_id'])
        ) == 0)) {
            unset($perms[$cat_id]);
            continue;
        }

        if (isset($params['user_id']) && ((empty($cat['users_indirect']) || count(
            array_intersect($cat['users_indirect'], $params['user_id'])
        ) == 0)
        && (empty($cat['users']) || count(array_intersect($cat['users'], $params['user_id'])) == 0))) {
            unset($perms[$cat_id]);
            continue;
        }

        $cat['groups'] = empty($cat['groups']) ? [] : array_values(array_unique($cat['groups']));
        $cat['users'] = empty($cat['users']) ? [] : array_values(array_unique($cat['users']));
        $cat['users_indirect'] = empty($cat['users_indirect']) ? [] : array_values(
            array_unique($cat['users_indirect'])
        );
    }

    unset($cat);

    return [
        'categories' => new NamedArray(
            array_values($perms),
            'category',
            ['id']
        ),
    ];
}

/**
 * API method
 * Add permissions
 * @option int[] cat_id
 * @option int[] group_id (optional)
 * @option int[] user_id (optional)
 * @option bool recursive
 */
function ws_permissions_add(
    array $params,
    $service
): mixed {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

    if (! empty($params['group_id'])) {
        $cat_ids = get_uppercat_ids($params['cat_id']);
        if ($params['recursive']) {
            $cat_ids = array_merge($cat_ids, get_subcat_ids($params['cat_id']));
        }

        $query = '
SELECT id
  FROM ' . CATEGORIES_TABLE . '
  WHERE id IN (' . implode(',', $cat_ids) . ')
    AND status = \'private\'
;';
        $private_cats = query2array($query, null, 'id');

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
            GROUP_ACCESS_TABLE,
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
 * @option int[] cat_id
 * @option int[] group_id (optional)
 * @option int[] user_id (optional)
 */
function ws_permissions_remove(
    array $params,
    $service
): mixed {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

    $cat_ids = get_subcat_ids($params['cat_id']);

    if (! empty($params['group_id'])) {
        $query = '
DELETE
  FROM ' . GROUP_ACCESS_TABLE . '
  WHERE group_id IN (' . implode(',', $params['group_id']) . ')
    AND cat_id IN (' . implode(',', $cat_ids) . ')
;';
        pwg_query($query);
    }

    if (! empty($params['user_id'])) {
        $query = '
DELETE
  FROM ' . USER_ACCESS_TABLE . '
  WHERE user_id IN (' . implode(',', $params['user_id']) . ')
    AND cat_id IN (' . implode(',', $cat_ids) . ')
;';
        pwg_query($query);
    }

    return $service->invoke('pwg.permissions.getList', [
        'cat_id' => $params['cat_id'],
    ]);
}
