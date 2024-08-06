<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Callback used for sorting by global_rank
 */
function global_rank_compare(
    array $a,
    array $b
): int {
    return strnatcasecmp($a['global_rank'], $b['global_rank']);
}

/**
 * Callback used for sorting by rank
 */
function rank_compare(
    array $a,
    array $b
): float|int {
    return $a['rank'] - $b['rank'];
}

/**
 * Is the category accessible to the connected user ?
 * If the user is not authorized to see this category, script exits
 */
function check_restrictions(
    string $category_id
): void {
    global $user;

    // $filter['visible_categories'] and $filter['visible_images']
    // are not used because it's not necessary (filter <> restriction)
    if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
        access_denied();
    }
}

/**
 * Returns template vars for main categories menu.
 *
 * @return array[]
 */
function get_categories_menu(): array
{
    global $page, $user, $filter, $conf;

    $query = 'SELECT ';
    // From categories
    $query .= ' id, name, permalink, nb_images, global_rank,';
    // From user_cache_categories
    $query .= ' date_last, max_date_last, count_images, count_categories';

    // $user['forbidden_categories'] including with user_cache_categories
    $query .= " FROM categories INNER JOIN user_cache_categories ON id = cat_id and user_id = {$user['id']}";

    // Always expand when filter is activated
    if (! $user['expand'] and ! $filter['enabled']) {
        $where = ' (id_uppercat is NULL';
        if (isset($page['category'])) {
            $where .= " OR id_uppercat IN ({$page['category']['uppercats']})";
        }
        $where .= ')';
    } else {
        $where = ' ' . get_sql_condition_FandF(
            [
                'visible_categories' => 'id',
            ],
            null,
            true
        );
    }

    $where = trigger_change(
        'get_categories_menu_sql_where',
        $where,
        $user['expand'],
        $filter['enabled']
    );

    $query .= " WHERE {$where};";
    $result = pwg_query($query);
    $cats = [];
    $selected_category = isset($page['category']) ? $page['category'] : null;
    while ($row = pwg_db_fetch_assoc($result)) {
        $child_date_last = @$row['max_date_last'] > @$row['date_last'];
        $row = array_merge(
            $row,
            [
                'NAME' => trigger_change(
                    'render_category_name',
                    $row['name'],
                    'get_categories_menu'
                ),
                'TITLE' => get_display_images_count(
                    $row['nb_images'],
                    $row['count_images'],
                    $row['count_categories'],
                    false,
                    ' / '
                ),
                'URL' => make_index_url([
                    'category' => $row,
                ]),
                'LEVEL' => substr_count($row['global_rank'], '.') + 1,
                'SELECTED' => ($selected_category !== null && $selected_category['id'] == $row['id']) ? true : false,
                'IS_UPPERCAT' => ($selected_category !== null && $selected_category['id_uppercat'] == $row['id']) ? true : false,
            ]
        );
        if ($conf['index_new_icon']) {
            $row['icon_ts'] = get_icon($row['max_date_last'], $child_date_last);
        }
        $cats[] = $row;
        if ($row['id'] == ($page['category']['id'] ?? null)) { //save the number of subcats for later optim
            $page['category']['count_categories'] = $row['count_categories'];
        }
    }
    usort($cats, 'global_rank_compare');

    // Update filtered data
    if (function_exists('update_cats_with_filtered_data')) {
        update_cats_with_filtered_data($cats);
    }

    return $cats;
}

/**
 * Retrieves informations about a category.
 */
function get_cat_info(
    string|int $id
): array|null {
    $query = "SELECT * FROM categories WHERE id = {$id};";
    $cat = pwg_db_fetch_assoc(pwg_query($query));
    if (empty($cat)) {
        return null;
    }

    foreach ($cat as $k => $v) {
        // If the field is true or false, the variable is transformed into a
        // boolean value.
        if ($cat[$k] == 'true' or $cat[$k] == 'false') {
            $cat[$k] = get_boolean($cat[$k]);
        }
    }

    $upper_ids = explode(',', $cat['uppercats']);
    if (count($upper_ids) == 1) {// no need to make a query for level 1
        $cat['upper_names'] = [
            [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'permalink' => $cat['permalink'],
            ],
        ];
    } else {
        $query = "SELECT id, name, permalink FROM categories WHERE id IN ({$cat['uppercats']});";
        $names = query2array($query, 'id');

        // category names must be in the same order than uppercats list
        $cat['upper_names'] = [];
        foreach ($upper_ids as $cat_id) {
            $cat['upper_names'][] = $names[$cat_id];
        }
    }
    return $cat;
}

