<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('IN_ADMIN')) {
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

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $page['user'] = $_GET['user_id'];
} else {
    die('user_id URL parameter is missing');
}

// +-----------------------------------------------------------------------+
// |                                updates                                |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    // if you forbid access to a category, all sub-categories become
    // automatically forbidden
    $subcats = get_subcat_ids($_POST['cat_true']);
    $subcat_ids = implode(',', $subcats);
    $query = <<<SQL
        DELETE FROM user_access
        WHERE user_id = {$page['user']}
            AND cat_id IN ({$subcat_ids});
        SQL;
    pwg_query($query);
} elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    add_permission_on_category($_POST['cat_false'], $page['user']);
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'user_perm' => 'user_perm.tpl',
        'double_select' => 'double_select.tpl',
    ]
);

$template->assign(
    [
        'TITLE' =>
          l10n(
              'Manage permissions for user "%s"',
              get_username($page['user'])
          ),
        'L_CAT_OPTIONS_TRUE' => l10n('Authorized'),
        'L_CAT_OPTIONS_FALSE' => l10n('Forbidden'),

        'F_ACTION' =>
            PHPWG_ROOT_PATH .
            'admin.php?page=user_perm' .
            '&amp;user_id=' . $page['user'],
    ]
);

// retrieve category ids authorized to the groups the user belongs to
$group_authorized = [];

$query = <<<SQL
    SELECT DISTINCT cat_id, c.uppercats, c.global_rank
    FROM user_group AS ug
    INNER JOIN group_access AS ga ON ug.group_id = ga.group_id
    INNER JOIN categories AS c ON c.id = ga.cat_id
    WHERE ug.user_id = {$page['user']};
    SQL;
$result = pwg_query($query);

if (pwg_db_num_rows($result) > 0) {
    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $cats[] = $row;
        $group_authorized[] = $row['cat_id'];
    }

    usort($cats, global_rank_compare(...));

    foreach ($cats as $category) {
        $template->append(
            'categories_because_of_groups',
            get_cat_display_name_cache($category['uppercats'], null)
        );
    }
}

// only private categories are listed
$query_true = <<<SQL
    SELECT id, name, uppercats, global_rank
    FROM categories
    INNER JOIN user_access ON cat_id = id
    WHERE status = 'private'
        AND user_id = {$page['user']}

    SQL;
if ($group_authorized !== []) {
    $groupAuthorizedImplode = implode(',', $group_authorized);
    $query_true .= " AND cat_id NOT IN ({$groupAuthorizedImplode})\n";
}

$query_true .= ';';
display_select_cat_wrapper($query_true, [], 'category_option_true');

$result = pwg_query($query_true);
$authorized_ids = [];
while ($row = pwg_db_fetch_assoc($result)) {
    $authorized_ids[] = $row['id'];
}

$query_false = <<<SQL
    SELECT id, name, uppercats, global_rank
    FROM categories
    WHERE status = 'private'

    SQL;
if ($authorized_ids !== []) {
    $authorizedIdsImplode = implode(',', $authorized_ids);
    $query_false .= " AND id NOT IN ({$authorizedIdsImplode})\n";
}

if ($group_authorized !== []) {
    $groupAuthorizedImplode = implode(',', $group_authorized);
    $query_false .= " AND id NOT IN ({$groupAuthorizedImplode})\n";
}

$query_false .= ';';
display_select_cat_wrapper($query_false, [], 'category_option_false');

$template->assign('PWG_TOKEN', get_pwg_token());

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
$template->assign_var_from_handle('ADMIN_CONTENT', 'user_perm');
