<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Returns the number of available tags for the connected user.
 *
 * @return int
 */
function get_nb_available_tags()
{
    global $user;
    if (! isset($user['nb_available_tags'])) {
        $user['nb_available_tags'] = count(get_available_tags());
        single_update(
            'user_cache',
            [
                'nb_available_tags' => $user['nb_available_tags'],
            ],
            [
                'user_id' => $user['id'],
            ]
        );
    }
    return $user['nb_available_tags'];
}

/**
 * Returns all available tags for the connected user (not sorted).
 * The returned list can be a subset of all existing tags due to permissions,
 * also tags with no images are not returned.
 *
 * @return array [id, name, counter, url_name]
 */
function get_available_tags($tag_ids = [])
{
    // we can find top fatter tags among reachable images
    $filters_and_forbidden = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'ic.image_id',
        ],
        ' AND '
    );
    $query = "SELECT tag_id, COUNT(DISTINCT(it.image_id)) AS counter FROM image_category ic INNER JOIN image_tag it ON ic.image_id = it.image_id WHERE 1 = 1 {$filters_and_forbidden}";

    if (is_array($tag_ids) and count($tag_ids) > 0) {
        $tag_ids_ = implode(',', $tag_ids);
        $query .= " AND tag_id IN ({$tag_ids_})";
    }

    $query .= ' GROUP BY tag_id;';
    $tag_counters = query2array($query, 'tag_id', 'counter');

    if (empty($tag_counters)) {
        return [];
    }

    $query = 'SELECT * FROM tags';
    $result = pwg_query($query);

    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $counter = intval(@$tag_counters[$row['id']]);
        if ($counter) {
            $row['counter'] = $counter;
            $row['name'] = trigger_change('render_tag_name', $row['name'], $row);
            $tags[] = $row;
        }
    }
    return $tags;
}

/**
 * Returns all tags even associated to no image.
 *
 * @return array [id, name, url_name]
 */
function get_all_tags()
{
    $query = 'SELECT * FROM tags;';
    $result = pwg_query($query);
    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['name'] = trigger_change('render_tag_name', $row['name'], $row);
        $tags[] = $row;
    }

    usort($tags, 'tag_alpha_compare');

    return $tags;
}

/**
 * Giving a set of tags with a counter for each one, calculate the display
 * level of each tag.
 *
 * The level of each tag depends on the average count of tags. This
 * calculation method avoid having very different levels for tags having
 * nearly the same count when set are small.
 *
 * @param array $tags at least [id, counter]
 * @return array [..., level]
 */
function add_level_to_tags($tags)
{
    global $conf;

    if (count($tags) == 0) {
        return $tags;
    }

    $total_count = 0;

    foreach ($tags as $tag) {
        $total_count += $tag['counter'];
    }

    // average count of available tags will determine the level of each tag
    $tag_average_count = $total_count / count($tags);

    // tag levels threshold calculation: a tag with an average rate must have
    // the middle level.
    for ($i = 1; $i < $conf['tags_levels']; $i++) {
        $threshold_of_level[$i] =
          2 * $i * $tag_average_count / $conf['tags_levels'];
    }

    // display sorted tags
    foreach ($tags as &$tag) {
        $tag['level'] = 1;

        // based on threshold, determine current tag level
        for ($i = $conf['tags_levels'] - 1; $i >= 1; $i--) {
            if ($tag['counter'] > $threshold_of_level[$i]) {
                $tag['level'] = $i + 1;
                break;
            }
        }
    }
    unset($tag);

    return $tags;
}

/**
 * Return the list of image ids corresponding to given tags.
 * AND & OR mode supported.
 *
 * @param int[] $tag_ids
 * @param string $mode
 * @param string $extra_images_where_sql - optionally apply a sql where filter to retrieved images
 * @param string $order_by - optionally overwrite default photo order
 * @return array
 */
function get_image_ids_for_tags($tag_ids, $mode = 'AND', $extra_images_where_sql = '', $order_by = '', $use_permissions = true)
{
    global $conf;
    if (empty($tag_ids)) {
        return [];
    }

    $query = 'SELECT id FROM images i ';

    if ($use_permissions) {
        $query .= ' INNER JOIN image_category ic ON id = ic.image_id';
    }

    $tag_ids_ = implode(',', $tag_ids);
    $query .= " INNER JOIN image_tag it ON id = it.image_id WHERE tag_id IN ({$tag_ids_})";

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

    $query .= (empty($extra_images_where_sql) ? '' : " \nAND ({$extra_images_where_sql})");
    $query .= ' GROUP BY id';

    if ($mode == 'AND' and count($tag_ids) > 1) {
        $query .= ' HAVING COUNT(DISTINCT tag_id) = ' . count($tag_ids);
    }
    $query .= "\n" . (empty($order_by) ? $conf['order_by'] : $order_by);
    $query .= ';';
    return query2array($query, null, 'id');
}

/**
 * Return a list of tags corresponding to given items.
 *
 * @param int[] $items
 * @param int $max_tags
 * @param int[] $excluded_tag_ids
 * @return array [id, name, counter, url_name]
 */
function get_common_tags($items, $max_tags, $excluded_tag_ids = [])
{
    if (empty($items)) {
        return [];
    }
    $items_ = implode(',', $items);
    $query = "SELECT t.*, COUNT(*) AS counter FROM image_tag INNER JOIN tags t ON tag_id = id WHERE image_id IN ({$items_})";
    if (! empty($excluded_tag_ids)) {
        $excluded_tag_ids_ = implode(',', $excluded_tag_ids);
        $query .= " AND tag_id NOT IN ({$excluded_tag_ids_})";
    }
    $query .= ' GROUP BY t.id ORDER BY ';
    if ($max_tags > 0) { // TODO : why ORDER field is in the if ?
        $query .= "counter DESC LIMIT {$max_tags}";
    } else {
        $query .= 'NULL';
    }

    $result = pwg_query($query);
    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['name'] = trigger_change('render_tag_name', $row['name'], $row);
        $tags[] = $row;
    }
    usort($tags, 'tag_alpha_compare');
    return $tags;
}

/**
 * Return a list of tags corresponding to any of ids, url_names or names.
 *
 * @param int[] $ids
 * @param string[] $url_names
 * @param string[] $names
 * @return array [id, name, url_name]
 */
function find_tags($ids = [], $url_names = [], $names = [])
{
    $where_clauses = [];
    if (! empty($ids)) {
        $ids_ = implode(',', $ids);
        $where_clauses[] = "id IN ({$ids_})";
    }
    if (! empty($url_names)) {
        $url_names_ = implode('\', \'', $url_names);
        $where_clauses[] = "url_name IN ('{$url_names_}')";
    }
    if (! empty($names)) {
        $names_ = implode('\', \'', $names);
        $where_clauses[] = "name IN ('{$names_}')";
    }
    if (empty($where_clauses)) {
        return [];
    }

    $where_clauses_ = implode(' OR ', $where_clauses);
    $query = "SELECT * FROM tags WHERE {$where_clauses_}";

    return query2array($query);
}

function tags_id_compare($a, $b)
{
    return ($a['id'] < $b['id']) ? -1 : 1;
}

function tags_counter_compare($a, $b)
{
    if ($a['counter'] == $b['counter']) {
        return tags_id_compare($a, $b);
    }

    return ($a['counter'] < $b['counter']) ? +1 : -1;
}
