<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * API method
 * Returns a list of tags
 * @param mixed[] $params
 *    @option bool sort_by_counter
 */
function ws_tags_getList(
    array $params,
    PwgServer &$service
): array {
    $tags = get_available_tags();
    if ($params['sort_by_counter']) {
        usort($tags, fn (array $a, array $b): float|int => -$a['counter'] + $b['counter']);
    } else {
        usort($tags, tag_alpha_compare(...));
    }

    $counter = count($tags);

    for ($i = 0; $i < $counter; $i++) {
        $tags[$i]['id'] = (int) $tags[$i]['id'];
        $tags[$i]['counter'] = (int) $tags[$i]['counter'];
        $tags[$i]['url'] = make_index_url(
            [
                'section' => 'tags',
                'tags' => [$tags[$i]],
            ]
        );
    }

    return [
        'tags' => new PwgNamedArray(
            $tags,
            'tag',
            ws_std_get_tag_xml_attributes()
        ),
    ];
}

/**
 * API method
 * Returns the list of tags as you can see them in administration
 * @param mixed[] $params
 *
 * Only admin can run this method and permissions are not taken into
 * account.
 */
function ws_tags_getAdminList(
    array $params,
    PwgServer &$service
): array {
    return [
        'tags' => new PwgNamedArray(
            get_all_tags(),
            'tag',
            ws_std_get_tag_xml_attributes()
        ),
    ];
}

/**
 * API method
 * Returns a list of images for tags
 * @param mixed[] $params
 *    @option int[] tag_id (optional)
 *    @option string[] tag_url_name (optional)
 *    @option string[] tag_name (optional)
 *    @option bool tag_mode_and
 *    @option int per_page
 *    @option int page
 *    @option string order
 */
function ws_tags_getImages(
    array $params,
    PwgServer &$service
): array {
    // first build all the tag_ids we are interested in
    $tags = find_tags($params['tag_id'], $params['tag_url_name'], $params['tag_name']);
    $tags_by_id = [];
    foreach ($tags as $tag) {
        $tags['id'] = (int) $tag['id'];
        $tags_by_id[$tag['id']] = $tag;
    }

    unset($tags);
    $tag_ids = array_keys($tags_by_id);

    $where_clauses = ws_std_image_sql_filter($params);
    if ($where_clauses !== []) {
        $where_clauses = implode(' AND ', $where_clauses);
    }

    $order_by = ws_std_image_sql_order($params, 'i.');
    if ($order_by !== '' && $order_by !== '0') {
        $order_by = 'ORDER BY ' . $order_by;
    }

    $image_ids = get_image_ids_for_tags(
        $tag_ids,
        $params['tag_mode_and'] ? 'AND' : 'OR',
        $where_clauses,
        $order_by
    );

    $count_set = count($image_ids);
    $image_ids = array_slice($image_ids, $params['per_page'] * $params['page'], $params['per_page']);

    $image_tag_map = [];
    // build list of image ids with associated tags per image
    if ($image_ids !== [] && ! $params['tag_mode_and']) {
        $tagIds = implode(',', $tag_ids);
        $imageIds = implode(',', $image_ids);
        $query = <<<SQL
            SELECT image_id, GROUP_CONCAT(tag_id) AS tag_ids
            FROM image_tag
            WHERE tag_id IN ({$tagIds}) AND image_id IN ({$imageIds})
            GROUP BY image_id;
            SQL;
        $result = pwg_query($query);

        while ($row = pwg_db_fetch_assoc($result)) {
            $row['image_id'] = (int) $row['image_id'];
            $image_tag_map[$row['image_id']] = explode(',', (string) $row['tag_ids']);
        }
    }

    $images = [];
    if ($image_ids !== []) {
        $rank_of = array_flip($image_ids);
        $favorite_ids = get_user_favorites();

        $imageIds = implode(',', $image_ids);
        $query = <<<SQL
            SELECT *
            FROM images
            WHERE id IN ({$imageIds});
            SQL;
        $result = pwg_query($query);

        while ($row = pwg_db_fetch_assoc($result)) {
            $image = [];
            $image['rank'] = $rank_of[$row['id']];
            $image['is_favorite'] = isset($favorite_ids[$row['id']]);

            foreach (['id', 'width', 'height', 'hit'] as $k) {
                if (isset($row[$k])) {
                    $image[$k] = (int) $row[$k];
                }
            }

            foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
                $image[$k] = $row[$k];
            }

            $image = array_merge($image, ws_std_get_urls($row));

            $image_tag_ids = ($params['tag_mode_and']) ? $tag_ids : $image_tag_map[$image['id']];
            $image_tags = [];
            foreach ($image_tag_ids as $tag_id) {
                $url = make_index_url(
                    [
                        'section' => 'tags',
                        'tags' => [$tags_by_id[$tag_id]],
                    ]
                );
                $page_url = make_picture_url(
                    [
                        'section' => 'tags',
                        'tags' => [$tags_by_id[$tag_id]],
                        'image_id' => $row['id'],
                        'image_file' => $row['file'],
                    ]
                );
                $image_tags[] = [
                    'id' => (int) $tag_id,
                    'url' => $url,
                    'page_url' => $page_url,
                ];
            }

            $image['tags'] = new PwgNamedArray($image_tags, 'tag', ws_std_get_tag_xml_attributes());
            $images[] = $image;
        }

        usort($images, rank_compare(...));
        unset($rank_of);
    }

    return [
        'paging' => new PwgNamedStruct(
            [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images),
                'total_count' => $count_set,
            ]
        ),
        'images' => new PwgNamedArray(
            $images,
            'image',
            ws_std_get_image_xml_attributes()
        ),
    ];
}

