<?php

declare(strict_types=1);

namespace Piwigo\inc\ws_functions;

use Piwigo\inc\Error;
use Piwigo\inc\NamedArray;
use Piwigo\inc\NamedStruct;
use function Piwigo\admin\inc\delete_user;
use function Piwigo\admin\inc\get_username;
use function Piwigo\admin\inc\invalidate_user_cache;
use function Piwigo\inc\check_user_favorites;
use function Piwigo\inc\create_user_auth_key;
use function Piwigo\inc\dbLayer\boolean_to_string;
use function Piwigo\inc\dbLayer\get_boolean;
use function Piwigo\inc\dbLayer\get_enums;
use function Piwigo\inc\dbLayer\mass_inserts;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_db_real_escape_string;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\dbLayer\single_insert;
use function Piwigo\inc\dbLayer\single_update;
use function Piwigo\inc\deactivate_password_reset_key;
use function Piwigo\inc\deactivate_user_auth_keys;
use function Piwigo\inc\delete_user_sessions;
use function Piwigo\inc\format_date;
use function Piwigo\inc\get_languages;
use function Piwigo\inc\get_pwg_themes;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_sql_condition_FandF;
use function Piwigo\inc\get_user_last_visit_from_history;
use function Piwigo\inc\get_userid;
use function Piwigo\inc\is_a_guest;
use function Piwigo\inc\is_webmaster;
use function Piwigo\inc\l10n;
use function Piwigo\inc\l10n_dec;
use function Piwigo\inc\pwg_activity;
use function Piwigo\inc\register_user;
use function Piwigo\inc\time_since;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\userprefs_update_param;
use function Piwigo\inc\validate_mail_address;
use function Piwigo\inc\ws_std_get_image_xml_attributes;
use function Piwigo\inc\ws_std_get_urls;
use function Piwigo\inc\ws_std_image_sql_order;
use const Piwigo\inc\PATTERN_ORDER;
use const Piwigo\inc\WS_ERR_INVALID_PARAM;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * API method
 * Returns a list of users
 * @option int[] user_id (optional)
 * @option string username (optional)
 * @option string[] status (optional)
 * @option int min_level (optional)
 * @option int max_level (optional)
 * @option int[] group_id (optional)
 * @option int per_page
 * @option int page
 * @option string order
 * @option string display
 * @option string filter
 * @option int[] exclude (optional)
 * @option string min_register
 * @option string max_register
 */