/**
 * Returns an array of image orders available for users/visitors.
 * Each entry is an array containing
 *  0: name
 *  1: SQL ORDER command
 *  2: visiblity (true or false)
 *
 * @return array[]
 */
function get_category_preferred_image_orders(): array
{
    global $conf, $page;

    return trigger_change('get_category_preferred_image_orders', [
        [l10n('Default'),                        '',                     true],
        [l10n('Photo title, A &rarr; Z'),        'name ASC',             true],
        [l10n('Photo title, Z &rarr; A'),        'name DESC',            true],
        [l10n('Date created, new &rarr; old'),   'date_creation DESC',   true],
        [l10n('Date created, old &rarr; new'),   'date_creation ASC',    true],
        [l10n('Date posted, new &rarr; old'),    'date_available DESC',  true],
        [l10n('Date posted, old &rarr; new'),    'date_available ASC',   true],
        [l10n('Rating score, high &rarr; low'),  'rating_score DESC',    $conf['rate']],
        [l10n('Rating score, low &rarr; high'),  'rating_score ASC',     $conf['rate']],
        [l10n('Visits, high &rarr; low'),        'hit DESC',             true],
        [l10n('Visits, low &rarr; high'),        'hit ASC',              true],
        [l10n('Permissions'),                    'level DESC',           is_admin()],
    ]);
}

/**
 * Assign a template var useable with {html_options} from a list of categories
 *
 * @param array[] $categories (at least id,name,global_rank,uppercats for each)
 * @param string $blockname variable name in template
 * @param bool $fullname full breadcrumb or not
 */
function display_select_categories(
    array $categories,
    array $selecteds,
    string $blockname,
    bool $fullname = true
): void {
    global $template;

    $tpl_cats = [];
    foreach ($categories as $category) {
        if ($fullname) {
            $option = strip_tags(
                get_cat_display_name_cache(
                    $category['uppercats'],
                    null
                )
            );
        } else {
            $option = str_repeat(
                '&nbsp;',
                (3 * substr_count($category['global_rank'], '.'))
            );
            $option .= '- ';
            $option .= strip_tags(
                trigger_change(
                    'render_category_name',
                    $category['name'],
                    'display_select_categories'
                )
            );
        }
        $tpl_cats[$category['id']] = $option;
    }

    $template->assign($blockname, $tpl_cats);
    $template->assign($blockname . '_selected', $selecteds);
}

/**
 * Same as display_select_categories but categories are ordered by rank
 * @see display_select_categories()
 */
function display_select_cat_wrapper(
    string $query,
    array $selecteds,
    string $blockname,
    bool $fullname = true
): void {
    $categories = query2array($query);
    usort($categories, 'global_rank_compare');
    display_select_categories($categories, $selecteds, $blockname, $fullname);
}

/**
 * Returns all subcategory identifiers of given category ids
 *
 * @param int[] $ids
 * @return int[]
 */
function get_subcat_ids(
    array $ids
): array {
    $query = 'SELECT DISTINCT(id) FROM categories WHERE ';
    foreach ($ids as $num => $category_id) {
        is_numeric($category_id)
          or trigger_error(
              'get_subcat_ids expecting numeric, not ' . gettype($category_id),
              E_USER_WARNING
          );
        if ($num > 0) {
            $query .= ' OR ';
        }
        $query .= 'uppercats ' . DB_REGEX_OPERATOR . " '(^|,){$category_id}(,|$)'";
    }
    $query .= ';';
    return query2array($query, null, 'id');
}

/**
 * Finds a matching category id from a potential list of permalinks
 *
 * @param string[] $permalinks
 * @param int $idx filled with the index in $permalinks that matches
 * @return int|null
 */
