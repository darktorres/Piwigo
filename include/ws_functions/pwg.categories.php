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
 * Returns images per category
 * @param mixed[] $params
 *    @option int[] cat_id (optional)
 *    @option bool recursive
 *    @option int per_page
 *    @option int page
 *    @option string order (optional)
 */
function ws_categories_getImages(
    array $params,
    PwgServer &$service
): array|PwgError {
    global $user, $conf;

    $params['cat_id'] = array_unique($params['cat_id']);

    if ($params['cat_id'] !== []) {
        // do the categories really exist?
        $cat_ids_list = implode(',', $params['cat_id']);
        $query = <<<SQL
            SELECT id
            FROM categories
            WHERE id IN ({$cat_ids_list});
            SQL;
        $db_cat_ids = query2array($query, null, 'id');
        $missing_cat_ids = array_diff($params['cat_id'], $db_cat_ids);

        if ($missing_cat_ids !== []) {
            return new PwgError(404, 'cat_id {' . implode(',', $missing_cat_ids) . '} not found');
        }
    }

    $images = [];
    $image_ids = [];
    $total_images = 0;

    //------------------------------------------------- get the related categories
    $where_clauses = [];
    foreach ($params['cat_id'] as $cat_id) {
        if ($params['recursive']) {
            $where_clauses[] = 'uppercats ' . DB_REGEX_OPERATOR . " '(^|,){$cat_id}(,|$)'";
        } else {
            $where_clauses[] = "id = {$cat_id}";
        }
    }

    if ($where_clauses !== []) {
        $where_clauses = ['(' . implode("\n    OR ", $where_clauses) . ')'];
    }

    $where_clauses[] = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'id',
        ],
        null,
        true
    );

    $where_condition = implode("\n    AND ", $where_clauses);
    $query = <<<SQL
        SELECT id, image_order
        FROM categories
        WHERE {$where_condition};
        SQL;
    $result = pwg_query($query);

    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['id'] = (int) $row['id'];
        $cats[$row['id']] = $row;
    }

    //-------------------------------------------------------- get the images
    if ($cats !== []) {
        $where_clauses = ws_std_image_sql_filter($params, 'i.');
        $where_clauses[] = 'category_id IN (' . implode(',', array_keys($cats)) . ')';
        $where_clauses[] = get_sql_condition_FandF(
            [
                'visible_images' => 'i.id',
            ],
            null,
            true
        );

        $order_by = ws_std_image_sql_order($params, 'i.');
        if (($order_by === '' || $order_by === '0') && count($params['cat_id']) == 1 && isset($cats[$params['cat_id'][0]]['image_order'])
        ) {
            $order_by = $cats[$params['cat_id'][0]]['image_order'];
        }

        $order_by = empty($order_by) ? $conf['order_by'] : "ORDER BY {$order_by}";
        $favorite_ids = get_user_favorites();

        $where_condition = implode("\n    AND ", $where_clauses);
        $offset = $params['per_page'] * $params['page'];
        $query = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS i.*
            FROM images i
            INNER JOIN image_category ON i.id = image_id
            WHERE {$where_condition}
            GROUP BY i.id
            {$order_by}
            LIMIT {$params['per_page']} OFFSET {$offset};
            SQL;
        $result = pwg_query($query);

        while ($row = pwg_db_fetch_assoc($result)) {
            $image_ids[] = $row['id'];

            $image = [];
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

            $images[] = $image;
        }

        [$total_images] = pwg_db_fetch_row(pwg_query('SELECT FOUND_ROWS();'));

        // let's take care of adding the related albums to each photo
        if ($image_ids !== []) {
            $category_ids = [];

            // find the complete list (given permissions) of albums linked to photos
            $image_ids_imploded = implode(',', $image_ids);
            $sql_condition = get_sql_condition_FandF([
                'forbidden_categories' => 'category_id',
            ], null, true);

            $query = <<<SQL
                SELECT image_id, category_id
                FROM image_category
                WHERE image_id IN ({$image_ids_imploded})
                    AND {$sql_condition};
                SQL;
            $result = pwg_query($query);
            while ($row = pwg_db_fetch_assoc($result)) {
                $category_ids[] = $row['category_id'];
                $categories_of_image[$row['image_id']][] = $row['category_id'];
            }

            if ($category_ids !== []) {
                // find details (for URL generation) about each album
                $category_ids_imploded = implode(',', $category_ids);
                $query = <<<SQL
                    SELECT id, name, permalink
                    FROM categories
                    WHERE id IN ({$category_ids_imploded});
                    SQL;
                $details_for_category = query2array($query, 'id');
            }

            foreach ($images as $idx => $image) {
                $image_cats = [];

                // it should not be possible at this point, but let's consider a photo can be in no album
                if (! isset($categories_of_image[$image['id']])) {
                    continue;
                }

                foreach ($categories_of_image[$image['id']] as $cat_id) {
                    $url = make_index_url([
                        'category' => $details_for_category[$cat_id],
                    ]);

                    $page_url = make_picture_url(
                        [
                            'category' => $details_for_category[$cat_id],
                            'image_id' => $image['id'],
                            'image_file' => $image['file'],
                        ]
                    );

                    $image_cats[] = [
                        'id' => (int) $cat_id,
                        'url' => $url,
                        'page_url' => $page_url,
                    ];
                }

                $images[$idx]['categories'] = new PwgNamedArray(
                    $image_cats,
                    'category',
                    ['id', 'url', 'page_url']
                );
            }
        }
    }

    return [
        'paging' => new PwgNamedStruct(
            [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images),
                'total_count' => $total_images,
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
 * Returns a list of categories
 * @param mixed[] $params
 *    @option int cat_id (optional)
 *    @option bool recursive
 *    @option bool public
 *    @option bool tree_output
 *    @option bool fullname
 */
function ws_categories_getList(
    array $params,
    PwgServer &$service
): array|PwgError {
    global $user, $conf;

    if (! in_array($params['thumbnail_size'], array_keys(ImageStdParams::get_defined_type_map()))) {
        return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid thumbnail_size');
    }

    $where = ['1 = 1'];
    $join_type = 'INNER';
    $join_user = $user['id'];

    if (! $params['recursive']) {
        if ($params['cat_id'] > 0) {
            $where[] = "(id_uppercat = {$params['cat_id']} OR id = {$params['cat_id']})";
        } else {
            $where[] = 'id_uppercat IS NULL';
        }
    } elseif ($params['cat_id'] > 0) {
        $where[] = 'uppercats ' . DB_REGEX_OPERATOR . " '(^|,){$params['cat_id']}(,|$)'";
    }

    if ($params['public']) {
        $where[] = 'status = "public"';
        $where[] = 'visible = "true"';

        $join_user = $conf['guest_id'];
    } elseif (is_admin()) {
        // in this very specific case, we don't want to hide empty
        // categories. Function calculate_permissions will only return
        // categories that are either locked or private and not permitted
        //
        // calculate_permissions does not consider empty categories as forbidden
        $forbidden_categories = calculate_permissions($user['id'], $user['status']);
        $where[] = "id NOT IN ({$forbidden_categories})";
        $join_type = 'LEFT';
    }

    $where_clause = implode("\n    AND ", $where);
    $query = <<<SQL
        SELECT id, name, comment, permalink, status, uppercats, global_rank, id_uppercat, nb_images,
            count_images AS total_nb_images, representative_picture_id, user_representative_picture_id,
            count_images, count_categories, date_last, max_date_last, count_categories AS nb_categories,
            image_order
        FROM categories
        {$join_type} JOIN user_cache_categories ON id = cat_id AND user_id = {$join_user}
        WHERE {$where_clause}

        SQL;

    if (isset($params['search']) && $params['search'] != '') {
        $search_escaped = pwg_db_real_escape_string($params['search']);
        $query .= <<<SQL
            AND name LIKE '%{$search_escaped}%'
            LIMIT {$conf['linked_album_search_limit']}

            SQL;
    }

    $query .= ';';
    $result = pwg_query($query);

    // management of the album thumbnail -- starts here
    $image_ids = [];
    $categories = [];
    $user_representative_updates_for = [];
    // management of the album thumbnail -- stops here

    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $row['url'] = make_index_url(
            [
                'category' => $row,
            ]
        );
        foreach (['id', 'nb_images', 'total_nb_images', 'nb_categories'] as $key) {
            $row[$key] = (int) $row[$key];
        }

        if ($params['fullname']) {
            $row['name'] = strip_tags(get_cat_display_name_cache($row['uppercats'], null));
        } else {
            $row['name'] = strip_tags(
                (string) trigger_change(
                    'render_category_name',
                    $row['name'],
                    'ws_categories_getList'
                )
            );
        }

        $row['comment'] = strip_tags(
            (string) trigger_change(
                'render_category_description',
                $row['comment'],
                'ws_categories_getList'
            )
        );

        // Management of the album thumbnail -- starts here
        //
        // on branch 2.3, the algorithm is duplicated from
        // include/category_cats, but we should use a common code for Piwigo 2.4
        //
        // warning: if the API method is called with $params['public'], the
        // album thumbnail may be not accurate. The thumbnail can be viewed by
        // the connected user, but maybe not by the guest. Changing the
        // filtering method would be too complicated for now. We will simply
        // avoid persisting the user_representative_picture_id in the database
        // if $params['public']
        if (! empty($row['user_representative_picture_id'])) {
            $image_id = $row['user_representative_picture_id'];
        } elseif (! empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
            $image_id = $row['representative_picture_id'];
        } elseif ($conf['allow_random_representative']) {
            // searching a random representant among elements in sub-categories
            $image_id = get_random_image_in_category($row);
        } elseif ($row['count_categories'] > 0 && $row['count_images'] > 0) {
            // searching a random representant among representant of sub-categories
            $sql_condition = get_sql_condition_FandF(
                [
                    'visible_categories' => 'id',
                ],
                'AND'
            );
            $random_function = DB_RANDOM_FUNCTION;
            $query = <<<SQL
                    SELECT representative_picture_id
                    FROM categories
                    INNER JOIN user_cache_categories ON id = cat_id AND user_id = {$user['id']}
                    WHERE uppercats LIKE '{$row['uppercats']},%'
                        AND representative_picture_id IS NOT NULL
                        {$sql_condition}
                    ORDER BY {$random_function}
                    LIMIT 1;
                    SQL;
            $subresult = pwg_query($query);
            if (pwg_db_num_rows($subresult) > 0) {
                [$image_id] = pwg_db_fetch_row($subresult);
            }
        }

        if (isset($image_id)) {
            if ($conf['representative_cache_on_subcats'] && $row['user_representative_picture_id'] != $image_id) {
                $user_representative_updates_for[$row['id']] = $image_id;
            }

            $row['representative_picture_id'] = $image_id;
            $image_ids[] = $image_id;
            $categories[] = $row;
        }

        unset($image_id);
        // management of the album thumbnail -- stops here

        if (empty($row['image_order'])) {
            $row['image_order'] = str_replace('ORDER BY ', '', $conf['order_by']);
        }

        $cats[] = $row;
    }

    usort($cats, global_rank_compare(...));

    // management of the album thumbnail -- starts here
    if ($categories !== []) {
        $thumbnail_src_of = [];
        $new_image_ids = [];

        $image_ids_str = implode(',', $image_ids);
        $query = <<<SQL
            SELECT id, path, representative_ext, level
            FROM images
            WHERE id IN ({$image_ids_str});
            SQL;
        $result = pwg_query($query);

        while ($row = pwg_db_fetch_assoc($result)) {
            if ($row['level'] <= $user['level']) {
                $thumbnail_src_of[$row['id']] = DerivativeImage::url($params['thumbnail_size'], $row);
            } else {
                // problem: we must not display the thumbnail of a photo which has a
                // higher privacy level than user privacy level
                //
                // * what is the represented category?
                // * find a random photo matching user permissions
                // * register it at user_representative_picture_id
                // * set it as the representative_picture_id for the category
                foreach ($categories as &$category) {
                    if ($row['id'] == $category['representative_picture_id']) {
                        // searching a random representant among elements in sub-categories
                        $image_id = get_random_image_in_category($category);

                        if (isset($image_id) && ! in_array($image_id, $image_ids)) {
                            $new_image_ids[] = $image_id;
                        }

                        if ($conf['representative_cache_on_level']) {
                            $user_representative_updates_for[$category['id']] = $image_id;
                        }

                        $category['representative_picture_id'] = $image_id;
                    }
                }

                unset($category);
            }
        }

        if ($new_image_ids !== []) {
            $image_ids_str = implode(',', $new_image_ids);
            $query = <<<SQL
                SELECT id, path, representative_ext
                FROM images
                WHERE id IN ({$image_ids_str});
                SQL;
            $result = pwg_query($query);

            while ($row = pwg_db_fetch_assoc($result)) {
                $thumbnail_src_of[$row['id']] = DerivativeImage::url($params['thumbnail_size'], $row);
            }
        }
    }

    // compared to code in include/category_cats, we only persist the new
    // user_representative if we have used $user['id'] and not the guest id,
    // or else the real guest may see thumbnail that he should not
    if (! $params['public'] && count($user_representative_updates_for)) {
        $updates = [];

        foreach ($user_representative_updates_for as $cat_id => $image_id) {
            $updates[] = [
                'user_id' => $user['id'],
                'cat_id' => $cat_id,
                'user_representative_picture_id' => $image_id,
            ];
        }

        mass_updates(
            'user_cache_categories',
            [
                'primary' => ['user_id', 'cat_id'],
                'update' => ['user_representative_picture_id'],
            ],
            $updates
        );
    }

    foreach ($cats as &$cat) {
        foreach ($categories as $category) {
            if ($category['id'] == $cat['id'] && isset($category['representative_picture_id'])) {
                $cat['tn_url'] = $thumbnail_src_of[$category['representative_picture_id']];
            }
        }

        // we don't want them in the output
        unset($cat['user_representative_picture_id'], $cat['count_images'], $cat['count_categories']);
    }

    unset($cat);
    // management of the album thumbnail -- stops here

    if ($params['tree_output']) {
        return categories_flatlist_to_tree($cats);
    }

    return [
        'categories' => new PwgNamedArray(
            $cats,
            'category',
            ws_std_get_category_xml_attributes()
        ),
    ];
}

/**
 * API method
 * Returns the list of categories as you can see them in administration
 * @param mixed[] $params
 *
 * Only admin can run this method and permissions are not taken into
 * account.
 */
function ws_categories_getAdminList(
    array $params,
    PwgServer &$service
): array {
    global $conf;

    if (! isset($params['additional_output'])) {
        $params['additional_output'] = '';
    }

    $params['additional_output'] = array_map(trim(...), explode(',', (string) $params['additional_output']));

    $query = <<<SQL
        SELECT category_id, COUNT(*) AS counter
        FROM image_category
        GROUP BY category_id;
        SQL;
    $nb_images_of = query2array($query, 'category_id', 'counter');

    // pwg_db_real_escape_string

    $query = <<<SQL
        SELECT SQL_CALC_FOUND_ROWS id, name, comment, uppercats, global_rank, dir, status, image_order
        FROM categories

        SQL;

    if (isset($params['search']) && $params['search'] != '') {
        $search_term = pwg_db_real_escape_string($params['search']);
        $query .= <<<SQL
            WHERE name LIKE '%{$search_term}%'
            LIMIT {$conf['linked_album_search_limit']}

            SQL;
    }

    $query .= ';';
    $result = pwg_query($query);

    [$counter] = pwg_db_fetch_row(pwg_query('SELECT FOUND_ROWS();'));

    $cats = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $id = $row['id'];
        $row['nb_images'] = $nb_images_of[$id] ?? 0;

        $cat_display_name = get_cat_display_name_cache(
            $row['uppercats'],
            'admin.php?page=album-'
        );

        $row['name'] = strip_tags(
            (string) trigger_change(
                'render_category_name',
                $row['name'],
                'ws_categories_getAdminList'
            )
        );
        $row['fullname'] = strip_tags($cat_display_name);
        isset($row['comment']) ? false : $row['comment'] = '';
        $row['comment'] = strip_tags(
            (string) trigger_change(
                'render_category_description',
                $row['comment'],
                'ws_categories_getAdminList'
            )
        );

        if (empty($row['image_order'])) {
            $row['image_order'] = str_replace('ORDER BY ', '', $conf['order_by']);
        }

        if (in_array('full_name_with_admin_links', $params['additional_output'])) {
            $row['full_name_with_admin_links'] = $cat_display_name;
        }

        $cats[] = $row;
    }

    $limit_reached = false;
    if ($counter > $conf['linked_album_search_limit']) {
        $limit_reached = true;
    }

    usort($cats, global_rank_compare(...));
    return [
        'categories' => new PwgNamedArray(
            $cats,
            'category',
            ['id', 'nb_images', 'name', 'uppercats', 'global_rank', 'status', 'test']
        ),
        'limit' => $conf['linked_album_search_limit'],
        'limit_reached' => $limit_reached,
    ];
}