/**
 * API method
 * Adds a tag
 * @param mixed[] $params
 *    @option string name
 */
function ws_tags_add(
    array $params,
    PwgServer &$service
): array|PwgError {
    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    $creation_output = create_tag($params['name']);

    if (isset($creation_output['error'])) {
        return new PwgError(WS_ERR_INVALID_PARAM, $creation_output['error']);
    }

    pwg_activity('tag', $creation_output['id'], 'add');

    $query = <<<SQL
        SELECT name, url_name
        FROM tags
        WHERE id = {$creation_output['id']};
        SQL;

    $new_tag = query2array($query);

    return [
        'info' => $creation_output['info'],
        'id' => $creation_output['id'],
        'name' => $new_tag[0]['name'],
        'url_name' => $new_tag[0]['url_name'],
    ];
}

function ws_tags_delete(
    array $params,
    PwgServer &$service
): array|PwgError {
    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $tag_ids = implode(',', $params['tag_id']);
    $query = <<<SQL
        SELECT COUNT(*)
        FROM tags
        WHERE id IN ({$tag_ids});
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count != count($params['tag_id'])) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'All tags does not exist.');
    }

    $tag_ids = $params['tag_id'];

    if (count($tag_ids) > 0) {
        delete_tags($params['tag_id']);
        return [
            'id' => $tag_ids,
        ];
    }

    return [
        'id' => [],
    ];

}

function ws_tags_rename(
    array $params,
    PwgServer &$service
): array|PwgError {
    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $tag_id = $params['tag_id'];
    $tag_name = strip_tags(stripslashes((string) $params['new_name']));

    // does the tag exist?
    $query = <<<SQL
        SELECT COUNT(*)
        FROM tags
        WHERE id = {$tag_id};
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This tag does not exist.');
    }

    $query = <<<SQL
        SELECT name
        FROM tags
        WHERE id != {$tag_id};
        SQL;
    $existing_names = query2array($query, null, 'name');

    $update = [];

    if (in_array($tag_name, $existing_names)) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already token');
    } elseif ($tag_name !== '' && $tag_name !== '0') {
        $update = [
            'name' => pwg_db_real_escape_string($tag_name),
            'url_name' => trigger_change('render_tag_url', $tag_name),
        ];

    }

    pwg_activity('tag', $tag_id, 'edit');

    single_update(
        'tags',
        $update,
        [
            'id' => $tag_id,
        ]
    );

    $query = <<<SQL
        SELECT id, name, url_name
        FROM tags
        WHERE id = {$tag_id};
        SQL;

    return query2array($query)[0];
}