function ws_users_getList(
    array $params,
    &$service
): Error|array {
    global $conf;

    if (! preg_match(PATTERN_ORDER, (string) $params['order'])) {
        return new Error(WS_ERR_INVALID_PARAM, 'Invalid input parameter order');
    }

    $where_clauses = ['1=1'];

    if (! empty($params['user_id'])) {
        $where_clauses[] = 'u.' . $conf['user_fields']['id'] . ' IN(' . implode(',', $params['user_id']) . ')';
    }

    if (! empty($params['username'])) {
        $where_clauses[] = 'u.' . $conf['user_fields']['username'] . " LIKE '" . pwg_db_real_escape_string(
            $params['username']
        ) . "'";
    }

    $filtered_groups = [];
    if (! empty($params['filter'])) {
        $filter_query = 'SELECT id FROM ' . GROUPS_TABLE . " WHERE name LIKE '%" . $params['filter'] . "%';";
        $filtered_groups_res = pwg_query($filter_query);
        while ($row = pwg_db_fetch_assoc($filtered_groups_res)) {
            $filtered_groups[] = $row['id'];
        }

        $filter_where_clause = '(u.' . $conf['user_fields']['username'] . " LIKE '%" .
        pwg_db_real_escape_string($params['filter']) . "%' OR "
        . 'u.' . $conf['user_fields']['email'] . " LIKE '%" .
        pwg_db_real_escape_string($params['filter']) . "%'";

        if ($filtered_groups !== []) {
            $filter_where_clause .= 'OR ug.group_id IN (' . implode(',', $filtered_groups) . ')';
        }

        $where_clauses[] = $filter_where_clause . ')';
    }

    if (! empty($params['min_register'])) {
        [$min_register_month, $min_register_year] = explode(' ', (string) $params['min_register']);
        if (strlen($min_register_month) == 1) {
            $min_register_month = '0' . $min_register_month;
        }

        $where_clauses[] = "ui.registration_date >= '" . $min_register_year . '-' . $min_register_month . "-01 00:00:00'";
    }

    if (! empty($params['max_register'])) {
        [$max_register_month, $max_register_year] = explode(' ', (string) $params['max_register']);
        if (strlen($max_register_month) == 1) {
            $max_register_month = '0' . $max_register_month;
        }

        $where_clauses[] = "ui.registration_date <= adddate('" . $max_register_year . '-' . $max_register_month . "-01 00:00:00', interval 1 month)";
    }

    if (! empty($params['status'])) {
        $params['status'] = array_intersect($params['status'], get_enums(USER_INFOS_TABLE, 'status'));
        if ($params['status'] !== []) {
            $where_clauses[] = 'ui.status IN("' . implode('","', $params['status']) . '")';
        }
    }

    if (! empty($params['min_level'])) {
        if (! in_array($params['min_level'], $conf['available_permission_levels'])) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid level');
        }

        $where_clauses[] = 'ui.level >= ' . $params['min_level'];
    }

    if (! empty($params['max_level'])) {
        if (! in_array($params['max_level'], $conf['available_permission_levels'])) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid level');
        }

        $where_clauses[] = 'ui.level <= ' . $params['max_level'];
    }

    if (! empty($params['group_id'])) {
        $where_clauses[] = 'ug.group_id IN(' . implode(',', $params['group_id']) . ')';
    }

    if (! empty($params['exclude'])) {
        $where_clauses[] = 'u.' . $conf['user_fields']['id'] . ' NOT IN(' . implode(',', $params['exclude']) . ')';
    }

    $display = [
        'u.' . $conf['user_fields']['id'] => 'id',
    ];

    if ($params['display'] != 'none') {
        $params['display'] = array_map('trim', explode(',', (string) $params['display']));

        if (in_array('all', $params['display'])) {
            $params['display'] = [
                'username', 'email', 'status', 'level', 'groups', 'language', 'theme',
                'nb_image_page', 'recent_period', 'expand', 'show_nb_comments', 'show_nb_hits',
                'enabled_high', 'registration_date', 'registration_date_string',
                'registration_date_since', 'last_visit', 'last_visit_string',
                'last_visit_since', 'total_count',
            ];
        } elseif (in_array('basics', $params['display'])) {
            $params['display'] = array_merge($params['display'], [
                'username', 'email', 'status', 'level', 'groups',
            ]);
        } elseif (in_array('only_id', $params['display'])) {
            $params['display'] = [];
        }

        $params['display'] = array_flip($params['display']);

        // if registration_date_string or registration_date_since is requested,
        // then registration_date is automatically added
        if (isset($params['display']['registration_date_string']) || isset($params['display']['registration_date_since'])) {
            $params['display']['registration_date'] = true;
        }

        // if last_visit_string or last_visit_since is requested, then
        // last_visit is automatically added
        if (isset($params['display']['last_visit_string']) || isset($params['display']['last_visit_since'])) {
            $params['display']['last_visit'] = true;
        }

        if (isset($params['display']['username'])) {
            $display['u.' . $conf['user_fields']['username']] = 'username';
        }

        if (isset($params['display']['email'])) {
            $display['u.' . $conf['user_fields']['email']] = 'email';
        }

        $ui_fields = [
            'status', 'level', 'language', 'theme', 'nb_image_page', 'recent_period', 'expand',
            'show_nb_comments', 'show_nb_hits', 'enabled_high', 'registration_date',
            'last_visit',
        ];
        foreach ($ui_fields as $field) {
            if (isset($params['display'][$field])) {
                $display['ui.' . $field] = $field;
            }
        }
    } else {
        $params['display'] = [];
    }

    $query = '