/**
 * API method
 * Adds a category
 * @param mixed[] $params
 *    @option string name
 *    @option int parent (optional)
 *    @option string comment (optional)
 *    @option bool visible
 *    @option string status (optional)
 *    @option bool commentable
 */
function ws_categories_add(
    array $params,
    PwgServer &$service
): array|PwgError {
    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    global $conf;

    if (isset($params['pwg_token']) && get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    if (! empty($params['position']) && in_array($params['position'], ['first', 'last'])) {
        //TODO make persistent with user prefs
        $conf['newcat_default_position'] = $params['position'];
    }

    $options = [];
    if (! empty($params['status']) && in_array($params['status'], ['private', 'public'])) {
        $options['status'] = $params['status'];
    }

    if (! empty($params['comment'])) {
        $options['comment'] = (! $conf['allow_html_descriptions'] || ! isset($params['pwg_token'])) ? strip_tags((string) $params['comment']) : $params['comment'];
    }

    $creation_output = create_virtual_category(
        (! $conf['allow_html_descriptions'] || ! isset($params['pwg_token'])) ? strip_tags((string) $params['name']) : $params['name'],
        $params['parent'],
        $options
    );

    if (isset($creation_output['error'])) {
        return new PwgError(500, $creation_output['error']);
    }

    invalidate_user_cache();

    return $creation_output;
}

/**
 * API method
 * Set the rank of a category
 * @param mixed[] $params
 *    @option int cat_id
 *    @option int rank
 */
function ws_categories_setRank(
    array $params,
    PwgServer &$service
): ?PwgError {
    // does the category really exist?
    $category_ids_str = implode(',', $params['category_id']);
    $query = <<<SQL
        SELECT id, id_uppercat, rank_column
        FROM categories
        WHERE id IN ({$category_ids_str});
        SQL;
    $categories = query2array($query);

    if (count($categories) == 0) {
        return new PwgError(404, 'category_id not found');
    }

    $category = $categories[0];

    //check the number of categories given by the user
    if (count($params['category_id']) > 1) {
        $order_new = $params['category_id'];
        $order_new_by_id = $order_new;
        sort($order_new_by_id, SORT_NUMERIC);

        $id_uppercat_condition = empty($category['id_uppercat']) ? 'IS NULL' : "= {$category['id_uppercat']}";
        $query = <<<SQL
            SELECT id
            FROM categories
            WHERE id_uppercat {$id_uppercat_condition}
            ORDER BY id ASC;
            SQL;

        $cat_asc = query2array($query, null, 'id');

        if (strcmp(implode(',', $cat_asc), implode(',', $order_new_by_id)) !== 0) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'you need to provide all sub-category ids for a given category');
        }
    } else {
        $params['category_id'] = implode('', $params['category_id']);

        $id_uppercat_condition = empty($category['id_uppercat']) ? 'IS NULL' : "= {$category['id_uppercat']}";
        $query = <<<SQL
            SELECT id
            FROM categories
            WHERE id_uppercat {$id_uppercat_condition}
                AND id != {$params['category_id']}
            ORDER BY rank_column ASC;
            SQL;

        $order_old = query2array($query, null, 'id');
        $order_new = [];
        $was_inserted = false;
        $i = 1;
        foreach ($order_old as $category_id) {
            if ($i == $params['rank_column']) {
                $order_new[] = $params['category_id'];
                $was_inserted = true;
            }

            $order_new[] = $category_id;
            ++$i;
        }

        if (! $was_inserted) {
            $order_new[] = $params['category_id'];
        }
    }

    // include function to set the global rank
    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
    save_categories_order($order_new);

    return null;
}

