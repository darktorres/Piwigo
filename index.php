<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//--------------------------------------------------------------------- include
const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
include(PHPWG_ROOT_PATH . 'include/section_init.inc.php');

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
        true
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
include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');

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
        if ($page['chronology_field'] == 'created') {
            $chronology_field = 'posted';
        } else {
            $chronology_field = 'created';
        }
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

    if ($page['section'] == 'search') {
        $template->assign(
            'U_SEARCH_RULES',
            get_root_url() . 'search_rules.php?search_id=' . $page['search']
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

    if ($page['section'] == 'search' && $page['start'] == 0 and
        ! isset($page['chronology_field']) && isset($page['qsearch_details'])) {
        $cats = array_merge(
            ($page['qsearch_details']['matching_cats_no_images']) ?? [],
            $page['qsearch_details']['matching_cats']
        );
        if (count($cats)) {
            usort($cats, 'name_compare');
            $hints = [];
            foreach ($cats as $cat) {
                $hints[] = get_cat_display_name([$cat]);
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
            $template->assign(
                'no_search_results',
                array_map('htmlspecialchars', $page['qsearch_details']['unmatched_terms'])
            );
        }
    }

    // image order
    if ($conf['index_sort_order_input']
        && count($page['items']) > 0
        && $page['section'] != 'most_visited'
        && $page['section'] != 'best_rated') {
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
        $template->clear_assign(
            'U_MODE_FLAT'
        );
    }

    //------------------------------------------------------ main part : thumbnails
    if ($page['start'] == 0
      && ! isset($page['flat'])
      && ! isset($page['chronology_field'])
      && ($page['section'] == 'recent_cats' || $page['section'] == 'categories')
      && (! isset($page['category']['count_categories']) || $page['category']['count_categories'] > 0)
    ) {
        include(PHPWG_ROOT_PATH . 'include/category_cats.inc.php');
    }

    if (! empty($page['items'])) {
        include(PHPWG_ROOT_PATH . 'include/category_default.inc.php');

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
                        'SELECTED' => $params->type == $selected_type,
                    ]
                );
            }
        }
    }

    // slideshow
    // execute after init thumbs in order to have all picture informations
    if (! empty($page['cat_slideshow_url'])) {
        if (isset($_GET['slideshow'])) {
            redirect($page['cat_slideshow_url']);
        } elseif ($conf['index_slideshow_icon']) {
            $template->assign('U_SLIDESHOW', $page['cat_slideshow_url']);
        }
    }
}

//------------------------------------------------------------ end
include(PHPWG_ROOT_PATH . 'include/page_header.php');
trigger_notify('loc_end_index');
flush_page_messages();
$template->parse_index_buttons();
$template->pparse('index');

//------------------------------------------------------------ log informations
pwg_log();
include(PHPWG_ROOT_PATH . 'include/page_tail.php');