SELECT DISTINCT ';

    // ADD SQL_CALC_FOUND_ROWS if display total_count is requested
    if (isset($params['display']['total_count'])) {
        $query .= 'SQL_CALC_FOUND_ROWS ';
    }

    $first = true;
    foreach ($display as $field => $name) {
        if (! $first) {
            $query .= ', ';
        } else {
            $first = false;
        }

        $query .= $field . ' AS ' . $name;
    }

    if (isset($display['ui.last_visit'])) {
        if (! $first) {
            $query .= ', ';
        }

        $query .= 'ui.last_visit_from_history AS last_visit_from_history';
    }

    $query .= '
  FROM ' . USERS_TABLE . ' AS u
    INNER JOIN ' . USER_INFOS_TABLE . ' AS ui
      ON u.' . $conf['user_fields']['id'] . ' = ui.user_id
    LEFT JOIN ' . USER_GROUP_TABLE . ' AS ug
      ON u.' . $conf['user_fields']['id'] . ' = ug.user_id
  WHERE
    ' . implode(' AND ', $where_clauses) . '
  ORDER BY ' . $params['order'];
    if ($params['per_page'] != 0 || isset($params['display']) && $params['display'] !== []) {
        $query .= '
    LIMIT ' . $params['per_page'] . '
    OFFSET ' . ($params['per_page'] * $params['page']) . ';
    ;';
    }

    $users = [];
    $result = pwg_query($query);

    /* GET THE RESULT OF SQL_CALC_FOUND_ROWS if display total_count is requested*/
    if (isset($params['display']['total_count'])) {
        $total_count_query_result = pwg_query('SELECT FOUND_ROWS();');
        [$total_count] = pwg_db_fetch_row($total_count_query_result);
    }

    while ($row = pwg_db_fetch_assoc($result)) {
        $row['id'] = intval($row['id']);
        if (isset($params['display']['groups'])) {
            $row['groups'] = []; // will be filled later
        }

        $users[$row['id']] = $row;
    }

    $users_id_arr = [];
    if ($users !== []) {
        if (isset($params['display']['groups'])) {
            $query = '
  SELECT user_id, group_id
  FROM ' . USER_GROUP_TABLE . '
  WHERE user_id IN (' . implode(',', array_keys($users)) . ')
;';
            $result = pwg_query($query);
            while ($row = pwg_db_fetch_assoc($result)) {
                $users[$row['user_id']]['groups'][] = intval($row['group_id']);
            }
        }

        foreach ($users as $cur_user) {
            $users_id_arr[] = $cur_user['id'];
            if (isset($params['display']['registration_date_string'])) {
                $users[$cur_user['id']]['registration_date_string'] = format_date(
                    $cur_user['registration_date'],
                    ['day', 'month', 'year']
                );
            }

            if (isset($params['display']['registration_date_since'])) {
                $users[$cur_user['id']]['registration_date_since'] = time_since(
                    $cur_user['registration_date'],
                    'month'
                );
            }

            if (isset($params['display']['last_visit'])) {
                $last_visit = $cur_user['last_visit'] ?? '';
                $users[$cur_user['id']]['last_visit'] = $last_visit;

                if (! get_boolean($cur_user['last_visit_from_history']) && empty($last_visit)) {
                    $last_visit = get_user_last_visit_from_history($cur_user['id'], true);
                    $users[$cur_user['id']]['last_visit'] = $last_visit;
                }

                if (isset($params['display']['last_visit_string'])) {
                    $users[$cur_user['id']]['last_visit_string'] = format_date($last_visit, ['day', 'month', 'year']);
                }

                if (isset($params['display']['last_visit_since'])) {
                    $users[$cur_user['id']]['last_visit_since'] = time_since($last_visit, 'day');
                }
            }
        }

        /* Removed for optimization above, dont go through the $users array for evert display
        if (isset($params['display']['registration_date_string']))
        {
          foreach ($users as $cur_user)
          {
            $users[$cur_user['id']]['registration_date_string'] = format_date($cur_user['registration_date'], array('day', 'month', 'year'));
          }
        }

        if (isset($params['display']['registration_date_since']))
        {
          foreach ($users as $cur_user)
          {
            $users[ $cur_user['id'] ]['registration_date_since'] = time_since($cur_user['registration_date'], 'month');
          }
        }

        if (isset($params['display']['last_visit']))
        {
          foreach ($users as $cur_user)
          {
            $last_visit = $cur_user['last_visit'];
            $users[ $cur_user['id'] ]['last_visit'] = $last_visit;

            if (!get_boolean($cur_user['last_visit_from_history']) && empty($last_visit))
            {
              $last_visit = get_user_last_visit_from_history($cur_user['id'], true);
              $users[ $cur_user['id'] ]['last_visit'] = $last_visit;
            }

            if (isset($params['display']['last_visit_string']))
            {
              $users[ $cur_user['id'] ]['last_visit_string'] = format_date($last_visit, array('day', 'month', 'year'));
            }

            if (isset($params['display']['last_visit_since']))
            {
              $users[ $cur_user['id'] ]['last_visit_since'] = time_since($last_visit, 'day');
            }
          }*/
    }

    $users = trigger_change('ws_users_getList', $users);
    if ($params['per_page'] == 0 && empty($params['display'])) {
        $method_result = $users_id_arr;
    } else {
        $method_result = [
            'paging' => new NamedStruct(
                [
                    'page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'count' => count($users),
                ]
            ),
            'users' => new NamedArray(array_values($users), 'user'),
        ];
    }

    if (isset($params['display']['total_count'])) {
        $method_result['total_count'] = $total_count;
    }

    return $method_result;
}