/**
 * API method
 * Sets details of a category
 * @param mixed[] $params
 *    @option int cat_id
 *    @option string name (optional)
 *    @option string status (optional)
 *    @option bool visible (optional)
 *    @option string comment (optional)
 *    @option bool commentable (optional)
 *    @option bool apply_commentable_to_subalbums (optional)
 */
function ws_categories_setInfo(
    array $params,
    PwgServer &$service
): ?PwgError {
    global $conf;

    if (isset($params['pwg_token']) && get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    // does the category really exist?
    $query = <<<SQL
        SELECT *
        FROM categories
        WHERE id = {$params['category_id']};
        SQL;
    $categories = query2array($query);
    if (count($categories) == 0) {
        return new PwgError(404, 'category_id not found');
    }

    $category = $categories[0];

    if (! empty($params['status'])) {
        if (! in_array($params['status'], ['private', 'public'])) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid status, only public/private');
        }

        if ($params['status'] != $category['status']) {
            require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
            set_cat_status([$params['category_id']], $params['status']);
        }
    }

    $update = [
        'id' => $params['category_id'],
    ];

    foreach (['visible', 'commentable'] as $param_name) {
        if (isset($params[$param_name]) && ! preg_match('/^(true|false)$/i', (string) $params[$param_name])) {
            return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid param ' . $param_name . ' : ' . $params[$param_name]);
        }
    }

    if (! empty($params['visible']) && $params['visible'] != $category['visible']) {
        require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
        set_cat_visible([$params['category_id']], $params['visible']);
    }

    $info_columns = ['name', 'comment', 'commentable'];

    $perform_update = false;
    foreach ($info_columns as $key) {
        if (isset($params[$key])) {
            $perform_update = true;
            $update[$key] = (! $conf['allow_html_descriptions'] || ! isset($params['pwg_token'])) ? strip_tags((string) $params[$key]) : $params[$key];
        }
    }

    if (isset($params['commentable']) && isset($params['apply_commentable_to_subalbums']) && $params['apply_commentable_to_subalbums']) {
        $subcats = get_subcat_ids([$params['category_id']]);
        if ($subcats !== []) {
            $subcats_str = implode(',', $subcats);
            $query = <<<SQL
                UPDATE categories
                SET commentable = '{$params['commentable']}'
                WHERE id IN ({$subcats_str});
                SQL;
            pwg_query($query);
        }
    }

    if ($perform_update) {
        single_update(
            'categories',
            $update,
            [
                'id' => $update['id'],
            ]
        );
    }

    pwg_activity('album', $params['category_id'], 'edit', [
        'fields' => implode(',', array_keys($update)),
    ]);

    return null;
}

