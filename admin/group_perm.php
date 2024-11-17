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

if ($_POST !== []) {
    check_pwg_token();
    check_input_parameter('cat_true', $_POST, true, PATTERN_ID);
    check_input_parameter('cat_false', $_POST, true, PATTERN_ID);
}

// +-----------------------------------------------------------------------+
// |                            variables init                             |
// +-----------------------------------------------------------------------+

if (! isset($_GET['group_id'])) {
    fatal_error('group_id URL parameter is missing');
}

check_input_parameter('group_id', $_GET, false, PATTERN_ID);

$page['group'] = $_GET['group_id'];

// +-----------------------------------------------------------------------+
// |                                updates                                |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    // if you forbid access to a category, all sub-categories become
    // automatically forbidden
    $subcats = get_subcat_ids($_POST['cat_true']);
    $subcat_list = implode(',', $subcats);
    $query = <<<SQL
        DELETE FROM group_access
        WHERE group_id = {$page['group']}
            AND cat_id IN ({$subcat_list});
        SQL;
    pwg_query($query);
} elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    $uppercats = get_uppercat_ids($_POST['cat_false']);
    $private_uppercats = [];

    $uppercat_list = implode(',', $uppercats);
    $query = <<<SQL
        SELECT id
        FROM categories
        WHERE id IN ({$uppercat_list})
            AND status = 'private';
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $private_uppercats[] = $row['id'];
    }

    // retrying to authorize a category which is already authorized may cause
    // an error (in SQL statement), so we need to know which categories are
    // accesible
    $authorized_ids = [];

    $query = <<<SQL
        SELECT cat_id
        FROM group_access
        WHERE group_id = {$page['group']};
        SQL;
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        $authorized_ids[] = $row['cat_id'];
    }

    $inserts = [];
    $to_autorize_ids = array_diff($private_uppercats, $authorized_ids);
    foreach ($to_autorize_ids as $to_autorize_id) {
        $inserts[] = [
            'group_id' => $page['group'],
            'cat_id' => $to_autorize_id,
        ];
    }

    mass_inserts('group_access', ['group_id', 'cat_id'], $inserts);
    invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'group_perm' => 'group_perm.tpl',
        'double_select' => 'double_select.tpl',
    ]
);

$template->assign(
    [
        'TITLE' =>
          l10n(
              'Manage permissions for group "%s"',
              get_groupname($page['group'])
          ),
        'L_CAT_OPTIONS_TRUE' => l10n('Authorized'),
        'L_CAT_OPTIONS_FALSE' => l10n('Forbidden'),

        'F_ACTION' =>
            get_root_url() .
            'admin.php?page=group_perm&amp;group_id=' .
            $page['group'],
    ]
);

// only private categories are listed
$query_true = <<<SQL
    SELECT id, name, uppercats, global_rank
    FROM categories
    INNER JOIN group_access ON cat_id = id
    WHERE status = 'private'
        AND group_id = {$page['group']};
    SQL;
display_select_cat_wrapper($query_true, [], 'category_option_true');

$result = pwg_query($query_true);
$authorized_ids = [];
while ($row = pwg_db_fetch_assoc($result)) {
    $authorized_ids[] = $row['id'];
}

$query_false = <<<SQL
    SELECT id, name, uppercats, global_rank
    FROM categories
    WHERE status = 'private'\n
    SQL;

if ($authorized_ids !== []) {
    $ids_list = implode(',', $authorized_ids);
    $query_false .= <<<SQL
        AND id NOT IN ({$ids_list})\n
        SQL;
}

$query_false .= ';';
display_select_cat_wrapper($query_false, [], 'category_option_false');

$template->assign('PWG_TOKEN', get_pwg_token());

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
$template->assign_var_from_handle('ADMIN_CONTENT', 'group_perm');