/**
 * API method
 * Adds a user
 * @option string username
 * @option string password (optional)
 * @option string email (optional)
 */
function ws_users_add(
    array $params,
    $service
): mixed {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    if (strlen(str_replace(' ', '', $params['username'])) == 0) {
        return new Error(WS_ERR_INVALID_PARAM, 'Name field must not be empty');
    }

    global $conf;

    if ($conf['double_password_type_in_admin'] && $params['password'] != $params['password_confirm']) {
        return new Error(WS_ERR_INVALID_PARAM, l10n('The passwords do not match'));
    }

    $user_id = register_user(
        $params['username'],
        $params['password'],
        $params['email'],
        false, // notify admin
        $errors,
        $params['send_password_by_mail']
    );

    if (! $user_id) {
        return new Error(WS_ERR_INVALID_PARAM, $errors[0]);
    }

    return $service->invoke('pwg.users.getList', [
        'user_id' => $user_id,
    ]);
}

/**
 * API method
 * Get a new authentication key for a user.
 * @option int[] user_id
 * @option string pwg_token
 */
function ws_users_getAuthKey(
    array $params,
    &$service
): Error|array {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    $authkey = create_user_auth_key($params['user_id']);

    if ($authkey === false) {
        return new Error(WS_ERR_INVALID_PARAM, 'invalid user_id');
    }

    return $authkey;
}

/**
 * API method
 * Deletes users
 * @option int[] user_id
 * @option string pwg_token
 */
