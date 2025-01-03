<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// todo: replace mixed types
// todo: replace $conf with a class with typed getters/setters

//--------------------------------------------------------------------- include
define('PHPWG_ROOT_PATH', './');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';
require PHPWG_ROOT_PATH . 'include/section_init.inc.php';

// Check Access and exit when user status is not ok
check_status(ACCESS_GUEST);

// access authorization check
if (isset($page['category'])) {
    check_restrictions($page['category']['id']);
}

if ($page['start'] > 0 && $page['start'] >= count($page['items'])) {
    page_not_found('', duplicate_index_url([
        'start' => 0,
    ]));
}

trigger_notify('loc_begin_index');

//---------------------------------------------- change of image display order
if (isset($_GET['image_order'])) {
    if ((int) $_GET['image_order'] > 0) {
        pwg_set_session_var('image_order', (int) $_GET['image_order']);
    } else {
        pwg_unset_session_var('image_order');
    }

    redirect(
        duplicate_index_url(
            [],        // nothing to redefine
            ['start']  // changing display order goes back to section first page
        )
    );
}

if (isset($_GET['display'])) {
    $page['meta_robots']['noindex'] = 1;
    if (array_key_exists($_GET['display'], ImageStdParams::get_defined_type_map())) {
        pwg_set_session_var('index_deriv', $_GET['display']);
    }
}

//-------------------------------------------------------------- initialization
// navigation bar
$page['navigation_bar'] = [];
if (count($page['items']) > $page['nb_image_page']) {
    $page['navigation_bar'] = create_navigation_bar(
        duplicate_index_url([], ['start']),
        count($page['items']),
        (int) $page['start'],
        (int) $page['nb_image_page'],
        true,
        'start'
    );
}

$template->assign('thumb_navbar', $page['navigation_bar']);

// caddie filling :-)
if (isset($_GET['caddie'])) {
    fill_caddie($page['items']);
    redirect(duplicate_index_url());
}

if (isset($page['is_homepage']) && $page['is_homepage']) {
    $canonical_url = get_gallery_home_url();
} else {
    $start = $page['nb_image_page'] * round($page['start'] / $page['nb_image_page']);
    if ($start > 0 && $start >= count($page['items'])) {
        $start -= $page['nb_image_page'];
    }

    $canonical_url = duplicate_index_url([
        'start' => $start,
    ]);
}

$template->assign('U_CANONICAL', $canonical_url);

//-------------------------------------------------------------- page title
$title = $page['title'];
$template_title = $page['section_title'];
$nb_items = count($page['items']);
$template->assign('TITLE', $template_title);
$template->assign('NB_ITEMS', $nb_items);

//-------------------------------------------------------------- menubar
require PHPWG_ROOT_PATH . 'include/menubar.inc.php';

$template->set_filename('index', 'index.tpl');

