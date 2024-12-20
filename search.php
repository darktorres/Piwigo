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
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';
require_once PHPWG_ROOT_PATH . 'include/functions_search.inc.php';

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
if (is_a_guest() || is_generic()) {
    $fields = $default_fields;
} else {
    $fields = userprefs_get_param('gallery_search_filters', $default_fields);
}

$words = [];
if (! empty($_GET['q'])) {
    $words = split_allwords($_GET['q']);
}

if (count($words) > 0 || in_array('allwords', $fields)) {
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

if ($cat_ids !== [] || in_array('cat', $fields)) {
    $search['fields']['cat'] = [
        'words' => $cat_ids,
        'sub_inc' => true,
    ];
}

if (get_available_tags() !== []) {
    $tag_ids = [];
    if (isset($_GET['tag_id'])) {
        check_input_parameter('tag_id', $_GET, false, '/^\d+(,\d+)*$/');
        $tag_ids = explode(',', (string) $_GET['tag_id']);
    }

    if ($tag_ids !== [] || in_array('tags', $fields)) {
        $search['fields']['tags'] = [
            'words' => $tag_ids,
            'mode' => 'AND',
        ];
    }
}

if (in_array('author', $fields)) {
    // does this Piwigo have authors for current user?
    $sql_condition = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'id',
        ],
        'WHERE'
    );

    $query = <<<SQL
        SELECT id
        FROM images AS i
        JOIN image_category AS ic ON ic.image_id = i.id
        {$sql_condition}
        AND author IS NOT NULL
        LIMIT 1;
        SQL;
    $first_author = query2array($query);

    if ($first_author !== []) {
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

[$search_uuid, $search_url] = save_search($search);
redirect($search_url);