function ws_users_delete(
    array $params,
    &$service
): Error|string {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    global $conf, $user;

    include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

    $protected_users = [
        $user['id'],
        $conf['guest_id'],
        $conf['default_user_id'],
        $conf['webmaster_id'],
    ];

    // an admin can't delete other admin/webmaster
    if ($user['status'] == 'admin') {
        $query = '
SELECT
    user_id
  FROM ' . USER_INFOS_TABLE . '
  WHERE status IN (\'webmaster\', \'admin\')
;';
        $protected_users = array_merge($protected_users, query2array($query, null, 'user_id'));
    }

    // protect some users
    $params['user_id'] = array_diff($params['user_id'], $protected_users);

    $counter = 0;

    foreach ($params['user_id'] as $user_id) {
        delete_user($user_id);
        $counter++;
    }

    return l10n_dec(
        '%d user deleted',
        '%d users deleted',
        $counter
    );
}

/**
 * API method
 * Updates users
 * @option int[] user_id
 * @option string username (optional)
 * @option string password (optional)
 * @option string email (optional)
 * @option string status (optional)
 * @option int level (optional)
 * @option string language (optional)
 * @option string theme (optional)
 * @option int nb_image_page (optional)
 * @option int recent_period (optional)
 * @option bool expand (optional)
 * @option bool show_nb_comments (optional)
 * @option bool show_nb_hits (optional)
 * @option bool enabled_high (optional)
 */
function ws_users_setInfo(
    array $params,
    $service
): mixed {
    if (get_pwg_token() != $params['pwg_token']) {
        return new Error(403, 'Invalid security token');
    }

    if (isset($params['username']) && strlen(str_replace(' ', '', $params['username'])) == 0) {
        return new Error(WS_ERR_INVALID_PARAM, 'Name field must not be empty');
    }

    global $conf, $user;

    include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');
    $updates = [];
    $updates_infos = [];
    $update_status = null;

    if (count($params['user_id']) == 1) {
        if (get_username($params['user_id'][0]) === false) {
            return new Error(WS_ERR_INVALID_PARAM, 'This user does not exist.');
        }

        if (! empty($params['username'])) {
            $user_id = get_userid($params['username']);
            if ($user_id && $user_id != $params['user_id'][0]) {
                return new Error(WS_ERR_INVALID_PARAM, l10n('this login is already used'));
            }

            if ($params['username'] != strip_tags((string) $params['username'])) {
                return new Error(WS_ERR_INVALID_PARAM, l10n('html tags are not allowed in login'));
            }

            $updates[$conf['user_fields']['username']] = $params['username'];
        }

        if (! empty($params['email'])) {
            if (($error = validate_mail_address($params['user_id'][0], $params['email'])) != '') {
                return new Error(WS_ERR_INVALID_PARAM, $error);
            }

            $updates[$conf['user_fields']['email']] = $params['email'];
        }

        if (! empty($params['password'])) {
            if (! is_webmaster()) {
                $password_protected_users = [$conf['guest_id']];

                $query = '
SELECT
    user_id
  FROM ' . USER_INFOS_TABLE . '
  WHERE status IN (\'webmaster\', \'admin\')
;';
                $admin_ids = query2array($query, null, 'user_id');

                // we add all admin+webmaster users BUT the user herself
                $password_protected_users = array_merge(
                    $password_protected_users,
                    array_diff($admin_ids, [$user['id']])
                );

                if (in_array($params['user_id'][0], $password_protected_users)) {
                    return new Error(403, 'Only webmasters can change password of other "webmaster/admin" users');
                }
            }

            $updates[$conf['user_fields']['password']] = $conf['password_hash']($params['password']);
        }
    }

    if (! empty($params['status'])) {
        if (in_array($params['status'], ['webmaster', 'admin']) && ! is_webmaster()) {
            return new Error(403, 'Only webmasters can grant "webmaster/admin" status');
        }

        if (! in_array($params['status'], ['guest', 'generic', 'normal', 'admin', 'webmaster'])) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid status');
        }

        $protected_users = [
            $user['id'],
            $conf['guest_id'],
            $conf['webmaster_id'],
        ];

        // an admin can't change status of other admin/webmaster
        if ($user['status'] == 'admin') {
            $query = '
SELECT
    user_id
  FROM ' . USER_INFOS_TABLE . '
  WHERE status IN (\'webmaster\', \'admin\')
;';
            $protected_users = array_merge($protected_users, query2array($query, null, 'user_id'));
        }

        // status update query is separated from the rest as not applying to the same
        // set of users (current, guest and webmaster can't be changed)
        $params['user_id_for_status'] = array_diff(
            $params['user_id'],
            $protected_users
        );

        $update_status = $params['status'];
    }

    if (! empty($params['level']) || $params['level'] === 0) {
        if (! in_array($params['level'], $conf['available_permission_levels'])) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid level');
        }

        $updates_infos['level'] = $params['level'];
    }

    if (! empty($params['language'])) {
        if (! in_array($params['language'], array_keys(get_languages()))) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid language');
        }

        $updates_infos['language'] = $params['language'];
    }

    if (! empty($params['theme'])) {
        if (! in_array($params['theme'], array_keys(get_pwg_themes()))) {
            return new Error(WS_ERR_INVALID_PARAM, 'Invalid theme');
        }

        $updates_infos['theme'] = $params['theme'];
    }

    if (! empty($params['nb_image_page'])) {
        $updates_infos['nb_image_page'] = $params['nb_image_page'];
    }

    if (! empty($params['recent_period']) || $params['recent_period'] === 0) {
        $updates_infos['recent_period'] = $params['recent_period'];
    }

    if (! empty($params['expand']) || $params['expand'] === false) {
        $updates_infos['expand'] = boolean_to_string($params['expand']);
    }

    if (! empty($params['show_nb_comments']) || $params['show_nb_comments'] === false) {
        $updates_infos['show_nb_comments'] = boolean_to_string($params['show_nb_comments']);
    }

    if (! empty($params['show_nb_hits']) || $params['show_nb_hits'] === false) {
        $updates_infos['show_nb_hits'] = boolean_to_string($params['show_nb_hits']);
    }

    if (! empty($params['enabled_high']) || $params['enabled_high'] === false) {
        $updates_infos['enabled_high'] = boolean_to_string($params['enabled_high']);
    }

    // perform updates
    single_update(
        USERS_TABLE,
        $updates,
        [
            $conf['user_fields']['id'] => $params['user_id'][0],
        ]
    );

    if (isset($updates[$conf['user_fields']['password']])) {
        deactivate_user_auth_keys($params['user_id'][0]);
    }

    if (isset($updates[$conf['user_fields']['email']])) {
        deactivate_password_reset_key($params['user_id'][0]);
    }

    if (isset($update_status) && count($params['user_id_for_status']) > 0) {
        $query = '
UPDATE ' . USER_INFOS_TABLE . ' SET
    status = "' . $update_status . '"
  WHERE user_id IN(' . implode(',', $params['user_id_for_status']) . ')
;';
        pwg_query($query);

        // we delete sessions, ie disconnect, for users if status becomes "guest".
        // It's like deactivating the user.
        if ($update_status == 'guest') {
            foreach ($params['user_id_for_status'] as $user_id_for_status) {
                delete_user_sessions($user_id_for_status);
            }
        }
    }

    if ($updates_infos !== []) {
        $query = '
UPDATE ' . USER_INFOS_TABLE . ' SET ';

        $first = true;
        foreach ($updates_infos as $field => $value) {
            if (! $first) {
                $query .= ', ';
            } else {
                $first = false;
            }

            $query .= $field . ' = "' . $value . '"';
        }

        $query .= '
  WHERE user_id IN(' . implode(',', $params['user_id']) . ')
;';
        pwg_query($query);
    }

    // manage association to groups
    if (! empty($params['group_id'])) {
        $query = '
DELETE
  FROM ' . USER_GROUP_TABLE . '
  WHERE user_id IN (' . implode(',', $params['user_id']) . ')
;';
        pwg_query($query);

        // we remove all provided groups that do not really exist
        $query = '
SELECT
    id
  FROM ' . GROUPS_TABLE . '
  WHERE id IN (' . implode(',', $params['group_id']) . ')
;';
        $group_ids = query2array($query, null, 'id');

        // if only -1 (a group id that can't exist) is in the list, then no
        // group is associated

        if ($group_ids !== []) {
            $inserts = [];

            foreach ($group_ids as $group_id) {
                foreach ($params['user_id'] as $user_id) {
                    $inserts[] = [
                        'user_id' => $user_id,
                        'group_id' => $group_id,
                    ];
                }
            }

            mass_inserts(USER_GROUP_TABLE, array_keys($inserts[0]), $inserts);
        }
    }

    invalidate_user_cache();

    pwg_activity('user', $params['user_id'], 'edit');

    return $service->invoke('pwg.users.getList', [
        'user_id' => $params['user_id'],
        'display' => 'basics,' . implode(',', array_keys($updates_infos)),
    ]);
}

