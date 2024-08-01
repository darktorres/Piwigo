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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'user_activity';
include(PHPWG_ROOT_PATH . 'admin/include/user_tabs.inc.php');

if (isset($_GET['type']) && $_GET['type'] == 'download_logs') {
    $output_lines = [];

    $query =
    "SELECT activity_id, performed_by, object, object_id, action, ip_address, occured_on, details, {$conf['user_fields']['username']} AS username
     FROM activity JOIN users AS u ON performed_by = u.{$conf['user_fields']['id']} ORDER BY activity_id DESC;";
    $result = pwg_query($query);
    $output_lines[] = ['User', 'ID_User', 'Object', 'Object_ID', 'Action', 'Date', 'Hour', 'IP_Address', 'Details'];
    while ($row = pwg_db_fetch_assoc($result)) {
        [$date, $hour] = explode(' ', (string) $row['occured_on']);

        $output_lines[] = [
            'username' => $row['username'],
            'user_id' => $row['performed_by'],
            'object' => $row['object'],
            'object_id' => $row['object_id'],
            'action' => $row['action'],
            'date' => $date,
            'hour' => $hour,
            'ip_address' => $row['ip_address'],
            'details' => $row['details'],
        ];
    }

    header('Content-type: application/csv');
    header('Content-Disposition: attachment; filename=' . date('YmdGis') . 'piwigo_activity_log.csv');
    header('Content-Transfer-Encoding: UTF-8');

    $f = fopen('php://output', 'w');
    foreach ($output_lines as $line) {
        fputcsv($f, $line, ';');
    }

    fclose($f);

    exit();
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+
$template->set_filename('user_activity', 'user_activity.tpl');
$template->assign('ADMIN_PAGE_TITLE', l10n('Users'));

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+
$template->assign([
    'PWG_TOKEN' => get_pwg_token(),
    'INHERIT' => $conf['inheritance_by_default'],
    'CACHE_KEYS' => get_admin_client_cache_keys(['users']),
]);

$query = "SELECT performed_by, COUNT(*) AS counter FROM activity WHERE object != 'system' GROUP BY performed_by;";

$nb_lines_for_user = query2array($query, 'performed_by', 'counter');

if ($nb_lines_for_user !== []) {
    $nb_lines_for_user_ = implode(',', array_keys($nb_lines_for_user));
    $query =
    "SELECT {$conf['user_fields']['id']} AS id, {$conf['user_fields']['username']} AS username FROM users
     WHERE {$conf['user_fields']['id']} IN ({$nb_lines_for_user_});";
}

$username_of = query2array($query, 'id', 'username');

$filterable_users = [];

foreach ($nb_lines_for_user as $id => $nb_line) {
    $filterable_users[] = [
        'id' => $id,
        'username' => $username_of[$id] ?? 'user#' . $id,
        'nb_lines' => $nb_line,
    ];
}

$template->assign('ulist', $filterable_users);

$query = 'SELECT COUNT(*) FROM users;';
[$nb_users] = pwg_db_fetch_row(pwg_query($query));
$template->assign('nb_users', $nb_users);

$template->assign_var_from_handle('ADMIN_CONTENT', 'user_activity');
