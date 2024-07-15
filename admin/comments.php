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

$page['start'] = isset($_GET['start']) && is_numeric($_GET['start']) ? $_GET['start'] : 0;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if ($_POST !== []) {
    if (empty($_POST['comments'])) {
        $page['errors'][] = l10n('Select at least one comment');
    } else {
        require_once PHPWG_ROOT_PATH . 'include/functions_comment.inc.php';
        check_input_parameter('comments', $_POST, true, PATTERN_ID);

        if (isset($_POST['validate'])) {
            validate_user_comment($_POST['comments']);

            $page['infos'][] = l10n_dec(
                '%d user comment validated',
                '%d user comments validated',
                count($_POST['comments'])
            );
        }

        if (isset($_POST['reject'])) {
            delete_user_comment($_POST['comments']);

            $page['infos'][] = l10n_dec(
                '%d user comment rejected',
                '%d user comments rejected',
                count($_POST['comments'])
            );
        }
    }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames([
    'comments' => 'comments.tpl',
]);

$template->assign(
    [
        'F_ACTION' => get_root_url() . 'admin.php?page=comments',
    ]
);

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('comments');
$tabsheet->select('');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                           comments display                            |
// +-----------------------------------------------------------------------+

$nb_total = 0;
$nb_pending = 0;

$query = 'SELECT COUNT(*) AS counter, validated FROM comments GROUP BY validated;';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    $nb_total += $row['counter'];

    if ($row['validated'] == 'false') {
        $nb_pending = $row['counter'];
    }
}

$page['filter'] = ! isset($_GET['filter']) && $nb_pending > 0 ? 'pending' : 'all';

if (isset($_GET['filter']) && $_GET['filter'] == 'pending') {
    $page['filter'] = $_GET['filter'];
}

$template->assign(
    [
        'nb_total' => $nb_total,
        'nb_pending' => $nb_pending,
        'filter' => $page['filter'],
    ]
);

$where_clauses = ['1 = 1'];

if ($page['filter'] == 'pending') {
    $where_clauses[] = "validated='false'";
}

$where_clauses_ = implode(' AND ', $where_clauses);
$query =
"SELECT c.id, c.image_id, c.date, c.author, {$conf['user_fields']['username']} AS username, c.content, i.path, i.representative_ext, validated, c.anonymous_id
 FROM comments AS c INNER JOIN images AS i ON i.id = c.image_id LEFT JOIN users AS u ON u.{$conf['user_fields']['id']} = c.author_id
 WHERE {$where_clauses_} ORDER BY c.date DESC LIMIT {$page['start']}, {$conf['comments_page_nb_comments']};";
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    $thumb = DerivativeImage::thumb_url(
        [
            'id' => $row['image_id'],
            'path' => $row['path'],
            'representative_ext' => $row['representative_ext'],
        ]
    );
    $author_name = empty($row['author_id']) ? $row['author'] : stripslashes((string) $row['username']);

    $template->append(
        'comments',
        [
            'U_PICTURE' => get_root_url() . 'admin.php?page=photo-' . $row['image_id'],
            'ID' => $row['id'],
            'TN_SRC' => $thumb,
            'AUTHOR' => trigger_change('render_comment_author', $author_name),
            'DATE' => format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
            'CONTENT' => trigger_change('render_comment_content', $row['content']),
            'IS_PENDING' => ($row['validated'] == 'false'),
            'IP' => $row['anonymous_id'],
        ]
    );

    $list[] = $row['id'];
}

// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

$navbar = create_navigation_bar(
    get_root_url() . 'admin.php' . get_query_string_diff(['start']),
    ($page['filter'] == 'pending' ? $nb_pending : $nb_total),
    $page['start'],
    $conf['comments_page_nb_comments']
);

$template->assign('navbar', $navbar);
$template->assign('ADMIN_PAGE_TITLE', l10n('User comments'));

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'comments');