/**
 * API method
 * Set a preferences parameter to current user
 * @option string param
 * @option mixed value
 */
function ws_users_preferences_set(
    array $params,
    &$service
): mixed {
    global $user;

    if (! preg_match('/^[a-zA-Z0-9_-]+$/', (string) $params['param'])) {
        return new Error(WS_ERR_INVALID_PARAM, 'Invalid param name #' . $params['param'] . '#');
    }

    $value = stripslashes((string) $params['value']);
    if ($params['is_json']) {
        $value = json_decode($value, true);
    }

    userprefs_update_param($params['param'], $value);

    return $user['preferences'];
}

/**
 * API method
 * Adds a favorite image for the current user
 * @option int image_id
 */
function ws_users_favorites_add(
    array $params,
    &$service
): true|Error {
    global $user;

    if (is_a_guest()) {
        return new Error(403, 'User must be logged in.');
    }

    // does the image really exist?
    $query = '
SELECT COUNT(*)
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $params['image_id'] . '
;';
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new Error(404, 'image_id not found');
    }

    single_insert(
        FAVORITES_TABLE,
        [
            'image_id' => $params['image_id'],
            'user_id' => $user['id'],
        ],
        [
            'ignore' => true,
        ]
    );

    return true;
}

/**
 * API method
 * Removes a favorite image for the current user
 * @option int image_id
 */
