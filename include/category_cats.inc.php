<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * This file is included by the main page to show subcategories of a category
 * or to show recent categories or main page categories list
 */

// $user['forbidden_categories'] including with user_cache_categories
$query = <<<SQL
    WITH total_rows AS
        (
            SELECT COUNT(*) AS total_count
            FROM categories c
            INNER JOIN user_cache_categories ucc ON id = cat_id AND user_id = {$user['id']}
            WHERE count_images > 0 AND id_uppercat IS NULL
         )
    SELECT c.*, user_representative_picture_id, nb_images, date_last, max_date_last,
        count_images, nb_categories, count_categories, tr.total_count
    FROM categories c
    INNER JOIN user_cache_categories ucc ON id = cat_id AND user_id = {$user['id']}
    CROSS JOIN total_rows tr
    WHERE count_images > 0

    SQL;

if ($page['section'] == 'recent_cats') {
    $recent_photos = get_recent_photos_sql('date_last');
    $query .= <<<SQL
        AND {$recent_photos}

        SQL;
} else {
    $category_condition = isset($page['category']) ? "= {$page['category']['id']}" : 'IS NULL';
    $query .= <<<SQL
        AND id_uppercat {$category_condition}

        SQL;
}

$query .= get_sql_condition_FandF(
    [
        'visible_categories' => 'id',
    ],
    'AND'
);

// special string to let plugins modify this query at this exact position
$query .= "-- after conditions\n";

if ($page['section'] != 'recent_cats') {
    $query .= <<<SQL
        ORDER BY rank_column

        SQL;
}

$offset = $page['startcat'] ?? 0;
$query .= <<<SQL
    LIMIT {$conf['nb_categories_page']} OFFSET {$offset};
    SQL;

$query = trigger_change('loc_begin_index_category_thumbnails_query', $query);

$result = pwg_query($query);

$categories = [];
$category_ids = [];
$image_ids = [];
$user_representative_updates_for = [];

while ($row = pwg_db_fetch_assoc($result)) {
    [$page['total_categories']] = $row['total_count'];
    $row['is_child_date_last'] = $row['max_date_last'] > $row['date_last'];

    if (! empty($row['user_representative_picture_id'])) {
        $image_id = $row['user_representative_picture_id'];
    } elseif (! empty($row['representative_picture_id'])) { // if a representative picture is set, it has priority
        $image_id = $row['representative_picture_id'];
    } elseif ($conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
        $image_id = get_random_image_in_category($row);
    } elseif ($row['count_categories'] > 0 && $row['count_images'] > 0) { // at this point, $row['count_images'] should always be >0 (used as condition in SQL)
        // searching a random representant among representant of subcategories
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
        $category_ids[] = $row['id'];
    } else {
        $logger->info(
            sprintf(
                '[%s] category #%u was listed in SQL but no image_id found, so it was skipped',
                basename(__FILE__),
                $row['id']
            )
        );
    }

    unset($image_id);
}

if ($conf['display_fromto'] && $category_ids !== []) {
    $category_ids_list = implode(',', $category_ids);
    $sql_condition = get_sql_condition_FandF(
        [
            'visible_categories' => 'category_id',
            'visible_images' => 'id',
        ],
        'AND'
    );
    $query = <<<SQL
            SELECT category_id, MIN(date_creation) AS from, MAX(date_creation) AS to
            FROM image_category
            INNER JOIN images ON image_id = id
            WHERE category_id IN ({$category_ids_list})
                {$sql_condition}
            GROUP BY category_id;
            SQL;
    $dates_of_category = query2array($query, 'category_id');
}

if ($page['section'] == 'recent_cats') {
    usort($categories, global_rank_compare(...));
}

