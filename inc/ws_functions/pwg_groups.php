<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc\ws_functions;

use Piwigo\admin\inc\functions_admin;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\PwgError;
use Piwigo\inc\PwgNamedArray;
use Piwigo\inc\PwgNamedStruct;

class pwg_groups
{
    /**
     * API method
     * Returns the list of groups
     * @param array{
     *     group_id?: int[],
     *     name?: string,
     *     order: mixed,
     *     per_page: mixed,
     *     page: mixed,
     * } $params
     */
    public static function ws_groups_getList($params, &$service)
    {
        if (! preg_match(PATTERN_ORDER, $params['order'])) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid input parameter order');
        }

        $where_clauses = ['1=1'];

        if (! empty($params['name'])) {
            $where_clauses[] = 'LOWER(name) LIKE \'' . functions_mysqli::pwg_db_real_escape_string($params['name']) . '\'';
        }

        if (! empty($params['group_id'])) {
            $where_clauses[] = 'id IN(' . implode(',', $params['group_id']) . ')';
        }

        $whereClause = implode(' AND ', $where_clauses);
        $offset = $params['per_page'] * $params['page'];
        $query = <<<SQL
            SELECT g.*, COUNT(user_id) AS nb_users
            FROM `groups` AS g
            LEFT JOIN user_group AS ug ON ug.group_id = g.id
            WHERE {$whereClause}
            GROUP BY id
            ORDER BY {$params['order']}
            LIMIT {$params['per_page']} OFFSET {$offset};
            SQL;

        $groups = functions::array_from_query($query);