function get_cat_id_from_permalinks(
    array $permalinks,
    int &$idx
): mixed {
    $in = '';
    foreach ($permalinks as $permalink) {
        if (! empty($in)) {
            $in .= ', ';
        }
        $in .= "'{$permalink}'";
    }
    $query = "SELECT cat_id AS id, permalink, 1 AS is_old FROM old_permalinks WHERE permalink IN ({$in}) UNION SELECT id, permalink, 0 AS is_old FROM categories WHERE permalink IN ({$in});";
    $perma_hash = query2array($query, 'permalink');

    if (empty($perma_hash)) {
        return null;
    }
    for ($i = count($permalinks) - 1; $i >= 0; $i--) {
        if (isset($perma_hash[$permalinks[$i]])) {
            $idx = $i;
            $cat_id = $perma_hash[$permalinks[$i]]['id'];
            if ($perma_hash[$permalinks[$i]]['is_old']) {
                $query = "UPDATE old_permalinks SET last_hit = NOW(), hit = hit + 1 WHERE permalink = '{$permalinks[$i]}' AND cat_id = {$cat_id} LIMIT 1";
                pwg_query($query);
            }
            return $cat_id;
        }
    }
    return null;
}

/**
 * Returns display text for images counter of category
 *
 * @param int $cat_nb_images nb images directly in category
 * @param int $cat_count_images nb images in category (including subcats)
 * @param int $cat_count_categories nb subcats
 * @param bool $short_message if true append " in this album"
 */
function get_display_images_count(
    int $cat_nb_images,
    int $cat_count_images,
    int $cat_count_categories,
    bool $short_message = true,
    string $separator = '\n'
): string {
    $display_text = '';

    if ($cat_count_images > 0) {
        if ($cat_nb_images > 0 and $cat_nb_images < $cat_count_images) {
            $display_text .= get_display_images_count($cat_nb_images, $cat_nb_images, 0, $short_message, $separator) . $separator;
            $cat_count_images -= $cat_nb_images;
            $cat_nb_images = 0;
        }

        //at least one image direct or indirect
        $display_text .= l10n_dec('%d photo', '%d photos', $cat_count_images);

        if ($cat_count_categories == 0 or $cat_nb_images == $cat_count_images) {
            //no descendant categories or descendants do not contain images
            if (! $short_message) {
                $display_text .= ' ' . l10n('in this album');
            }
        } else {
            $display_text .= ' ' . l10n_dec('in %d sub-album', 'in %d sub-albums', $cat_count_categories);
        }
    }

    return $display_text;
}

/**
 * Find a random photo among all photos inside an album (including sub-albums)
 *
 * @param array $category (at least id,uppercats,count_images)
 * @return int|null
 */
function get_random_image_in_category(
    array $category,
    bool $recursive = true
): mixed {
    $image_id = null;
    $filters_and_forbidden = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'c.id',
            'visible_categories' => 'c.id',
            'visible_images' => 'image_id',
        ],
        "\n  AND"
    );
    if ($category['count_images'] > 0) {
        $query = 'SELECT image_id FROM categories AS c INNER JOIN image_category AS ic ON ic.category_id = c.id WHERE ';
        if ($recursive) {
            $query .= " (c.id = {$category['id']} OR uppercats LIKE '{$category['uppercats']},%')";
        } else {
            $query .= " c.id = {$category['id']}";
        }
        $query .= " {$filters_and_forbidden} ORDER BY " . DB_RANDOM_FUNCTION . '() LIMIT 1;';
        $result = pwg_query($query);
        if (pwg_db_num_rows($result) > 0) {
            list($image_id) = pwg_db_fetch_row($result);
        }
    }

    return $image_id;
}

/**
 * Get computed array of categories, that means cache data of all categories
 * available for the current user (count_categories, count_images, etc.).
 *
 * @param int $filter_days number of recent days to filter on or null
 */
