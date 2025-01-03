<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+
define('PHPWG_ROOT_PATH', './');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_comment.inc.php';

if (! $conf['activate_comments']) {
    page_not_found(null);
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

$url_self = PHPWG_ROOT_PATH . 'comments.php'
  . get_query_string_diff(['delete', 'edit', 'validate', 'pwg_token']);

$sort_order = [
    'DESC' => l10n('descending'),
    'ASC' => l10n('ascending'),
];

// sort_by: database fields proposed for sorting comments list
$sort_by = [
    'date' => l10n('comment date'),
    'image_id' => l10n('photo'),
];

// items_number: list of number of items to display per page
$items_number = [5, 10, 20, 50, 'all'];

// if the default value is not in the expected values, we add it in the $items_number array
if (! in_array($conf['comments_page_nb_comments'], $items_number)) {
    $items_number_new = [];

    $is_inserted = false;

    foreach ($items_number as $number) {
        if ($number > $conf['comments_page_nb_comments'] || $number == 'all' && ! $is_inserted) {
            $items_number_new[] = $conf['comments_page_nb_comments'];
            $is_inserted = true;
        }

        $items_number_new[] = $number;
    }

    $items_number = $items_number_new;
}

// since when display comments?
//
$since_options = [
    1 => [
        'label' => l10n('today'),
        'clause' => 'date > ' . pwg_db_get_recent_period_expression(1),
    ],
    2 => [
        'label' => l10n('last %d days', 7),
        'clause' => 'date > ' . pwg_db_get_recent_period_expression(7),
    ],
    3 => [
        'label' => l10n('last %d days', 30),
        'clause' => 'date > ' . pwg_db_get_recent_period_expression(30),
    ],
    4 => [
        'label' => l10n('the beginning'),
        'clause' => '1 = 1',
    ], // stupid but generic
];

trigger_notify('loc_begin_comments');

$page['since'] = empty($_GET['since']) ? 4 : intval($_GET['since']);

// on which field sorting
//
$page['sort_by'] = 'date';
// if the form was submitted, it overloads default behaviour
if (isset($_GET['sort_by']) && isset($sort_by[$_GET['sort_by']])) {
    $page['sort_by'] = $_GET['sort_by'];
}

// order to sort
//
$page['sort_order'] = 'DESC';
// if the form was submitted, it overloads default behaviour
if (isset($_GET['sort_order']) && isset($sort_order[$_GET['sort_order']])) {
    $page['sort_order'] = $_GET['sort_order'];
}

// number of items to display
//
$page['items_number'] = $conf['comments_page_nb_comments'];
if (isset($_GET['items_number'])) {
    $page['items_number'] = $_GET['items_number'];
}

if (! is_numeric($page['items_number']) && $page['items_number'] != 'all') {
    $page['items_number'] = 10;
}

$page['where_clauses'] = [];

// which category to filter on?
if (isset($_GET['cat']) && $_GET['cat'] != 0) {
    check_input_parameter('cat', $_GET, false, PATTERN_ID);

    $category_ids = get_subcat_ids([$_GET['cat']]);
    if ($category_ids === []) {
        $category_ids = [-1];
    }

    $imploded_category_ids = implode(',', $category_ids);
    $page['where_clauses'][] = "category_id IN ({$imploded_category_ids})";
}

// search a particular author
if (! empty($_GET['author'])) {
    $page['where_clauses'][] = "(u.{$conf['user_fields']['username']} = '{$_GET['author']}' OR author = '{$_GET['author']}')";
}

// search a specific comment (if you're coming directly from an admin
// notification email)
if (! empty($_GET['comment_id'])) {
    check_input_parameter('comment_id', $_GET, false, PATTERN_ID);

    // currently, the $_GET['comment_id'] is only used by admins from email
    // for management purpose (validate/delete)
    if (! is_admin()) {
        $login_url =
          get_root_url() . 'identification.php?redirect='
          . urlencode(urlencode((string) $_SERVER['REQUEST_URI']))
        ;
        redirect($login_url);
    }

    $page['where_clauses'][] = 'com.id = ' . $_GET['comment_id'];
}

// search a substring among comments content
if (! empty($_GET['keyword'])) {
    $page['where_clauses'][] =
      '(' .
      implode(
          ' AND ',
          array_map(
              fn ($s): string => "content LIKE '%{$s}%'",
              preg_split('/[\s,;]+/', (string) $_GET['keyword'])
          )
      ) .
      ')';
}

$page['where_clauses'][] = $since_options[$page['since']]['clause'];

// which status to filter on?
if (! is_admin()) {
    $page['where_clauses'][] = "validated='true'";
}

$page['where_clauses'][] = get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'ic.image_id',
    ],
    '',
    true
);