        return [
            'paging' => new PwgNamedStruct([
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($groups),
            ]),
            'groups' => new PwgNamedArray($groups, 'group'),
        ];
    }

    /**
     * API method
     * Adds a group
     * @param array{
     *     name: string,
     *     is_default: bool,
     * } $params
     */
    public static function ws_groups_add($params, &$service)
    {
        $params['name'] = functions_mysqli::pwg_db_real_escape_string(strip_tags(stripslashes($params['name'])));

        // is the name not already used ?
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE name = '{$params['name']}';
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count != 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        if (strlen(str_replace(' ', '', $params['name'])) == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'Name field must not be empty');
        }

        // creating the group
        functions_mysqli::single_insert(
            'groups',
            [
                'name' => $params['name'],
                'is_default' => functions_mysqli::boolean_to_string($params['is_default']),
            ]
        );
        $inserted_id = functions_mysqli::pwg_db_insert_id();

        functions::pwg_activity('group', $inserted_id, 'add');

        return $service->invoke('pwg.groups.getList', [
            'group_id' => $inserted_id,
        ]);
    }

    /**
     * API method
     * Deletes a group
     * @param array{
     *     group_id: int[],
     *     pwg_token: string,
     * } $params
     */
    public static function ws_groups_delete($params, &$service)
    {
        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');
        $groupnames = array_values(functions_admin::delete_groups($params['group_id']));

        functions_admin::invalidate_user_cache();

        return new PwgNamedArray($groupnames, 'group_deleted');
    }

    /**
     * API method
     * Updates a group
     * @param array{
     *     group_id: int,
     *     name?: string,
     *     is_default?: bool,
     *     pwg_token: mixed,
     * } $params
     */
    public static function ws_groups_setInfo($params, &$service)
    {
        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        if (isset($params['name']) && strlen(str_replace(' ', '', $params['name'])) == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'Name field must not be empty');
        }

        $updates = [];

        // does the group exist ?
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE id = {$params['group_id']};
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        if (! empty($params['name'])) {
            $params['name'] = functions_mysqli::pwg_db_real_escape_string(strip_tags(stripslashes($params['name'])));

            // is the name not already used ?
            $query = <<<SQL
                SELECT COUNT(*)
                FROM `groups`
                WHERE name = '{$params['name']}'
                    AND id != {$params['group_id']};
                SQL;
            list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
            if ($count != 0) {
                return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
            }

            $updates['name'] = $params['name'];
        }

        if (! empty($params['is_default']) or @$params['is_default'] === false) {
            $updates['is_default'] = functions_mysqli::boolean_to_string($params['is_default']);
        }

        functions_mysqli::single_update(
            'groups',
            $updates,
            [
                'id' => $params['group_id'],
            ]
        );

        functions::pwg_activity('group', $params['group_id'], 'edit');

        return $service->invoke('pwg.groups.getList', [
            'group_id' => $params['group_id'],
        ]);
    }

    /**
     * API method
     * Adds user(s) to a group
     * @param array{
     *     group_id: int,
     *     user_id: int[],
     *     pwg_token: mixed,
     * } $params
     */
    public static function ws_groups_addUser($params, &$service)
    {
        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        // does the group exist ?
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE id = {$params['group_id']};
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $inserts = [];
        foreach ($params['user_id'] as $user_id) {
            $inserts[] = [
                'group_id' => $params['group_id'],
                'user_id' => $user_id,
            ];
        }

        functions_mysqli::mass_inserts(
            'user_group',
            ['group_id', 'user_id'],
            $inserts
        );

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');
        functions_admin::invalidate_user_cache();

        functions::pwg_activity('group', $params['group_id'], 'edit');
        functions::pwg_activity('user', $params['user_id'], 'edit');

        return $service->invoke('pwg.groups.getList', [
            'group_id' => $params['group_id'],
        ]);
    }

    /**
     * API method
     * Merge groups in one other group
     * @param array{
     *     destination_group_id: int,
     *     merge_group_id: int[],
     *     pwg_token: mixed,
     * } $params
     */
    public static function ws_groups_merge($params, &$service)
    {

        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        $all_groups = $params['merge_group_id'];
        $all_groups[] = $params['destination_group_id'];

        $all_groups = array_unique($all_groups);
        $merge_group = array_diff($params['merge_group_id'], [$params['destination_group_id']]);
        $merge_group_object = $service->invoke('pwg.groups.getList', [
            'group_id' => $params['merge_group_id'],
        ]);

        $allGroupsList = implode(',', $all_groups);
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE id IN ({$allGroupsList});
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count != count($all_groups)) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'All groups does not exist.');
        }

        $user_in_merge_groups = [];
        $user_in_dest = [];
        $user_to_add = [];

        $mergeGroupList = implode(',', $merge_group);
        $query = <<<SQL
            SELECT DISTINCT(user_id)
            FROM user_group
            WHERE group_id IN ({$mergeGroupList});
            SQL;
        $user_in_merge_groups = functions_mysqli::query2array($query, null, 'user_id');

        $query = <<<SQL
            SELECT user_id
            FROM user_group
            WHERE group_id = {$params['destination_group_id']};
            SQL;

        $user_in_dest = functions_mysqli::query2array($query, null, 'user_id');

        $user_to_add = array_diff($user_in_merge_groups, $user_in_dest);

        $inserts = [];
        foreach ($user_to_add as $user) {
            $inserts[] = [
                'group_id' => $params['destination_group_id'],
                'user_id' => $user,
            ];
        }

        functions_mysqli::mass_inserts(
            'user_group',
            ['group_id', 'user_id'],
            $inserts,
            [
                'ignore' => true,
            ]
        );

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');
        functions_admin::invalidate_user_cache();

        functions::pwg_activity('group', $params['destination_group_id'], 'edit');
        foreach ($user_to_add as $user_id) {
            functions::pwg_activity('user', $user_id, 'edit', [
                'associated' => $params['destination_group_id'],
            ]);
        }

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');

        functions_admin::delete_groups($merge_group);

        return [
            'destination_group' => $service->invoke('pwg.groups.getList', [
                'group_id' => $params['destination_group_id'],
            ]),
            'deleted_group' => $merge_group_object,
        ];
    }

    /**
     * API method
     * Create a copy of a group
     * @param array{
     *     group_id: int,
     *     copy_name: string,
     *     pwg_token: mixed,
     * } $params
     */
    public static function ws_groups_duplicate($params, &$service)
    {

        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        $escapedCopyName = functions_mysqli::pwg_db_real_escape_string($params['copy_name']);
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE name = '{$escapedCopyName}';
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count != 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already used by another group.');
        }

        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE id = {$params['group_id']};
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $query = <<<SQL
            SELECT is_default
            FROM `groups`
            WHERE id = {$params['group_id']};
            SQL;

        list($is_default) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));

        // creating the group
        functions_mysqli::single_insert(
            'groups',
            [
                'name' => $params['copy_name'],
                'is_default' => functions_mysqli::boolean_to_string($is_default),
            ]
        );
        $inserted_id = functions_mysqli::pwg_db_insert_id();

        functions::pwg_activity('group', $inserted_id, 'add');

        $query = <<<SQL
            SELECT user_id
            FROM user_group
            WHERE group_id = {$params['group_id']};
            SQL;

        $users = functions_mysqli::query2array($query, null, 'user_id');

        $inserts = [];
        foreach ($users as $user) {
            $inserts[] = [
                'group_id' => $inserted_id,
                'user_id' => $user,
            ];
        }

        functions_mysqli::mass_inserts(
            'user_group',
            ['group_id', 'user_id'],
            $inserts,
            [
                'ignore' => true,
            ]
        );

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');
        functions_admin::invalidate_user_cache();

        foreach ($users as $user_id) {
            functions::pwg_activity('user', $user_id, 'edit', [
                'associated' => $params['group_id'],
            ]);
        }

        return $service->invoke('pwg.groups.getList', [
            'group_id' => $inserted_id,
        ]);
    }

    /**
     * API method
     * Removes user(s) from a group
     * @param array{
     *     group_id: int,
     *     user_id: int[],
     *     pwg_token: mixed,
     * } $params
     */
    public static function ws_groups_deleteUser($params, &$service)
    {
        if (functions::get_pwg_token() != $params['pwg_token']) {
            return new PwgError(403, 'Invalid security token');
        }

        // does the group exist ?
        $query = <<<SQL
            SELECT COUNT(*)
            FROM `groups`
            WHERE id = {$params['group_id']};
            SQL;
        list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
        if ($count == 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'This group does not exist.');
        }

        $userIdsList = implode(',', $params['user_id']);
        $query = <<<SQL
            DELETE FROM user_group
            WHERE group_id = {$params['group_id']}
                AND user_id IN ({$userIdsList});
            SQL;
        functions_mysqli::pwg_query($query);

        include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');
        functions_admin::invalidate_user_cache();

        functions::pwg_activity('group', $params['group_id'], 'edit');
        functions::pwg_activity('user', $params['user_id'], 'edit');

        return $service->invoke('pwg.groups.getList', [
            'group_id' => $params['group_id'],
        ]);
    }
}