// +-----------------------------------------------------------------------+
// |  index page (categories, thumbnails, search, calendar, random, etc.)  |
// +-----------------------------------------------------------------------+
if (empty($page['is_external'])) {
    //----------------------------------------------------- template initialization
    $page['body_id'] = 'theCategoryPage';

    if (isset($page['flat']) || isset($page['chronology_field'])) {
        $template->assign(
            'U_MODE_NORMAL',
            duplicate_index_url([], ['chronology_field', 'start', 'flat'])
        );
    }

    if ($conf['index_flat_icon'] && ! isset($page['flat']) && $page['section'] == 'categories') {
        $template->assign(
            'U_MODE_FLAT',
            duplicate_index_url([
                'flat' => '',
            ], ['start', 'chronology_field'])
        );
    }

    if (! isset($page['chronology_field'])) {
        $chronology_params = [
            'chronology_field' => 'created',
            'chronology_style' => 'monthly',
            'chronology_view' => 'list',
        ];
        if ($conf['index_created_date_icon']) {
            $template->assign(
                'U_MODE_CREATED',
                duplicate_index_url($chronology_params, ['start', 'flat'])
            );
        }

        if ($conf['index_posted_date_icon']) {
            $chronology_params['chronology_field'] = 'posted';
            $template->assign(
                'U_MODE_POSTED',
                duplicate_index_url($chronology_params, ['start', 'flat'])
            );
        }
    } else {
        $chronology_field = $page['chronology_field'] == 'created' ? 'posted' : 'created';

        if ($conf['index_' . $chronology_field . '_date_icon']) {
            $url = duplicate_index_url(
                [
                    'chronology_field' => $chronology_field,
                ],
                ['chronology_date', 'start', 'flat']
            );
            $template->assign(
                'U_MODE_' . strtoupper($chronology_field),
                $url
            );
        }
    }

    // We add isset($page['search_details']) in this condition because it only
    // applies to regular search, not the legacy quicksearch. Since Piwigo 14 can still
    // be able to show an old quicksearch result, we must check this condition too.
    if ($page['section'] == 'search' && isset($page['search_details'])) {
        include_once(PHPWG_ROOT_PATH . 'include/functions_search.inc.php');

        $my_search = get_search_array($page['search']);

        // we want filters to be filled with values related to current items ONLY IF we have some filters filled
        if ($page['search_details']['has_filters_filled']) {
            $search_items = [-1];
            if (! empty($page['items'])) {
                $search_items = $page['items'];
            }

            $search_items_clause = 'image_id IN (' . implode(',', $search_items) . ')';
        } else {
            $search_items_clause = '1 = 1';
        }

        if (isset($my_search['fields']['tags'])) {
            $filter_tags = [];
            // TODO calling get_available_tags(), with lots of photos/albums/tags may cost time,
            // we should reuse the result if already executed (for building the menu for example)
            if (isset($search_items)) {
                $filter_tags = get_common_tags($search_items, 0);

                // The user may have started a search on 2 or more tags that have no
                // intersection. In this case, $search_items is empty and get_common_tags
                // returns nothing. We should still display the list of selected tags. We
                // have to "force" them on the list.
                $missing_tag_ids = array_diff($my_search['fields']['tags']['words'], array_column($filter_tags, 'id'));

                if ($missing_tag_ids !== []) {
                    $filter_tags = array_merge(get_available_tags($missing_tag_ids), $filter_tags);
                }
            } else {
                $filter_tags = get_available_tags();
                usort($filter_tags, 'tag_alpha_compare');
            }

            $template->assign('TAGS', $filter_tags);

            $filter_tag_ids = $filter_tags !== [] ? array_column($filter_tags, 'id') : [];

            // in case the search has forbidden tags for current user, we need to filter the search rule
            $my_search['fields']['tags']['words'] = array_intersect($my_search['fields']['tags']['words'], $filter_tag_ids);
        }

        if (isset($my_search['fields']['author'])) {
            $sql_condition = get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id',
                ],
                'AND'
            );

            $query = <<<SQL
                SELECT author, COUNT(DISTINCT(id)) AS counter
                FROM images AS i
                JOIN image_category AS ic ON ic.image_id = i.id
                WHERE {$search_items_clause}
                    {$sql_condition}
                    AND author IS NOT NULL
                GROUP BY author;
                SQL;
            $authors = query2array($query);
            $author_names = [];
            foreach ($authors as $author) {
                $author_names[] = $author['author'];
            }

            $template->assign('AUTHORS', $authors);

            // in case the search has forbidden authors for current user, we need to filter the search rule
            $my_search['fields']['author']['words'] = array_intersect($my_search['fields']['author']['words'], $author_names);
        }

        if (isset($my_search['fields']['date_posted'])) {
            $query = <<<SQL
                SELECT
                    SUBDATE(NOW(), INTERVAL 24 HOUR) AS 24h,
                    SUBDATE(NOW(), INTERVAL 7 DAY) AS 7d,
                    SUBDATE(NOW(), INTERVAL 30 DAY) AS 30d,
                    SUBDATE(NOW(), INTERVAL 3 MONTH) AS 3m,
                    SUBDATE(NOW(), INTERVAL 6 MONTH) AS 6m;
                SQL;
            $thresholds = query2array($query)[0];

            $sql_condition = get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id',
                ],
                'AND'
            );

            $query = <<<SQL
                SELECT image_id, date_available
                FROM images AS i
                JOIN image_category AS ic ON ic.image_id = i.id
                WHERE {$search_items_clause}
                    {$sql_condition};
                SQL;
            $dates = query2array($query);
            $pre_counters = array_fill_keys(array_keys($thresholds), []);
            foreach ($dates as $date_row) {
                $year = date('Y', strtotime((string) $date_row['date_available']));
                $pre_counters['y' . $year][$date_row['image_id']] = 1;
                foreach ($thresholds as $threshold => $date_limit) {
                    if ($date_row['date_available'] > $date_limit) {
                        $pre_counters[$threshold][$date_row['image_id']] = 1;
                    }
                }
            }

            $label_for_threshold = [
                '24h' => l10n('last 24 hours'),
                '7d' => l10n('last 7 days'),
                '30d' => l10n('last 30 days'),
                '3m' => l10n('last 3 months'),
                '6m' => l10n('last 6 months'),
            ];

            // pre_counters need to be deduplicated because a photo can be in several albums
            $counters = array_fill_keys(array_keys($thresholds), [
                'label' => 'default label',
                'counter' => 0,
            ]);
            foreach (array_keys($thresholds) as $threshold) {
                $counters[$threshold] = [
                    'label' => $label_for_threshold[$threshold],
                    'counter' => count(array_keys($pre_counters[$threshold])),
                ];
            }

            $pre_counters_keys = array_keys($pre_counters);
            rsort($pre_counters_keys); // we want y2023 to come before y2022 in the list

            foreach ($pre_counters_keys as $key) {
                if (preg_match('/^y(\d+)$/', $key, $matches)) {
                    $counters[$key] = [
                        'label' => l10n('year %d', $matches[1]),
                        'counter' => count(array_keys($pre_counters[$key])),
                    ];
                }
            }

            foreach ($counters as $key => $counter) {
                if ($counter['counter'] == 0) {
                    unset($counters[$key]);
                }
            }

            $template->assign('DATE_POSTED', $counters);
        }

        if (isset($my_search['fields']['added_by'])) {
            $sql_condition = get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id',
                ],
                'AND'
            );

            $query = <<<SQL
                SELECT COUNT(DISTINCT(id)) AS counter, added_by AS added_by_id
                FROM images AS i
                JOIN image_category AS ic ON ic.image_id = i.id
                WHERE {$search_items_clause}
                    {$sql_condition}
                GROUP BY added_by_id
                ORDER BY counter DESC;
                SQL;
            $added_by = query2array($query);
            $user_ids = [];

            if ($added_by !== []) {
                // now let's find the usernames of added_by users
                foreach ($added_by as $i) {
                    $user_ids[] = $i['added_by_id'];
                }

                $user_ids_str = implode(',', $user_ids);
                $query = <<<SQL
                    SELECT {$conf['user_fields']['id']} AS id, {$conf['user_fields']['username']} AS username
                    FROM users
                    WHERE {$conf['user_fields']['id']} IN ({$user_ids_str});
                    SQL;
                $username_of = query2array($query, 'id', 'username');

                foreach (array_keys($added_by) as $added_by_idx) {
                    $added_by_id = $added_by[$added_by_idx]['added_by_id'];
                    $added_by[$added_by_idx]['added_by_name'] = $username_of[$added_by_id] ?? 'user #' . $added_by_id . ' (deleted)';
                }
            }

            $template->assign('ADDED_BY', $added_by);

            // in case the search has forbidden added_by users for current user, we need to filter the search rule
            $my_search['fields']['added_by'] = array_intersect($my_search['fields']['added_by'], $user_ids);
        }

        if (isset($my_search['fields']['cat']) && ! empty($my_search['fields']['cat']['words'])) {
            $fullname_of = [];

            $cat_words = implode(',', $my_search['fields']['cat']['words']);
            $query = <<<SQL
                SELECT id, uppercats
                FROM categories
                INNER JOIN user_cache_categories ON id = cat_id AND user_id = {$user['id']}
                WHERE id IN ({$cat_words});
                SQL;
            $result = pwg_query($query);

            while ($row = pwg_db_fetch_assoc($result)) {
                $cat_display_name = get_cat_display_name_cache(
                    $row['uppercats'],
                    'admin.php?page=album-' // TODO not sure it's relevant to link to admin pages
                );
                $row['fullname'] = strip_tags($cat_display_name);

                $fullname_of[$row['id']] = $row['fullname'];
            }

            $template->assign('fullname_of', json_encode($fullname_of));

            // in case the search has forbidden albums for current user, we need to filter the search rule
            $my_search['fields']['cat']['words'] = array_intersect($my_search['fields']['cat']['words'], array_keys($fullname_of));
        }

        if (isset($my_search['fields']['filetypes'])) {
            $sql_condition = get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id',
                ],
                'AND'
            );

            $query = <<<SQL
                SELECT SUBSTRING_INDEX(path, '.', -1) AS ext, COUNT(DISTINCT(id)) AS counter
                FROM images AS i
                JOIN image_category AS ic ON ic.image_id = i.id
                WHERE {$search_items_clause}
                    {$sql_condition}
                GROUP BY ext
                ORDER BY counter DESC;
                SQL;
            $template->assign('FILETYPES', query2array($query, 'ext', 'counter'));
        }

        $template->assign(
            [
                'GP' => json_encode($my_search),
                'SEARCH_ID' => $page['search'],
            ]
        );

        if ($page['start'] == 0 && ! isset($page['chronology_field']) && isset($page['search_details'])) {
            if (isset($page['search_details']['matching_cat_ids'])) {
                $cat_ids = $page['search_details']['matching_cat_ids'];
                if (count($cat_ids) > 0) {
                    $cat_ids_str = implode(',', $cat_ids);
                    $query = <<<SQL
                        SELECT c.*
                        FROM categories AS c
                        INNER JOIN user_cache_categories ON c.id = cat_id AND user_id = {$user['id']}
                        WHERE id IN ({$cat_ids_str});
                        SQL;
                    $cats = query2array($query);
                    usort($cats, 'name_compare');
                    $albums_found = [];
                    foreach ($cats as $cat) {
                        $single_link = false;
                        $albums_found[] = get_cat_display_name_cache(
                            $cat['uppercats'],
                            '',
                            $single_link
                        );
                    }

                    if ($albums_found !== []) {
                        $template->assign('ALBUMS_FOUND', $albums_found);
                    }
                }
            }

            if (isset($page['search_details']['matching_tag_ids'])) {
                $tag_ids = $page['search_details']['matching_tag_ids'];

                if (count($tag_ids) > 0) {
                    $tags = get_available_tags($tag_ids);
                    usort($tags, 'tag_alpha_compare');
                    $tags_found = [];
                    foreach ($tags as $tag) {
                        $url = make_index_url(
                            [
                                'tags' => [$tag],
                            ]
                        );
                        $tags_found[] = sprintf('<a href="%s">%s</a>', $url, $tag['name']);
                    }

                    if ($tags_found !== []) {
                        $template->assign('TAGS_FOUND', $tags_found);
                    }
                }
            }
        }
    }

    if ($page['section'] == 'categories' && isset($page['category']) && ! isset($page['combined_categories'])) {
        $template->assign(
            [
                'SEARCH_IN_SET_BUTTON' => $conf['index_search_in_set_button'],
                'SEARCH_IN_SET_ACTION' => $conf['index_search_in_set_action'],
                'SEARCH_IN_SET_URL' => get_root_url() . 'search.php?cat_id=' . $page['category']['id'],
            ]
        );
    }

    if (isset($page['body_data']['tag_ids'])) {
        $template->assign(
            [
                'SEARCH_IN_SET_BUTTON' => $conf['index_search_in_set_button'],
                'SEARCH_IN_SET_ACTION' => $conf['index_search_in_set_action'],
                'SEARCH_IN_SET_URL' => get_root_url() . 'search.php?tag_id=' . implode(',', $page['body_data']['tag_ids']),
            ]
        );
    }

    if (isset($page['category']) && is_admin() && $conf['index_edit_icon']) {
        $template->assign(
            'U_EDIT',
            get_root_url() . 'admin.php?page=album-' . $page['category']['id']
        );
    }

    if (is_admin() && ! empty($page['items']) && $conf['index_caddie_icon']) {
        $template->assign(
            'U_CADDIE',
            add_url_params(duplicate_index_url(), [
                'caddie' => 1,
            ])
        );
    }

    if ($page['section'] == 'search' && $page['start'] == 0 && ! isset($page['chronology_field']) && isset($page['qsearch_details'])) {
        $cats = array_merge(
            $page['qsearch_details']['matching_cats_no_images'] ?? [],
            $page['qsearch_details']['matching_cats'] ?? []
        );
        if ($cats !== []) {
            usort($cats, name_compare(...));
            $hints = [];
            foreach ($cats as $cat) {
                $hints[] = get_cat_display_name([$cat], '');
            }

            $template->assign('category_search_results', $hints);
        }

        $tags = (array) $page['qsearch_details']['matching_tags'];
        foreach ($tags as $tag) {
            $tag['URL'] = make_index_url([
                'tags' => [$tag],
            ]);
            $template->append('tag_search_results', $tag);
        }

        if (empty($page['items'])) {
            $template->append('no_search_results', htmlspecialchars((string) $page['qsearch_details']['q']));
        } elseif (! empty($page['qsearch_details']['unmatched_terms'])) {
            $template->assign('no_search_results', array_map(htmlspecialchars(...), $page['qsearch_details']['unmatched_terms']));
        }
    }

    // image order
    if ($conf['index_sort_order_input'] && count($page['items']) > 0 && $page['section'] != 'most_visited' && $page['section'] != 'best_rated') {
        $preferred_image_orders = get_category_preferred_image_orders();
        $order_idx = pwg_get_session_var('image_order', 0);

        // get first order field and direction
        $first_order = substr((string) $conf['order_by'], 9);
        if (($pos = strpos($first_order, ',')) !== false) {
            $first_order = substr($first_order, 0, $pos);
        }

        $first_order = trim($first_order);

        $url = add_url_params(
            duplicate_index_url(),
            [
                'image_order' => '',
            ]
        );
        $tpl_orders = [];
        $order_selected = false;

        foreach ($preferred_image_orders as $order_id => $order) {
            if ($order[2]) {
                // force select if the field is the first field of order_by
                if (! $order_selected && $order[1] == $first_order) {
                    $order_idx = $order_id;
                    $order_selected = true;
                }

                $tpl_orders[$order_id] = [
                    'DISPLAY' => $order[0],
                    'URL' => $url . $order_id,
                    'SELECTED' => $order_idx == $order_id,
                ];
            }
        }

        $tpl_orders[0]['SELECTED'] = ! $order_selected; // unselect "Default" if another one is selected
        $template->assign('image_orders', $tpl_orders);
    }

    // category comment
    if (($page['start'] == 0 || $conf['album_description_on_all_pages']) && ! isset($page['chronology_field']) && ! empty($page['comment'])) {
        $template->assign('CONTENT_DESCRIPTION', $page['comment']);
    }

    if (isset($page['category']['count_categories']) && $page['category']['count_categories'] == 0) {// count_categories might be computed by menubar - if the case unassign flat link if no sub albums
        $template->clear_assign('U_MODE_FLAT');
    }

    //------------------------------------------------------ main part : thumbnails
    if ($page['start'] == 0 && ! isset($page['flat']) && ! isset($page['chronology_field']) && ($page['section'] == 'recent_cats' || $page['section'] == 'categories') && (! isset($page['category']['count_categories']) || $page['category']['count_categories'] > 0)
    ) {
        require PHPWG_ROOT_PATH . 'include/category_cats.inc.php';
    }

    if (! empty($page['items'])) {
        require PHPWG_ROOT_PATH . 'include/category_default.inc.php';

        if ($conf['index_sizes_icon']) {
            $url = add_url_params(
                duplicate_index_url(),
                [
                    'display' => '',
                ]
            );

            $selected_type = $template->get_template_vars('derivative_params')
                ->type;
            $template->clear_assign('derivative_params');
            $type_map = ImageStdParams::get_defined_type_map();
            unset($type_map[IMG_XXLARGE], $type_map[IMG_XLARGE]);

            foreach ($type_map as $params) {
                $template->append(
                    'image_derivatives',
                    [
                        'DISPLAY' => l10n($params->type),
                        'URL' => $url . $params->type,
                        'SELECTED' => ($params->type == $selected_type),
                    ]
                );
            }
        }
    }

    // slideshow
    // execute after init thumbs to have all picture information
    if (! empty($page['cat_slideshow_url'])) {
        if (isset($_GET['slideshow'])) {
            redirect($page['cat_slideshow_url']);
        } elseif ($conf['index_slideshow_icon']) {
            $template->assign('U_SLIDESHOW', $page['cat_slideshow_url']);
        }
    }
}

//------------------------------------------------------------ end
require PHPWG_ROOT_PATH . 'include/page_header.php';
trigger_notify('loc_end_index');
flush_page_messages();
$template->parse_index_buttons();
$template->pparse('index');

//------------------------------------------------------------ log information
pwg_log();
require PHPWG_ROOT_PATH . 'include/page_tail.php';