/**
 * API method
 * Sets representative image of a category
 * @param mixed[] $params
 *    @option int category_id
 *    @option int image_id
 */
function ws_categories_setRepresentative(
    array $params,
    PwgServer &$service
): ?PwgError {
    // does the category really exist?
    $query = <<<SQL
        SELECT COUNT(*)
        FROM categories
        WHERE id = {$params['category_id']};
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new PwgError(404, 'category_id not found');
    }

    // does the image really exist?
    $query = <<<SQL
        SELECT COUNT(*)
        FROM images
        WHERE id = {$params['image_id']};
        SQL;
    [$count] = pwg_db_fetch_row(pwg_query($query));
    if ($count == 0) {
        return new PwgError(404, 'image_id not found');
    }

    // apply change
    $query = <<<SQL
        UPDATE categories
        SET representative_picture_id = {$params['image_id']}
        WHERE id = {$params['category_id']};
        SQL;
    pwg_query($query);

    $query = <<<SQL
        UPDATE user_cache_categories
        SET user_representative_picture_id = NULL
        WHERE cat_id = {$params['category_id']};
        SQL;
    pwg_query($query);

    pwg_activity('album', $params['category_id'], 'edit', [
        'image_id' => $params['image_id'],
    ]);

    return null;
}

/**
 * API method
 *
 * Deletes the album thumbnail. Only possible if
 * $conf['allow_random_representative'] or if the album has no direct photos.
 *
 * @param mixed[] $params
 *    @option int category_id
 */