// +-----------------------------------------------------------------------+
// |                         comments management                           |
// +-----------------------------------------------------------------------+

$comment_id = null;
$action = null;

$actions = ['delete', 'validate', 'edit'];
foreach ($actions as $loop_action) {
    if (isset($_GET[$loop_action])) {
        $action = $loop_action;
        check_input_parameter($action, $_GET, false, PATTERN_ID);
        $comment_id = $_GET[$action];
        break;
    }
}

if (isset($action)) {
    $comment_author_id = get_comment_author_id($comment_id);

    if (can_manage_comment($action, $comment_author_id)) {
        $perform_redirect = false;

        if ($action === 'delete') {
            check_pwg_token();
            delete_user_comment($comment_id);
            $perform_redirect = true;
        }

        if ($action === 'validate') {
            check_pwg_token();
            validate_user_comment($comment_id);
            $perform_redirect = true;
        }

        if ($action === 'edit') {
            if (! empty($_POST['content'])) {
                check_pwg_token();
                $comment_action = update_user_comment(
                    [
                        'comment_id' => $_GET['edit'],
                        'image_id' => $_POST['image_id'],
                        'content' => $_POST['content'],
                        'website_url' => $_POST['website_url'],
                    ],
                    $_POST['key']
                );

                switch ($comment_action) {
                    case 'moderate':
                        $_SESSION['page_infos'][] = l10n('An administrator must authorize your comment before it is visible.');
                        // no break
                    case 'validate':
                        $_SESSION['page_infos'][] = l10n('Your comment has been registered');
                        $perform_redirect = true;
                        break;
                    case 'reject':
                        $_SESSION['page_errors'][] = l10n('Your comment has NOT been registered because it did not pass the validation rules');
                        break;
                    default:
                        trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
                }
            }

            $edit_comment = $_GET['edit'];
        }

        if ($perform_redirect) {
            redirect($url_self);
        }
    }
}

// +-----------------------------------------------------------------------+
// |                       page header and options                         |
// +-----------------------------------------------------------------------+

$title = l10n('User comments');
$page['body_id'] = 'theCommentsPage';

$template->set_filenames([
    'comments' => 'comments.tpl',
    'comment_list' => 'comment_list.tpl',
]);
$template->assign(
    [
        'F_ACTION' => PHPWG_ROOT_PATH . 'comments.php',
        'F_KEYWORD' => isset($_GET['keyword']) ? htmlspecialchars(stripslashes((string) $_GET['keyword'])) : '',
        'F_AUTHOR' => isset($_GET['author']) ? htmlspecialchars(stripslashes((string) $_GET['author'])) : '',
    ]
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

// Search in a particular category
$blockname = 'categories';

$sql_condition = get_sql_condition_FandF(
    [
        'forbidden_categories' => 'id',
        'visible_categories' => 'id',
    ],
    'WHERE'
);

$query = <<<SQL
    SELECT id, name, uppercats, global_rank
    FROM categories
    {$sql_condition};
    SQL;
display_select_cat_wrapper($query, [$_GET['cat']], $blockname, true);

// Filter on recent comments...
$tpl_var = [];
foreach ($since_options as $id => $option) {
    $tpl_var[$id] = $option['label'];
}

$template->assign('since_options', $tpl_var);
$template->assign('since_options_selected', $page['since']);

// Sort by
$template->assign('sort_by_options', $sort_by);
$template->assign('sort_by_options_selected', $page['sort_by']);

// Sorting order
$template->assign('sort_order_options', $sort_order);
$template->assign('sort_order_options_selected', $page['sort_order']);

// Number of items
$blockname = 'items_number_option';
$tpl_var = [];
foreach ($items_number as $option) {
    $tpl_var[$option] = is_numeric($option) ? $option : l10n($option);
}

$template->assign('item_number_options', $tpl_var);
$template->assign('item_number_options_selected', $page['items_number']);

// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

$start = isset($_GET['start']) ? intval($_GET['start']) : 0;

// +-----------------------------------------------------------------------+
// |                        last comments display                          |
// +-----------------------------------------------------------------------+

$comments = [];
$element_ids = [];
$category_ids = [];

// todo: replace SQL_CALC_FOUND_ROWS
$where_clauses = implode(' AND ', $page['where_clauses']);
$query = <<<SQL
    SELECT SQL_CALC_FOUND_ROWS com.id AS comment_id, com.image_id, ic.category_id, com.author, com.author_id,
        u.{$conf['user_fields']['email']} AS user_email, com.email, com.date, com.website_url, com.content,
        com.validated
    FROM image_category AS ic
    INNER JOIN comments AS com ON ic.image_id = com.image_id
    LEFT JOIN users As u ON u.{$conf['user_fields']['id']} = com.author_id
    WHERE {$where_clauses}
    GROUP BY comment_id
    ORDER BY {$page['sort_by']} {$page['sort_order']}

    SQL;

if ($page['items_number'] != 'all') {
    $query .= <<<SQL
        LIMIT {$page['items_number']} OFFSET {$start}

        SQL;
}

$query .= ';';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    $comments[] = $row;
    $element_ids[] = $row['image_id'];
    $category_ids[] = $row['category_id'];
}