function get_computed_categories(
    array &$userdata,
    int $filter_days = null
): array {
    // Count by date_available to avoid count null
    $query =
    "SELECT c.id AS cat_id, id_uppercat, global_rank, MAX(date_available) AS date_last, COUNT(date_available) AS nb_images
     FROM categories as c LEFT JOIN image_category AS ic ON ic.category_id = c.id LEFT JOIN images AS i ON ic.image_id = i.id
     AND i.level <= {$userdata['level']}";

    if (isset($filter_days)) {
        $query .= ' AND i.date_available > ' . pwg_db_get_recent_period_expression($filter_days);
    }

    if (! empty($userdata['forbidden_categories'])) {
        $query .= " WHERE c.id NOT IN ({$userdata['forbidden_categories']})";
    }

    $query .= ' GROUP BY c.id;';
    $result = pwg_query($query);

    $userdata['last_photo_date'] = null;
    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['user_id'] = $userdata['id'];
        $row['nb_categories'] = 0;
        $row['count_categories'] = 0;
        $row['count_images'] = (int) $row['nb_images'];
        $row['max_date_last'] = $row['date_last'];
        if ($row['date_last'] > $userdata['last_photo_date']) {
            $userdata['last_photo_date'] = $row['date_last'];
        }

        $cats[$row['cat_id']] = $row;
    }

    // it is important to logically sort the albums because some operations
    // (like removal) rely on this logical order. Child album doesn't always
    // have a bigger id than its parent (if it was moved afterwards).
    uasort($cats, 'global_rank_compare');

    foreach ($cats as $cat) {
        if (! isset($cat['id_uppercat'])) {
            continue;
        }

        // Piwigo before 2.5.3 may have generated inconsistent permissions, ie
        // private album A1/A2 permitted to user U1 but private album A1 not
        // permitted to U1.
        //
        // TODO 2.7: add an upgrade script to repair permissions and remove this
        // test
        if (! isset($cats[$cat['id_uppercat']])) {
            continue;
        }

        $parent = &$cats[$cat['id_uppercat']];
        $parent['nb_categories']++;

        do {
            $parent['count_images'] += $cat['nb_images'];
            $parent['count_categories']++;

            if ((empty($parent['max_date_last'])) or ($parent['max_date_last'] < $cat['date_last'])) {
                $parent['max_date_last'] = $cat['date_last'];
            }

            if (! isset($parent['id_uppercat'])) {
                break;
            }
            $parent = &$cats[$parent['id_uppercat']];
        } while (true);
        unset($parent);
    }

    if (isset($filter_days)) {
        foreach ($cats as $category) {
            if (empty($category['max_date_last'])) {
                remove_computed_category($cats, $category);
            }
        }
    }

    return $cats;
}

/**
 * Removes a category from computed array of categories and updates counters.
 *
 * @param array $cat category to remove
 */
function remove_computed_category(
    array &$cats,
    array $cat
): void {
    if (isset($cats[$cat['id_uppercat']])) {
        $parent = &$cats[$cat['id_uppercat']];
        $parent['nb_categories']--;

        do {
            $parent['count_images'] -= $cat['nb_images'];
            $parent['count_categories'] -= 1 + $cat['count_categories'];

            if (! isset($cats[$parent['id_uppercat']])) {
                break;
            }
            $parent = &$cats[$parent['id_uppercat']];
        } while (true);
    }

    unset($cats[$cat['cat_id']]);
}

/**
 * Return the list of image ids corresponding to given categories.
 * AND & OR mode supported.
 *
 * @param int[] $cat_ids
 * @param string $extra_images_where_sql - optionally apply a sql where filter to retrieved images
 * @param string $order_by - optionally overwrite default photo order
 */
function get_image_ids_for_categories(
    array $cat_ids,
    string $mode = 'AND',
    string $extra_images_where_sql = '',
    string $order_by = '',
    bool $use_permissions = true
): array {
    global $conf;

    if (empty($cat_ids)) {
        return [];
    }

    $cat_ids_ = implode(',', $cat_ids);
    $query = "SELECT id FROM images i INNER JOIN image_category ic ON id = ic.image_id WHERE category_id IN ({$cat_ids_})";

    if ($use_permissions) {
        $query .= get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id',
            ],
            "\n  AND"
        );
    }

    $query .= (empty($extra_images_where_sql) ? '' : " \nAND ({$extra_images_where_sql})") . ' GROUP BY id';

    if ($mode == 'AND' and count($cat_ids) > 1) {
        $query .= ' HAVING COUNT(DISTINCT category_id) = ' . count($cat_ids);
    }
    $query .= "\n" . (empty($order_by) ? $conf['order_by'] : $order_by);

    return query2array($query, null, 'id');
}

