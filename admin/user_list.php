<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Add users and manage users list
 */

check_input_parameter('group', $_GET, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'user_list';
require PHPWG_ROOT_PATH . 'admin/include/user_tabs.inc.php';

// +-----------------------------------------------------------------------+
// |                              groups list                              |
// +-----------------------------------------------------------------------+

$groups = [];

$query = <<<SQL
    SELECT id, name
    FROM groups_table
    ORDER BY name ASC;
    SQL;
$result = pwg_query($query);

while ($row = pwg_db_fetch_assoc($result)) {
    $groups[$row['id']] = $row['name'];
}

// +-----------------------------------------------------------------------+
// |                              Dates for filtering                      |
// +-----------------------------------------------------------------------+

$query = <<<SQL
    SELECT DISTINCT

    SQL;

if (DB_ENGINE === 'MySQL') {
    $query .= <<<SQL
        MONTH(registration_date) AS registration_month, YEAR(registration_date) AS registration_year, registration_date

        SQL;
}

if (DB_ENGINE === 'PostgreSQL') {
    $query .= <<<SQL
        EXTRACT(MONTH FROM registration_date) AS registration_month, EXTRACT(YEAR FROM registration_date) AS registration_year, registration_date

        SQL;
}

$query .= <<<SQL
    FROM user_infos
    ORDER BY registration_date;
    SQL;
$result = pwg_query($query);

$register_dates = [];
while ($row = pwg_db_fetch_assoc($result)) {
    $register_dates[] = $row['registration_year'] . '-' . sprintf('%02u', $row['registration_month']);
}

$template->assign('register_dates', implode(',', $register_dates));

// +-----------------------------------------------------------------------+
// | template                                                              |
// +-----------------------------------------------------------------------+
$template->assign(
    [
        'ADMIN_PAGE_TITLE' => l10n('Users'),
        'ACTIVATE_COMMENTS' => $conf['activate_comments'],
        'Double_Password' => $conf['double_password_type_in_admin'],
    ]
);

$template->set_filenames([
    'user_list' => 'user_list.tpl',
]);

$default_user = get_default_user_info(true);

$protected_users = [
    $user['id'],
    $conf['guest_id'],
    $conf['default_user_id'],
    $conf['webmaster_id'],
];

$password_protected_users = [$conf['guest_id']];

// an admin can't delete other admin/webmaster
if ($user['status'] == 'admin') {
    $query = <<<SQL
        SELECT user_id
        FROM user_infos
        WHERE status IN ('webmaster', 'admin');
        SQL;
    $admin_ids = query2array($query, null, 'user_id');

    $protected_users = array_merge($protected_users, $admin_ids);

    // we add all admin+webmaster users BUT the user herself
    $password_protected_users = array_merge($password_protected_users, array_diff($admin_ids, [$user['id']]));
}

$template->assign(
    [
        'U_HISTORY' => get_root_url() . 'admin.php?page=history&filter_user_id=',
        'PWG_TOKEN' => get_pwg_token(),
        'NB_IMAGE_PAGE' => $default_user['nb_image_page'],
        'RECENT_PERIOD' => $default_user['recent_period'],
        'theme_options' => get_pwg_themes(),
        'theme_selected' => get_default_theme(),
        'language_options' => get_languages(),
        'language_selected' => get_default_language(),
        'association_options' => $groups,
        'protected_users' => implode(',', array_unique($protected_users)),
        'password_protected_users' => implode(',', array_unique($password_protected_users)),
        'guest_user' => $conf['guest_id'],
        'filter_group' => ($_GET['group'] ?? null),
        'connected_user' => $user['id'],
        'connected_user_status' => $user['status'],
        'owner' => $conf['webmaster_id'],
    ]
);

if (isset($_GET['show_add_user'])) {
    $template->assign('show_add_user', true);
}

// Status options
foreach (get_enums('user_infos', 'status') as $status) {
    $label_of_status[$status] = l10n('user_status_' . $status);
}

$pref_status_options = $label_of_status;

// a simple "admin" can't set/remove statuses webmaster/admin
if ($user['status'] == 'admin') {
    unset($pref_status_options['webmaster']);
    unset($pref_status_options['admin']);
}

$template->assign('label_of_status', $label_of_status);
$template->assign('pref_status_options', $pref_status_options);
$template->assign('pref_status_selected', 'normal');

// user level options
foreach ($conf['available_permission_levels'] as $level) {
    $level_options[$level] = l10n(sprintf('Level %d', $level));
}

$template->assign('level_options', $level_options);
$template->assign('level_selected', $default_user['level']);

$query = <<<SQL
    SELECT id, name, is_default
    FROM groups_table
    ORDER BY name ASC;
    SQL;
$result = pwg_query($query);

$groups_arr_id = [];
$groups_arr_name = [];
while ($row = pwg_db_fetch_assoc($result)) {
    $groups_arr_name[] = '"' . pwg_db_real_escape_string($row['name']) . '"';
    $groups_arr_id[] = $row['id'];
}

$template->assign('groups_arr_id', implode(',', $groups_arr_id));
$template->assign('groups_arr_name', implode(',', $groups_arr_name));
$template->assign('guest_id', $conf['guest_id']);

$template->assign('view_selector', userprefs_get_param('user-manager-view', 'line'));

if (userprefs_get_param('user-manager-view', 'line') == 'line') {
    //Show 5 users by default
    $template->assign('pagination', userprefs_get_param('user-manager-pagination', 5));
} else {
    //Show 10 users by default
    $template->assign('pagination', userprefs_get_param('user-manager-pagination', 10));
}

// +-----------------------------------------------------------------------+
// | html code display                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'user_list');
