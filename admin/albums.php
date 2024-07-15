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

$query = 'SELECT COUNT(*) FROM categories;';
[$albums_counter] = pwg_db_fetch_row(pwg_query($query));

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('parent_id', $_GET, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'list';
include(PHPWG_ROOT_PATH . 'admin/include/albums_tab.inc.php');

// +-----------------------------------------------------------------------+
// |                         categories auto order                         |
// +-----------------------------------------------------------------------+

$open_cat = $_GET['parent_id'] ?? -1;

$sort_orders = [
    'name ASC',
    'name DESC',
    'date_creation DESC',
    'date_creation ASC',
    'date_available DESC',
    'date_available ASC',
    'natural_order DESC',
    'natural_order ASC',
];

if (isset($_POST['simpleAutoOrder']) || isset($_POST['recursiveAutoOrder'])) {

    if (! in_array($_POST['order'], $sort_orders)) {
        die('Invalid sort order');
    }

    check_input_parameter('id', $_POST, false, '/^-?\d+$/');

    $id_uppercat_ = ($_POST['id'] === '-1') ? 'IS NULL' : "= {$_POST['id']}";
    $query = "SELECT id FROM categories WHERE id_uppercat {$id_uppercat_};";
    $category_ids = array_from_query($query, 'id');

    if (isset($_POST['recursiveAutoOrder'])) {
        $category_ids = get_subcat_ids($category_ids);
    }

    $categories = [];
    $sort = [];

    [$order_by_field, $order_by_asc] = explode(' ', (string) $_POST['order']);

    $order_by_date = false;
    if (str_starts_with($order_by_field, 'date_')) {
        $order_by_date = true;

        $ref_dates = get_categories_ref_date(
            $category_ids,
            $order_by_field,
            $order_by_asc === 'ASC' ? 'min' : 'max'
        );
    }

    $category_ids_ = implode(',', $category_ids);
    $query = "SELECT id, name, id_uppercat FROM categories WHERE id IN ({$category_ids_});";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['name'] = trigger_change('render_category_name', $row['name'], 'admin_cat_list');

        $sort[] = $order_by_date ? $ref_dates[$row['id']] : remove_accents($row['name']);

        $categories[] = [
            'id' => $row['id'],
            'id_uppercat' => $row['id_uppercat'],
        ];
    }

    array_multisort(
        $sort,
        $order_by_field === 'natural_order' ? SORT_NATURAL : SORT_REGULAR,
        $order_by_asc === 'ASC' ? SORT_ASC : SORT_DESC,
        $categories
    );

    save_categories_order($categories);

    $open_cat = $_POST['id'];
}

$template->assign('open_cat', $open_cat);

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+
$template->set_filename('albums', 'albums.tpl');

$template->assign(
    [
        'F_ACTION' => get_root_url() . 'admin.php?page=albums',
    ]
);

$template->assign('delay_before_autoOpen', $conf['album_move_delay_before_auto_opening']);

$template->assign('POS_PREF', $conf['newcat_default_position']); //TODO use user pref if it exists

// +-----------------------------------------------------------------------+
// |                          Album display                                |
// +-----------------------------------------------------------------------+

//Get all albums
$query = 'SELECT id, name, rank_column, status, visible, uppercats, lastmodified FROM categories;';

$allAlbum = query2array($query);

//Make an id tree
$associatedTree = [];

foreach ($allAlbum as $album) {
    $album['name'] = trigger_change('render_category_name', $album['name'], 'admin_cat_list');
    $album['lastmodified'] = time_since($album['lastmodified'], 'year');

    $parents = explode(',', (string) $album['uppercats']);
    $the_place = &$associatedTree[strval($parents[0])];
    $counter = count($parents);
    for ($i = 1; $i < $counter; $i++) {
        $the_place = &$the_place['children'][strval($parents[$i])];
    }

    $the_place['cat'] = $album;
}

// WARNING $user['forbidden_categories'] is 100% reliable only on gallery side because
// it's a cache variable. On administration side, if you modify public/private status
// of an album or change permissions, this variable is reset and not recalculated until
// you open the gallery. As this situation doesn't occur each time you use the
// administration, it's quite reliable but not as much as on gallery side.
$is_forbidden = array_fill_keys(explode(',', (string) $user['forbidden_categories']), 1);

//Make an ordered tree
function cmpCat(
    array $a,
    array $b
): int {
    return $a['rank_column'] <=> $b['rank_column'];
}

/**
 * @return array<'children'|'has_not_access'|'id'|'last_updates'|'name'|'nb_images'|'nb_sub_photos'|'nb_subcats'|'rank'|'status', mixed>[]|array<'has_not_access'|'id'|'last_updates'|'name'|'nb_images'|'nb_sub_photos'|'rank'|'status', mixed>[]
 */