function ws_categories_deleteRepresentative(
    array $params,
    PwgServer &$service
): ?PwgError {
    global $conf;

    // does the category really exist?
    $query = <<<SQL
        SELECT id
        FROM categories
        WHERE id = {$params['category_id']};
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) == 0) {
        return new PwgError(404, 'category_id not found');
    }

    $query = <<<SQL
        SELECT COUNT(*)
        FROM image_category
        WHERE category_id = {$params['category_id']};
        SQL;
    [$nb_images] = pwg_db_fetch_row(pwg_query($query));

    if (! $conf['allow_random_representative'] && $nb_images != 0) {
        return new PwgError(401, 'not permitted');
    }

    $query = <<<SQL
        UPDATE categories
        SET representative_picture_id = NULL
        WHERE id = {$params['category_id']};
        SQL;
    pwg_query($query);

    pwg_activity('album', $params['category_id'], 'edit');

    return null;
}

/**
 * API method
 *
 * Find a new album thumbnail.
 *
 * @param mixed[] $params
 *    @option int category_id
 */
function ws_categories_refreshRepresentative(
    array $params,
    PwgServer &$service
): array|PwgError {
    global $conf;

    // does the category really exist?
    $query = <<<SQL
        SELECT id
        FROM categories
        WHERE id = {$params['category_id']};
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) == 0) {
        return new PwgError(404, 'category_id not found');
    }

    $query = <<<SQL
        SELECT DISTINCT category_id
        FROM image_category
        WHERE category_id = {$params['category_id']}
        LIMIT 1;
        SQL;
    $result = pwg_query($query);
    $has_images = pwg_db_num_rows($result) > 0;

    if (! $has_images) {
        return new PwgError(401, 'not permitted');
    }

    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

    set_random_representant([$params['category_id']]);

    pwg_activity('album', $params['category_id'], 'edit');

    // return url of the new representative
    $query = <<<SQL
        SELECT *
        FROM categories
        WHERE id = {$params['category_id']};
        SQL;
    $category = pwg_db_fetch_assoc(pwg_query($query));

    return get_category_representant_properties($category['representative_picture_id'], IMG_SMALL);
}