function ws_users_favorites_remove(
    array $params,
    &$service
): true|Error {
    global $user;

    if (is_a_guest()) {
        return new Error(403, 'User must be logged in.');
    }

    // does the image really exist?
    $query = '
SELECT COUNT(*)
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $params['image_id'] . '
;';
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new Error(404, 'image_id not found');
    }

    $query = '
DELETE
  FROM ' . FAVORITES_TABLE . '
  WHERE user_id = ' . $user['id'] . '
    AND image_id = ' . $params['image_id'] . '
;';

    pwg_query($query);

    return true;
}

/**
 * API method
 * Returns the favorite images of the current user
 * @option int per_page
 * @option int page
 * @option string order
 */
function ws_users_favorites_getList(
    array $params,
    &$service
): false|array {
    global $conf, $user;

    if (is_a_guest()) {
        return false;
    }

    check_user_favorites();

    $order_by = ws_std_image_sql_order($params, 'i.');
    $order_by = $order_by === '' || $order_by === '0' ? $conf['order_by'] : 'ORDER BY ' . $order_by;

    $query = '
SELECT
    i.*
  FROM ' . FAVORITES_TABLE . '
    INNER JOIN ' . IMAGES_TABLE . ' i ON image_id = i.id
  WHERE user_id = ' . $user['id'] . '
' . get_sql_condition_FandF(
        [
            'visible_images' => 'id',
        ],
        'AND'
    ) . '
    ' . $order_by . '
;';
    $images = [];
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $image = [];

        foreach (['id', 'width', 'height', 'hit'] as $k) {
            if (isset($row[$k])) {
                $image[$k] = (int) $row[$k];
            }
        }

        foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
            $image[$k] = $row[$k];
        }

        $images[] = array_merge($image, ws_std_get_urls($row));
    }

    $count = count($images);
    $images = array_slice($images, $params['per_page'] * $params['page'], $params['per_page']);

    return [
        'paging' => new NamedStruct(
            [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => $count,
            ]
        ),
        'images' => new NamedArray(
            $images,
            'image',
            ws_std_get_image_xml_attributes()
        ),
    ];
}