/**
 * Return a list of categories corresponding to given items.
 *
 * @param int[] $items
 * @param int[] $excluded_cat_ids
 * @return array [id, name, counter, url_name]
 */
function get_common_categories(
    array $items,
    int $max = null,
    array $excluded_cat_ids = [],
    bool $use_permissions = true
): array {
    if (empty($items)) {
        return [];
    }

    $items_ = implode(',', $items);
    $query = "SELECT c.id, c.uppercats, COUNT(*) AS counter FROM image_category INNER JOIN categories c ON category_id = id WHERE image_id IN ({$items_})";

    if ($use_permissions) {
        $query .= get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
            ],
            "\n    AND"
        );
    }

    if (! empty($excluded_cat_ids)) {
        $excluded_cat_ids_ = implode(',', $excluded_cat_ids);
        $query .= " AND category_id NOT IN ({$excluded_cat_ids_})";
    }

    $query .= ' GROUP BY c.id ORDER BY ';
    if (isset($max)) {
        $query .= "counter DESC LIMIT {$max}";
    } else {
        $query .= 'NULL';
    }

    $result = pwg_query($query);
    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $cats[$row['id']] = $row;
    }

    return $cats;
}

function get_related_categories_menu(
    array $items,
    array $excluded_cat_ids = []
): array {
    global $page, $conf;

    $common_cats = get_common_categories($items, $conf['related_albums_display_limit'], $excluded_cat_ids);
    // echo '<pre>'; print_r($common_cats); echo '</pre>';

    if (count($common_cats) == 0) {
        return [];
    }

    $cat_ids = [];
    // now we add the upper categories and useful values such as depth level and url
    foreach ($common_cats as $cat) {
        foreach (explode(',', $cat['uppercats']) as $uppercat) {
            @$cat_ids[$uppercat]++;
        }
    }

    $cat_ids_ = implode(',', array_keys($cat_ids));
    $query = "SELECT id, name, permalink, id_uppercat, uppercats, global_rank FROM categories WHERE id IN ({$cat_ids_});";
    $cats = query2array($query);
    usort($cats, 'global_rank_compare');

    $index_of_cat = [];

    foreach ($cats as $idx => $cat) {
        $index_of_cat[$cat['id']] = $idx;
        $cats[$idx]['LEVEL'] = substr_count($cat['global_rank'], '.') + 1;
        $cats[$idx]['name'] = trigger_change('render_category_name', $cat['name'], $cat);

        // if the category is directly linked to the items, we add an URL + counter
        if (isset($common_cats[$cat['id']])) {
            $cats[$idx]['count_images'] = $common_cats[$cat['id']]['counter'];

            $url_params = [];
            if (isset($page['category'])) {
                $url_params['category'] = $page['category'];

                $url_params['combined_categories'] = [$cat];
                if (isset($page['combined_categories'])) {
                    $url_params['combined_categories'] = array_merge($page['combined_categories'], [$cat]);
                }
            } else {
                $url_params['category'] = $cat;
            }

            $cats[$idx]['url'] = make_index_url($url_params);
        }

        // let's find how many sub-categories we have for each category. 3 options:
        // 1. direct sub-albums
        // 2. total indirect sub-albums
        // 3. number of sub-albums containing photos
        //
        // Option 3 seems more appropriate here.
        if (! empty($cat['id_uppercat']) and @$cats[$idx]['count_images'] > 0) {
            foreach (array_slice(explode(',', $cat['uppercats']), 0, -1) as $uppercat_id) {
                @$cats[$index_of_cat[$uppercat_id]]['count_categories']++;
            }
        }
    }

    return $cats;
}