/**
 * API method
 * Deletes a category
 * @param mixed[] $params
 *    @option string|int[] category_id
 *    @option string photo_deletion_mode
 *    @option string pwg_token
 */
function ws_categories_delete(
    array $params,
    PwgServer &$service
): ?PwgError {
    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    $modes = ['no_delete', 'delete_orphans', 'force_delete'];
    if (! in_array($params['photo_deletion_mode'], $modes)) {
        return new PwgError(
            500,
            '[ws_categories_delete]'
      . ' invalid parameter photo_deletion_mode "' . $params['photo_deletion_mode'] . '"'
      . ', possible values are {' . implode(', ', $modes) . '}.'
        );
    }

    if (! is_array($params['category_id'])) {
        $params['category_id'] = preg_split(
            '/[\s,;\|]/',
            (string) $params['category_id'],
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    $params['category_id'] = array_map(intval(...), $params['category_id']);

    $category_ids = [];
    foreach ($params['category_id'] as $category_id) {
        if ($category_id > 0) {
            $category_ids[] = $category_id;
        }
    }

    if (count($category_ids) == 0) {
        return null;
    }

    $category_ids_imploded = implode(',', $category_ids);
    $query = <<<SQL
        SELECT id
        FROM categories
        WHERE id IN ({$category_ids_imploded});
        SQL;
    $category_ids = query2array($query, null, 'id');

    if (count($category_ids) == 0) {
        return null;
    }

    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
    delete_categories($category_ids, $params['photo_deletion_mode']);
    update_global_rank();
    invalidate_user_cache();

    return null;
}

/**
 * API method
 * Moves a category
 * @param mixed[] $params
 *    @option string|int[] category_id
 *    @option int parent
 *    @option string pwg_token
 */
function ws_categories_move(
    array $params,
    PwgServer &$service
): array|PwgError {
    global $page;

    if (get_pwg_token() != $params['pwg_token']) {
        return new PwgError(403, 'Invalid security token');
    }

    if (! is_array($params['category_id'])) {
        $params['category_id'] = preg_split(
            '/[\s,;\|]/',
            (string) $params['category_id'],
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    $params['category_id'] = array_map(intval(...), $params['category_id']);

    $category_ids = [];
    foreach ($params['category_id'] as $category_id) {
        if ($category_id > 0) {
            $category_ids[] = $category_id;
        }
    }

    if (count($category_ids) == 0) {
        return new PwgError(403, 'Invalid category_id input parameter, no category to move');
    }

    // we can't move physical categories
    $categories_in_db = [];
    $update_cat_ids = [];

    $category_ids_imploded = implode(',', $category_ids);
    $query = <<<SQL
        SELECT id, name, dir, uppercats
        FROM categories
        WHERE id IN ({$category_ids_imploded});
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $categories_in_db[$row['id']] = $row;
        $update_cat_ids = array_merge($update_cat_ids, array_slice(explode(',', (string) $row['uppercats']), 0, -1));

        // we break on error at first physical category detected
        if (! empty($row['dir'])) {
            $row['name'] = strip_tags(
                (string) trigger_change(
                    'render_category_name',
                    $row['name'],
                    'ws_categories_move'
                )
            );

            return new PwgError(
                403,
                sprintf(
                    'Category %s (%u) is not a virtual category, you cannot move it',
                    $row['name'],
                    $row['id']
                )
            );
        }
    }

    if (count($categories_in_db) != count($category_ids)) {
        $unknown_category_ids = array_diff($category_ids, array_keys($categories_in_db));

        return new PwgError(
            403,
            sprintf(
                'Category %u does not exist',
                $unknown_category_ids[0]
            )
        );
    }

    // does this parent exist? This check should be made in the
    // move_categories function, not here
    // 0 as parent means "move categories at gallery root"
    if ($params['parent'] != 0) {
        $subcat_ids = get_subcat_ids([$params['parent']]);
        if (count($subcat_ids) == 0) {
            return new PwgError(403, 'Unknown parent category id');
        }
    }

    $page['infos'] = [];
    $page['errors'] = [];

    require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
    move_categories($category_ids, $params['parent']);
    invalidate_user_cache();

    if ($page['errors'] !== []) {
        return new PwgError(403, implode('; ', $page['errors']));
    }

    $category_ids_imploded = implode(',', $category_ids);
    $query = <<<SQL
        SELECT uppercats
        FROM categories
        WHERE id IN ({$category_ids_imploded});
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $cat_display_name = get_cat_display_name_cache(
            $row['uppercats'],
            'admin.php?page=album-'
        );
        $update_cat_ids = array_merge($update_cat_ids, array_slice(explode(',', (string) $row['uppercats']), 0, -1));
    }

    $query = <<<SQL
        SELECT category_id, COUNT(*) AS nb_photos
        FROM image_category
        GROUP BY category_id;
        SQL;

    $nb_photos_in = query2array($query, 'category_id', 'nb_photos');

    $update_cats = [];
    foreach (array_unique($update_cat_ids) as $update_cat) {
        $nb_sub_photos = 0;
        $sub_cat_without_parent = array_diff(get_subcat_ids([$update_cat]), [$update_cat]);

        foreach ($sub_cat_without_parent as $id_sub_cat) {
            $nb_sub_photos += $nb_photos_in[$id_sub_cat] ?? 0;
        }

        $update_cats[] = [
            'cat_id' => $update_cat,
            'nb_sub_photos' => $nb_sub_photos,
        ];
    }

    return [
        'new_ariane_string' => $cat_display_name,
        'updated_cats' => $update_cats,
    ];
}

/**
 * API method
 * Return the number of orphan photos if an album is deleted
 * @since 12
 */
function ws_categories_calculateOrphans(
    array $param,
    PwgServer &$service
) {
    global $conf;

    $category_id = $param['category_id'][0];

    $query = <<<SQL
        SELECT DISTINCT category_id
        FROM image_category
        WHERE category_id = {$category_id}
        LIMIT 1;
        SQL;
    $result = pwg_query($query);
    $category['has_images'] = pwg_db_num_rows($result) > 0;

    // number of sub-categories
    $subcat_ids = get_subcat_ids([$category_id]);

    $category['nb_subcats'] = count($subcat_ids) - 1;

    // total number of images under this category (including sub-categories)
    $subcat_ids_list = implode(',', $subcat_ids);
    $query = <<<SQL
        SELECT DISTINCT (image_id)
        FROM image_category
        WHERE category_id IN ({$subcat_ids_list});
        SQL;
    $image_ids_recursive = query2array($query, null, 'image_id');

    $category['nb_images_recursive'] = count($image_ids_recursive);

    // number of images that would become orphan on album deletion
    $category['nb_images_becoming_orphan'] = 0;
    $category['nb_images_associated_outside'] = 0;

    if ($category['nb_images_recursive'] > 0) {
        // if we don't have "too many" photos, it's faster to compute the orphans with MySQL
        if ($category['nb_images_recursive'] < 1000) {
            $subcat_ids_list = implode(',', $subcat_ids);
            $image_ids_recursive_list = implode(',', $image_ids_recursive);
            $query = <<<SQL
                SELECT DISTINCT (image_id)
                FROM image_category
                WHERE category_id NOT IN ({$subcat_ids_list})
                    AND image_id IN ({$image_ids_recursive_list});
                SQL;

            $image_ids_associated_outside = query2array($query, null, 'image_id');
            $category['nb_images_associated_outside'] = count($image_ids_associated_outside);

            $image_ids_becoming_orphan = array_diff($image_ids_recursive, $image_ids_associated_outside);
            $category['nb_images_becoming_orphan'] = count($image_ids_becoming_orphan);
        }
        // else it's better to avoid sending a huge SQL request, we compute the orphan list with PHP
        else {
            $image_ids_recursive_keys = array_flip($image_ids_recursive);

            $subcat_ids_list = implode(',', $subcat_ids);
            $query = <<<SQL
                SELECT image_id
                FROM image_category
                WHERE category_id NOT IN ({$subcat_ids_list});
                SQL;
            $image_ids_associated_outside = query2array($query, null, 'image_id');
            $image_ids_not_orphan = [];

            foreach ($image_ids_associated_outside as $image_id) {
                if (isset($image_ids_recursive_keys[$image_id])) {
                    $image_ids_not_orphan[] = $image_id;
                }
            }

            $category['nb_images_associated_outside'] = count(array_unique($image_ids_not_orphan));
            $image_ids_becoming_orphan = array_diff($image_ids_recursive, $image_ids_not_orphan);
            $category['nb_images_becoming_orphan'] = count($image_ids_becoming_orphan);
        }
    }

    $output[] = [
        'nb_images_associated_outside' => $category['nb_images_associated_outside'],
        'nb_images_becoming_orphan' => $category['nb_images_becoming_orphan'],
        'nb_images_recursive' => $category['nb_images_recursive'],
    ];

    return $output;
}