function ws_tags_duplicate(
    array $params,
    PwgServer &$service
): array|PwgError {

    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $tag_id = $params['tag_id'];
    $copy_name = $params['copy_name'];

    // does the tag exist?
    $query = <<<SQL
        SELECT COUNT(*)
        FROM tags
        WHERE id = {$tag_id};
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This tag does not exist.');
    }

    $query = <<<SQL
        SELECT COUNT(*)
        FROM tags
        WHERE name = '{$copy_name}';
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count != 0) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already taken.');
    }

    single_insert(
        'tags',
        [
            'name' => $copy_name,
            'url_name' => trigger_change('render_tag_url', $copy_name),
        ]
    );
    $destination_tag_id = pwg_db_insert_id();

    pwg_activity('tag', $destination_tag_id, 'add', [
        'action' => 'duplicate',
        'source_tag' => $tag_id,
    ]);

    $query = <<<SQL
        SELECT image_id
        FROM image_tag
        WHERE tag_id = {$tag_id};
        SQL;
    $destination_tag_image_ids = query2array($query, null, 'image_id');

    $inserts = [];

    foreach ($destination_tag_image_ids as $image_id) {
        $inserts[] = [
            'tag_id' => $destination_tag_id,
            'image_id' => $image_id,
        ];
        pwg_activity('photo', $image_id, 'edit', [
            'add-tag' => $destination_tag_id,
        ]);
    }

    if ($inserts !== []) {
        mass_inserts(
            'image_tag',
            array_keys($inserts[0]),
            $inserts
        );
    }

    return [
        'id' => $destination_tag_id,
        'name' => $copy_name,
        'url_name' => trigger_change('render_tag_url', $copy_name),
        'count' => count($inserts),
    ];
}

function ws_tags_merge(
    array $params,
    PwgServer &$service
): array|PwgError {

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $all_tags = $params['merge_tag_id'];
    $all_tags[] = $params['destination_tag_id'];

    $all_tags = array_unique($all_tags);
    $merge_tag = array_diff($params['merge_tag_id'], [$params['destination_tag_id']]);

    $all_tags_imploded = implode(',', $all_tags);
    $query = <<<SQL
        SELECT COUNT(*)
        FROM tags
        WHERE id IN ({$all_tags_imploded});
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count != count($all_tags)) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'All tags does not exist.');
    }

    $image_in_merge_tags = [];
    $image_in_dest = [];
    $image_to_add = [];

    $merge_tag_imploded = implode(',', $merge_tag);
    $query = <<<SQL
        SELECT DISTINCT(image_id)
        FROM image_tag
        WHERE tag_id IN ({$merge_tag_imploded});
        SQL;
    $image_in_merge_tags = query2array($query, null, 'image_id');

    $query = <<<SQL
        SELECT image_id
        FROM image_tag
        WHERE tag_id = {$params['destination_tag_id']};
        SQL;

    $image_in_dest = query2array($query, null, 'image_id');

    $image_to_add = array_diff($image_in_merge_tags, $image_in_dest);

    $inserts = [];
    foreach ($image_to_add as $image) {
        $inserts[] = [
            'tag_id' => $params['destination_tag_id'],
            'image_id' => $image,
        ];
    }

    mass_inserts(
        'image_tag',
        ['tag_id', 'image_id'],
        $inserts,
        [
            'ignore' => true,
        ]
    );

    pwg_activity('tag', $params['destination_tag_id'], 'edit');
    foreach ($image_to_add as $image_id) {
        pwg_activity('photo', $image_id, 'edit', [
            'tag-add' => $params['destination_tag_id'],
        ]);
    }

    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    delete_tags($merge_tag);

    $image_in_merged = array_merge($image_in_dest, $image_to_add);

    return [
        'destination_tag' => $params['destination_tag_id'],
        'deleted_tag' => $params['merge_tag_id'],
        'images_in_merged_tag' => $image_in_merged,
    ];
}
