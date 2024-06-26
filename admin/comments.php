<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use Piwigo\inc\DerivativeImage;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_status;
use function Piwigo\inc\create_navigation_bar;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\delete_user_comment;
use function Piwigo\inc\format_date;
use function Piwigo\inc\get_query_string_diff;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use function Piwigo\inc\l10n_dec;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\validate_user_comment;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\PATTERN_ID;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

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
        include_once(PHPWG_ROOT_PATH . 'inc/functions_comment.inc.php');
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

$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new Tabsheet();
$tabsheet->set_id('comments');
$tabsheet->select('');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                           comments display                            |
// +-----------------------------------------------------------------------+

$nb_total = 0;
$nb_pending = 0;

$query = '
SELECT
    COUNT(*) AS counter,
    validated
  FROM ' . COMMENTS_TABLE . '
  GROUP BY validated
;';
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

$where_clauses = ['1=1'];

if ($page['filter'] == 'pending') {
    $where_clauses[] = "validated='false'";
}

$query = '
SELECT
    c.id,
    c.image_id,
    c.date,
    c.author,
    ' . $conf['user_fields']['username'] . ' AS username,
    c.content,
    i.path,
    i.representative_ext,
    validated,
    c.anonymous_id
  FROM ' . COMMENTS_TABLE . ' AS c
    INNER JOIN ' . IMAGES_TABLE . ' AS i
      ON i.id = c.image_id
    LEFT JOIN ' . USERS_TABLE . ' AS u
      ON u.' . $conf['user_fields']['id'] . ' = c.author_id
  WHERE ' . implode(' AND ', $where_clauses) . '
  ORDER BY c.date DESC
  LIMIT ' . $page['start'] . ', ' . $conf['comments_page_nb_comments'] . '
;';
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

$template->assign_var_from_handle(
    'ADMIN_CONTENT',
    'comments'
);