// todo: replace FOUND_ROWS()
[$counter] = pwg_db_fetch_row(pwg_query('SELECT FOUND_ROWS();'));

$url = PHPWG_ROOT_PATH . 'comments.php'
  . get_query_string_diff(['start', 'edit', 'delete', 'validate', 'pwg_token']);

$navbar = create_navigation_bar(
    $url,
    $counter,
    $start,
    $page['items_number']
);

$template->assign('navbar', $navbar);

if ($comments !== []) {
    // retrieving element information
    $element_ids_str = implode(',', $element_ids);
    $query = <<<SQL
        SELECT *
        FROM images
        WHERE id IN ({$element_ids_str});
        SQL;
    $elements = query2array($query, 'id');

    // retrieving category information
    $category_ids_str = implode(',', $category_ids);
    $query = <<<SQL
        SELECT id, name, permalink, uppercats
        FROM categories
        WHERE id IN ({$category_ids_str});
        SQL;
    $categories = query2array($query, 'id');

    foreach ($comments as $comment) {
        if (! empty($elements[$comment['image_id']]['name'])) {
            $name = $elements[$comment['image_id']]['name'];
        } else {
            $name = get_name_from_file($elements[$comment['image_id']]['file']);
        }

        // source of the thumbnail picture
        $src_image = new SrcImage($elements[$comment['image_id']]);

        // link to the full size picture
        $url = make_picture_url(
            [
                'category' => $categories[$comment['category_id']],
                'image_id' => $comment['image_id'],
                'image_file' => $elements[$comment['image_id']]['file'],
            ]
        );

        $email = null;
        if (! empty($comment['user_email'])) {
            $email = $comment['user_email'];
        } elseif (! empty($comment['email'])) {
            $email = $comment['email'];
        }

        $tpl_comment = [
            'ID' => $comment['comment_id'],
            'U_PICTURE' => $url,
            'src_image' => $src_image,
            'ALT' => $name,
            'AUTHOR' => trigger_change('render_comment_author', $comment['author']),
            'WEBSITE_URL' => $comment['website_url'],
            'DATE' => format_date($comment['date'], ['day_name', 'day', 'month', 'year', 'time']),
            'CONTENT' => trigger_change('render_comment_content', $comment['content']),
        ];

        if (is_admin()) {
            $tpl_comment['EMAIL'] = $email;
        }

        if (can_manage_comment('delete', $comment['author_id'])) {
            $tpl_comment['U_DELETE'] = add_url_params(
                $url_self,
                [
                    'delete' => $comment['comment_id'],
                    'pwg_token' => get_pwg_token(),
                ]
            );
        }

        if (can_manage_comment('edit', $comment['author_id'])) {
            $tpl_comment['U_EDIT'] = add_url_params(
                $url_self,
                [
                    'edit' => $comment['comment_id'],
                ]
            );

            if (isset($edit_comment) && $comment['comment_id'] == $edit_comment) {
                $tpl_comment['IN_EDIT'] = true;
                $key = get_ephemeral_key(2, $comment['image_id']);
                $tpl_comment['KEY'] = $key;
                $tpl_comment['IMAGE_ID'] = $comment['image_id'];
                $tpl_comment['CONTENT'] = $comment['content'];
                $tpl_comment['PWG_TOKEN'] = get_pwg_token();
                $tpl_comment['U_CANCEL'] = $url_self;
            }
        }

        if (can_manage_comment('validate', $comment['author_id']) && $comment['validated'] != 'true') {
            $tpl_comment['U_VALIDATE'] = add_url_params(
                $url_self,
                [
                    'validate' => $comment['comment_id'],
                    'pwg_token' => get_pwg_token(),
                ]
            );
        }

        $template->append('comments', $tpl_comment);
    }
}

$derivative_params = trigger_change('get_comments_derivative_params', ImageStdParams::get_by_type(IMG_THUMB));
$template->assign('comment_derivative_params', $derivative_params);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theCommentsPage', $themeconf['hide_menu_on'])) {
    require PHPWG_ROOT_PATH . 'include/menubar.inc.php';
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+
require PHPWG_ROOT_PATH . 'include/page_header.php';
trigger_notify('loc_end_comments');
flush_page_messages();
if ($comments !== []) {
    $template->assign_var_from_handle('COMMENT_LIST', 'comment_list');
}

$template->pparse('comments');
require PHPWG_ROOT_PATH . 'include/page_tail.php';