function assocToOrderedTree(
    array $assocT
): array {
    global $nb_photos_in, $nb_sub_photos, $is_forbidden;

    $orderedTree = [];

    foreach ($assocT as $cat) {
        $orderedCat = [];
        $orderedCat['rank_column'] = $cat['cat']['rank_column'];
        $orderedCat['name'] = $cat['cat']['name'];
        $orderedCat['status'] = $cat['cat']['status'];
        $orderedCat['id'] = $cat['cat']['id'];
        $orderedCat['visible'] = $cat['cat']['visible'];
        $orderedCat['nb_images'] = $nb_photos_in[$cat['cat']['id']] ?? 0;
        $orderedCat['last_updates'] = $cat['cat']['lastmodified'];
        $orderedCat['has_not_access'] = isset($is_forbidden[$cat['cat']['id']]);
        $orderedCat['nb_sub_photos'] = $nb_sub_photos[$cat['cat']['id']] ?? 0;
        if (isset($cat['children'])) {
            //Does not update when moving a node
            $orderedCat['nb_subcats'] = count($cat['children']);
            $orderedCat['children'] = assocToOrderedTree($cat['children']);
        }

        $orderedTree[] = $orderedCat;
    }

    usort($orderedTree, cmpCat(...));
    return $orderedTree;
}

$query = 'SELECT category_id, COUNT(*) AS nb_photos FROM image_category GROUP BY category_id;';

$nb_photos_in = query2array($query, 'category_id', 'nb_photos');

$query = 'SELECT id, uppercats FROM categories;';
$all_categories = query2array($query, 'id', 'uppercats');

$subcats_of = [];

foreach ($all_categories as $id => $uppercats) {
    foreach (array_slice(explode(',', (string) $uppercats), 0, -1) as $uppercat_id) {
        $subcats_of[$uppercat_id][] = $id;
    }
}

$nb_sub_photos = [];
foreach ($subcats_of as $cat_id => $subcat_ids) {
    $nb_photos = 0;
    foreach ($subcat_ids as $id) {
        if (isset($nb_photos_in[$id])) {
            $nb_photos += $nb_photos_in[$id];
        }
    }

    $nb_sub_photos[$cat_id] = $nb_photos;
}

$template->assign(
    [
        'album_data' => assocToOrderedTree($associatedTree),
        'PWG_TOKEN' => get_pwg_token(),
        'nb_albums' => count($allAlbum),
        'ADMIN_PAGE_TITLE' => l10n('Albums'),
        'light_album_manager' => ($albums_counter > $conf['light_album_manager_threshold']) ? 1 : 0,
    ]
);

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'albums');

// +-----------------------------------------------------------------------+
// |                              functions                                |
// +-----------------------------------------------------------------------+
/**
 * @return mixed[]
 */
function get_categories_ref_date(
    array $ids,
    string $field = 'date_available',
    string $minmax = 'max'
): array {
    // we need to work on the whole tree under each category, even if we don't
    // want to sort sub categories
    $category_ids = get_subcat_ids($ids);

    // search for the reference date of each album
    $category_ids_ = implode(',', $category_ids);
    $query =
    "SELECT category_id, {$minmax}({$field}) AS ref_date FROM image_category JOIN images ON image_id = id
     WHERE category_id IN ({$category_ids_}) GROUP BY category_id;";
    $ref_dates = query2array($query, 'category_id', 'ref_date');

    // the iterate on all albums (having a ref_date or not) to find the
    // reference_date, with a search on sub-albums
    $category_ids_ = implode(',', $category_ids);
    $query = "SELECT id, uppercats FROM categories WHERE id IN {$category_ids_});";
    $uppercats_of = query2array($query, 'id', 'uppercats');

    foreach (array_keys($uppercats_of) as $cat_id) {
        // find the subcats
        $subcat_ids = [];

        foreach ($uppercats_of as $id => $uppercats) {
            if (preg_match('/(^|,)' . $cat_id . '(,|$)/', (string) $uppercats)) {
                $subcat_ids[] = $id;
            }
        }

        $to_compare = [];
        foreach ($subcat_ids as $id) {
            if (isset($ref_dates[$id])) {
                $to_compare[] = $ref_dates[$id];
            }
        }

        if ($to_compare !== []) {
            $ref_dates[$cat_id] = $minmax === 'max' ? max($to_compare) : min($to_compare);
        } else {
            $ref_dates[$cat_id] = null;
        }
    }

    // only return the list of $ids, not the sub-categories
    $return = [];
    foreach ($ids as $id) {
        $return[$id] = $ref_dates[$id];
    }

    return $return;
}
