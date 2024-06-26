<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_insert_id;
use function Piwigo\inc\dbLayer\pwg_db_real_escape_string;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\display_select_cat_wrapper;
use function Piwigo\inc\flush_page_messages;
use function Piwigo\inc\get_available_tags;
use function Piwigo\inc\get_sql_condition_FandF;
use function Piwigo\inc\l10n;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\redirect;
use function Piwigo\inc\trigger_notify;
use const Piwigo\inc\ACCESS_GUEST;
use const Piwigo\inc\PATTERN_ID;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//--------------------------------------------------------------------- include
const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

trigger_notify('loc_begin_search');

//------------------------------------------------------------------ form check
$search = [];
if (isset($_POST['submit'])) {
    foreach ($_POST as $post_key => $post_value) {
        if (! is_array($post_value)) {
            $_POST[$post_key] = pwg_db_real_escape_string($post_value);
        }
    }

    if (isset($_POST['search_allwords'])
        && ! preg_match('/^\s*$/', (string) $_POST['search_allwords'])) {
        check_input_parameter('mode', $_POST, false, '/^(OR|AND)$/');
        check_input_parameter('fields', $_POST, true, '/^(name|comment|file)$/');

        $drop_char_match = [
            '-', '^', '$', ';', '#', '&', '(', ')', '<', '>', '`', "'", '"', '|', ',', '@', '_',
            '?', '%', '~', '.', '[', ']', '{', '}', ':', '\\', '/', '=', "'", '!', '*'];
        $drop_char_replace = [
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', '', ' ',
            ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', ' ', ' ', ' ', ' ', ' '];

        // Split words
        $search['fields']['allwords'] = [
            'words' => array_unique(
                preg_split(
                    '/\s+/',
                    str_replace(
                        $drop_char_match,
                        $drop_char_replace,
                        $_POST['search_allwords']
                    )
                )
            ),
            'mode' => $_POST['mode'],
            'fields' => $_POST['fields'],
        ];

        if (isset($_POST['search_in_tags'])) {
            $search['fields']['search_in_tags'] = true;
        }
    }

    if (isset($_POST['tags'])) {
        check_input_parameter('tags', $_POST, true, PATTERN_ID);
        check_input_parameter('tag_mode', $_POST, false, '/^(OR|AND)$/');

        $search['fields']['tags'] = [
            'words' => $_POST['tags'],
            'mode' => $_POST['tag_mode'],
        ];
    }

    if (isset($_POST['authors']) && is_array($_POST['authors']) && $_POST['authors'] !== []) {
        $authors = [];

        foreach ($_POST['authors'] as $author) {
            $authors[] = strip_tags((string) $author);
        }

        $search['fields']['author'] = [
            'words' => $authors,
            'mode' => 'OR',
        ];
    }

    if (isset($_POST['cat'])) {
        check_input_parameter('cat', $_POST, true, PATTERN_ID);

        $search['fields']['cat'] = [
            'words' => $_POST['cat'],
            'sub_inc' => $_POST['subcats-included'] == 1,
        ];
    }

    // dates
    check_input_parameter('date_type', $_POST, false, '/^date_(creation|available)$/');

    $type_date = $_POST['date_type'];

    if (! empty($_POST['start_year'])) {
        $search['fields'][$type_date . '-after'] = [
            'date' => sprintf(
                '%d-%02d-%02d 00:00:00',
                $_POST['start_year'],
                $_POST['start_month'] != 0 ? $_POST['start_month'] : '01',
                $_POST['start_day'] != 0 ? $_POST['start_day'] : '01'
            ),
            'inc' => true,
        ];
    }

    if (! empty($_POST['end_year'])) {
        $search['fields'][$type_date . '-before'] = [
            'date' => sprintf(
                '%d-%02d-%02d 23:59:59',
                $_POST['end_year'],
                $_POST['end_month'] != 0 ? $_POST['end_month'] : '12',
                $_POST['end_day'] != 0 ? $_POST['end_day'] : '31'
            ),
            'inc' => true,
        ];
    }

    if (! empty($search)) {
        // default search mode : each clause must be respected
        $search['mode'] = 'AND';

        // register search rules in database, then they will be available on
        // thumbnails page and picture page.
        $query = '
INSERT INTO ' . SEARCH_TABLE . '
  (rules, last_seen)
  VALUES
  (\'' . pwg_db_real_escape_string(serialize($search)) . '\', NOW())
;';
        pwg_query($query);

        $search_id = pwg_db_insert_id();
    } else {
        $page['errors'][] = l10n('Empty query. No criteria has been entered.');
    }
}

//----------------------------------------------------------------- redirection
if (isset($_POST['submit']) && count(
    $page['errors']
) == 0) {
    redirect(
        make_index_url(
            [
                'section' => 'search',
                'search' => $search_id,
            ]
        )
    );
}

//----------------------------------------------------- template initialization

//
// Start output of page
//
$title = l10n('Search');
$page['body_id'] = 'theSearchPage';

$template->set_filename('search', 'search.tpl');

$month_list = $lang['month'];
$month_list[0] = '------------';
ksort($month_list);

$template->assign(
    [
        'F_SEARCH_ACTION' => 'search.php',
        'U_HELP' => PHPWG_ROOT_PATH . 'popuphelp.php?page=search',

        'month_list' => $month_list,
        'START_DAY_SELECTED' => $_POST['start_day'] ?? null,
        'START_MONTH_SELECTED' => $_POST['start_month'] ?? null,
        'END_DAY_SELECTED' => $_POST['end_day'] ?? null,
        'END_MONTH_SELECTED' => $_POST['end_month'] ?? null,
    ]
);

$available_tags = get_available_tags();

if ($available_tags !== []) {
    usort($available_tags, '\Piwigo\inc\tag_alpha_compare');

    $template->assign('TAGS', $available_tags);
}

// authors
$authors = [];

$query = '
SELECT
    author,
    id
  FROM ' . IMAGES_TABLE . ' AS i
    JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = i.id
  ' . get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id',
    ],
    ' WHERE '
) . '
    AND author IS NOT NULL
  GROUP BY author, id
  ORDER BY author
;';
$author_counts = [];
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    if (! isset($author_counts[$row['author']])) {
        $author_counts[$row['author']] = 0;
    }

    $author_counts[$row['author']]++;
}

foreach ($author_counts as $author => $counter) {
    $authors[] = [
        'author' => $author,
        'counter' => $counter,
    ];
}

$template->assign('AUTHORS', $authors);

//------------------------------------------------------------- categories form
$query = '
SELECT id,name,global_rank,uppercats
  FROM ' . CATEGORIES_TABLE . '
' . get_sql_condition_FandF(
    [
        'forbidden_categories' => 'id',
        'visible_categories' => 'id',
    ],
    'WHERE'
) . '
;';
display_select_cat_wrapper($query, [], 'category_options');

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theSearchPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'inc/menubar.inc.php');
}

//------------------------------------------------------------ html code display
include(PHPWG_ROOT_PATH . 'inc/page_header.php');
trigger_notify('loc_end_search');
flush_page_messages();
$template->pparse('search');
include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