if ($categories !== []) {
    $infos_of_image = [];
    $new_image_ids = [];

    $image_ids_list = implode(',', $image_ids);
    $query = <<<SQL
        SELECT *
        FROM images
        WHERE id IN ({$image_ids_list});
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['level'] <= $user['level']) {
            $infos_of_image[$row['id']] = $row;
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
        $image_ids_list = implode(',', $new_image_ids);
        $query = <<<SQL
            SELECT *
            FROM images
            WHERE id IN ({$image_ids_list});
            SQL;
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            $infos_of_image[$row['id']] = $row;
        }
    }

    foreach ($infos_of_image as &$info) {
        $info['src_image'] = new SrcImage($info);
    }

    unset($info);
}

if ($user_representative_updates_for !== []) {
    $updates = [];

    foreach ($user_representative_updates_for as $cat_id => $image_id) {
        $updates[] =
          [
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

if ($categories !== []) {
    // Update filtered data
    if (function_exists('update_cats_with_filtered_data')) {
        update_cats_with_filtered_data($categories);
    }

    $template->set_filename('index_category_thumbnails', 'mainpage_categories.tpl');

    trigger_notify('loc_begin_index_category_thumbnails', $categories);

    $tpl_thumbnails_var = [];

    foreach ($categories as $category) {
        if ($category['count_images'] == 0) {
            continue;
        }

        $category['name'] = trigger_change(
            'render_category_name',
            $category['name'],
            'subcatify_category_name'
        );

        if ($page['section'] == 'recent_cats') {
            $name = get_cat_display_name_cache($category['uppercats'], null);
        } else {
            $name = $category['name'];
        }

        $representative_infos = $infos_of_image[$category['representative_picture_id']];

        $tpl_var = array_merge($category, [
            'ID' => $category['id'] /*obsolete*/,
            'representative' => $representative_infos,
            'TN_ALT' => strip_tags((string) $category['name']),

            'URL' => make_index_url(
                [
                    'category' => $category,
                ]
            ),
            'CAPTION_NB_IMAGES' => get_display_images_count(
                (int) $category['nb_images'],
                (int) $category['count_images'],
                (int) $category['count_categories'],
                true,
                '<br>'
            ),
            'DESCRIPTION' =>
              trigger_change(
                  'render_category_literal_description',
                  trigger_change(
                      'render_category_description',
                      $category['comment'],
                      'subcatify_category_description'
                  )
              ),
            'NAME' => $name,
        ]);
        if ($conf['index_new_icon']) {
            $tpl_var['icon_ts'] = get_icon($category['max_date_last'], $category['is_child_date_last']);
        }

        if ($conf['display_fromto'] && isset($dates_of_category[$category['id']])) {
            $from = $dates_of_category[$category['id']]['from'];
            $to = $dates_of_category[$category['id']]['to'];
            if (! empty($from)) {
                $tpl_var['INFO_DATES'] = format_fromto($from, $to);
            }
        }

        $tpl_thumbnails_var[] = $tpl_var;
    }

    // pagination
    $tpl_thumbnails_var_selection = $tpl_thumbnails_var;

    $derivative_params = trigger_change('get_index_album_derivative_params', ImageStdParams::get_by_type(IMG_THUMB));
    $tpl_thumbnails_var_selection = trigger_change('loc_end_index_category_thumbnails', $tpl_thumbnails_var_selection);
    $template->assign([
        'category_thumbnails' => $tpl_thumbnails_var_selection,
        'derivative_params' => $derivative_params,
    ]);

    $template->assign_var_from_handle('CATEGORIES', 'index_category_thumbnails');

    // navigation bar
    $page['cats_navigation_bar'] = [];
    if ($page['total_categories'] > $conf['nb_categories_page']) {
        $page['cats_navigation_bar'] = create_navigation_bar(
            duplicate_index_url([], ['startcat']),
            (int) $page['total_categories'],
            (int) $page['startcat'],
            $conf['nb_categories_page'],
            true,
            'startcat'
        );
    }

    $template->assign('cats_navbar', $page['cats_navigation_bar']);
}

pwg_debug('end include/category_cats.inc.php');
