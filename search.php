<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//--------------------------------------------------------------------- include
define('PHPWG_ROOT_PATH', './');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_search.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

trigger_notify('loc_begin_search');

// +-----------------------------------------------------------------------+
// | Create a default search                                               |
// +-----------------------------------------------------------------------+

$search = [
    'mode' => 'AND',
    'fields' => [],
];

// list of filters in user preferences
// allwords, cat, tags, author, added_by, filetypes, date_posted
$default_fields = ['allwords', 'cat', 'tags', 'author'];
if (is_a_guest() or is_generic()) {
    $fields = $default_fields;
} else {
    $fields = userprefs_get_param('gallery_search_filters', $default_fields);
}

$words = [];
if (! empty($_GET['q'])) {
    $words = split_allwords($_GET['q']);
}

if (count($words) > 0 or in_array('allwords', $fields)) {
    $search['fields']['allwords'] = [
        'words' => $words,
        'mode' => 'AND',
        'fields' => ['file', 'name', 'comment', 'tags', 'author', 'cat-title', 'cat-desc'],
    ];
}

$cat_ids = [];
if (isset($_GET['cat_id'])) {
    check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
    $cat_ids = [$_GET['cat_id']];
}

if (count($cat_ids) > 0 or in_array('cat', $fields)) {
    $search['fields']['cat'] = [
        'words' => $cat_ids,
        'sub_inc' => true,
    ];
}

if (count(get_available_tags()) > 0) {
    $tag_ids = [];
    if (isset($_GET['tag_id'])) {
        check_input_parameter('tag_id', $_GET, false, '/^\d+(,\d+)*$/');
        $tag_ids = explode(',', $_GET['tag_id']);
    }

    if (count($tag_ids) > 0 or in_array('tags', $fields)) {
        $search['fields']['tags'] = [
            'words' => $tag_ids,
            'mode' => 'AND',
        ];
    }
}

if (in_array('author', $fields)) {
    // does this Piwigo has authors for current user?
    $filters_and_forbidden = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'id',
        ],
        ' WHERE '
    );
    $query = "SELECT id FROM images AS i JOIN image_category AS ic ON ic.image_id = i.id {$filters_and_forbidden} AND author IS NOT NULL LIMIT 1;";
    $first_author = query2array($query);

    if (count($first_author) > 0) {
        $search['fields']['author'] = [
            'words' => [],
            'mode' => 'OR',
        ];
    }
}

foreach (['added_by', 'filetypes', 'date_posted'] as $field) {
    if (in_array($field, $fields)) {
        $search['fields'][$field] = [];
    }
}

list($search_uuid, $search_url) = save_search($search);
redirect($search_url);
