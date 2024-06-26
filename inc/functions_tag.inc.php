<?php

declare(strict_types=1);

namespace Piwigo\inc;

use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\dbLayer\single_update;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Returns the number of available tags for the connected user.
 */
function get_nb_available_tags(): int
{
    global $user;
    if (! isset($user['nb_available_tags'])) {
        $user['nb_available_tags'] = count(get_available_tags());
        single_update(
            USER_CACHE_TABLE,
            [
                'nb_available_tags' => $user['nb_available_tags'],
            ],
            [
                'user_id' => $user['id'],
            ]
        );
    }

    return (int) $user['nb_available_tags'];
}

/**
 * Returns all available tags for the connected user (not sorted).
 * The returned list can be a subset of all existing tags due to permissions,
 * also tags with no images are not returned.
 *
 * @return array [id, name, counter, url_name]
 */
function get_available_tags(): array
{
    // we can find top fatter tags among reachable images
    $query = '
SELECT tag_id, COUNT(DISTINCT(it.image_id)) AS counter
  FROM ' . IMAGE_CATEGORY_TABLE . ' ic
    INNER JOIN ' . IMAGE_TAG_TABLE . ' it
    ON ic.image_id=it.image_id
  ' . get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'ic.image_id',
        ],
        ' WHERE '
    ) . '
  GROUP BY tag_id
;';
    $tag_counters = query2array($query, 'tag_id', 'counter');

    if ($tag_counters === []) {
        return [];
    }

    $query = '
SELECT *
  FROM ' . TAGS_TABLE;
    $result = pwg_query($query);

    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $counter = intval($tag_counters[$row['id']]);
        if ($counter !== 0) {
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
function get_all_tags(): array
{
    $query = '
SELECT *
  FROM ' . TAGS_TABLE . '
;';
    $result = pwg_query($query);
    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['name'] = trigger_change('render_tag_name', $row['name'], $row);
        $tags[] = $row;
    }

    usort($tags, '\Piwigo\inc\tag_alpha_compare');

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
function add_level_to_tags(
    array $tags
): array {
    global $conf;

    if (count($tags) == 0) {
        return $tags;
    }

    $total_count = 0;

    foreach ($tags as $tag) {
        $total_count += $tag['counter'];
    }

    // average count of available tags will determine the level of each tag
    $tag_average_count = $total_count / count(
        $tags
    );

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
 * @param string $extra_images_where_sql - optionally apply a sql where filter to retrieved images
 * @param string $order_by - optionally overwrite default photo order
 */
function get_image_ids_for_tags(
    array $tag_ids,
    string $mode = 'AND',
    string $extra_images_where_sql = '',
    string $order_by = '',
    bool $use_permissions = true
): array {
    global $conf;
    if ($tag_ids === []) {
        return [];
    }

    $query = '
SELECT id
  FROM ' . IMAGES_TABLE . ' i ';

    if ($use_permissions) {
        $query .= '
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' ic ON id=ic.image_id';
    }

    $query .= '
    INNER JOIN ' . IMAGE_TAG_TABLE . ' it ON id=it.image_id
    WHERE tag_id IN (' . implode(',', $tag_ids) . ')';

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

    $query .= ($extra_images_where_sql === '' || $extra_images_where_sql === '0' ? '' : " \nAND (" . $extra_images_where_sql . ')') . '
  GROUP BY id';

    if ($mode === 'AND' && count($tag_ids) > 1) {
        $query .= '
  HAVING COUNT(DISTINCT tag_id)=' . count($tag_ids);
    }

    $query .= "\n" . ($order_by === '' || $order_by === '0' ? $conf['order_by'] : $order_by);

    return query2array($query, null, 'id');
}

/**
 * Return a list of tags corresponding to given items.
 *
 * @param int[] $items
 * @param int[] $excluded_tag_ids
 * @return array [id, name, counter, url_name]
 */
function get_common_tags(
    array $items,
    int $max_tags,
    array $excluded_tag_ids = [
    ]
): array {
    if ($items === []) {
        return [];
    }

    $query = '
SELECT t.*, count(*) AS counter
  FROM ' . IMAGE_TAG_TABLE . '
    INNER JOIN ' . TAGS_TABLE . ' t ON tag_id = id
  WHERE image_id IN (' . implode(',', $items) . ')';
    if ($excluded_tag_ids !== []) {
        $query .= '
    AND tag_id NOT IN (' . implode(',', $excluded_tag_ids) . ')';
    }

    $query .= '
  GROUP BY t.id
  ORDER BY ';
    if ($max_tags > 0) { // TODO : why ORDER field is in the if ?
        $query .= 'counter DESC
  LIMIT ' . $max_tags;
    } else {
        $query .= 'NULL';
    }

    $result = pwg_query($query);
    $tags = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['name'] = trigger_change('render_tag_name', $row['name'], $row);
        $tags[] = $row;
    }

    usort($tags, '\Piwigo\inc\tag_alpha_compare');
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
function find_tags(
    array $ids = [],
    array $url_names = [],
    array $names = [
    ]
): array {
    $where_clauses = [];
    if ($ids !== []) {
        $where_clauses[] = 'id IN (' . implode(',', $ids) . ')';
    }

    if ($url_names !== []) {
        $where_clauses[] =
          "url_name IN ('" . implode("', '", $url_names) . "')";
    }

    if ($names !== []) {
        $where_clauses[] =
          "name IN ('" . implode("', '", $names) . "')";
    }

    if ($where_clauses === []) {
        return [];
    }

    $query = '
SELECT *
  FROM ' . TAGS_TABLE . '
  WHERE ' . implode('
    OR ', $where_clauses);

    return query2array($query);
}

function tags_id_compare($a, $b): int
{
    return ($a['id'] < $b['id']) ? -1 : 1;
}

function tags_counter_compare($a, $b): int
{
    if ($a['counter'] == $b['counter']) {
        return tags_id_compare($a, $b);
    }

    return ($a['counter'] < $b['counter']) ? +1 : -1;
}
