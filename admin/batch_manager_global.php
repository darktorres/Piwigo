<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Management of elements set. Elements can belong to a category or to the
 * user caddie.
 */

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

if (! empty($_POST)) {
    check_pwg_token();
}

trigger_notify('loc_begin_element_set_global');

check_input_parameter('del_tags', $_POST, true, PATTERN_ID);
check_input_parameter('associate', $_POST, false, PATTERN_ID);
check_input_parameter('dissociate', $_POST, false, PATTERN_ID);

// +-----------------------------------------------------------------------+
// |                            current selection                          |
// +-----------------------------------------------------------------------+

$collection = [];
if (isset($_POST['nb_photos_deleted'])) {
    check_input_parameter('nb_photos_deleted', $_POST, false, '/^\d+$/');

    // let's fake a collection (we don't know the image_ids so we use "null", we only
    // care about the number of items here)
    $collection = array_fill(0, $_POST['nb_photos_deleted'], null);
} elseif (isset($_POST['setSelected'])) {
    // Here we don't use check_input_parameter because preg_match has a limit in
    // the repetitive pattern. Found a limit to 3276 but may depend on memory.
    //
    // check_input_parameter('whole_set', $_POST, false, '/^\d+(,\d+)*$/');
    //
    // Instead, let's break the input parameter into pieces and check pieces one by one.
    $collection = explode(',', $_POST['whole_set']);

    foreach ($collection as $id) {
        if (! preg_match('/^\d+$/', $id)) {
            fatal_error('[Hacking attempt] the input parameter "whole_set" is not valid');
        }
    }
} elseif (isset($_POST['selection'])) {
    $collection = $_POST['selection'];
}

// +-----------------------------------------------------------------------+
// |                       global mode form submission                     |
// +-----------------------------------------------------------------------+

// $page['prefilter'] is a shortcut to test if the current filter contains a
// given prefilter. The idea is to make conditions simpler to write in the
// code.
$page['prefilter'] = 'none';
if (isset($_SESSION['bulk_manager_filter']['prefilter'])) {
    $page['prefilter'] = $_SESSION['bulk_manager_filter']['prefilter'];
}

$redirect_url = get_root_url() . 'admin.php?page=' . $_GET['page'];

if (isset($_POST['submit'])) {
    // if the user tries to apply an action, it means that there is at least 1
    // photo in the selection
    if (count($collection) == 0) {
        $page['errors'][] = l10n('Select at least one photo');
    }

    $action = $_POST['selectAction'];
    $redirect = false;

    if ($action == 'remove_from_caddie') {
        $collection_str = implode(',', $collection);
        $query = <<<SQL
            DELETE FROM caddie
            WHERE element_id IN ({$collection_str})
                AND user_id = {$user['id']};
            SQL;
        pwg_query($query);

        // remove from caddie action available only in caddie so reload content
        $redirect = true;
    } elseif ($action == 'add_tags') {
        if (empty($_POST['add_tags'])) {
            $page['errors'][] = l10n('Select at least one tag');
        } else {
            $tag_ids = get_tag_ids($_POST['add_tags']);
            add_tags($tag_ids, $collection);

            if ($page['prefilter'] == 'no_tag') {
                $redirect = true;
            }
        }
    } elseif ($action == 'del_tags') {
        if (isset($_POST['del_tags']) and count($_POST['del_tags']) > 0) {
            $taglist_before = get_image_tag_ids($collection);

            $collection_str = implode(',', $collection);
            $del_tags_str = implode(',', $_POST['del_tags']);
            $query = <<<SQL
                DELETE FROM image_tag
                WHERE image_id IN ({$collection_str})
                    AND tag_id IN ({$del_tags_str});
                SQL;
            pwg_query($query);

            $taglist_after = get_image_tag_ids($collection);
            $images_to_update = compare_image_tag_lists($taglist_before, $taglist_after);
            update_images_lastmodified($images_to_update);

            if (isset($_SESSION['bulk_manager_filter']['tags']) &&
              count(array_intersect($_SESSION['bulk_manager_filter']['tags'], $_POST['del_tags']))) {
                $redirect = true;
            }
        } else {
            $page['errors'][] = l10n('Select at least one tag');
        }
    }

    if ($action == 'associate') {
        associate_images_to_categories(
            $collection,
            [$_POST['associate']]
        );

        $_SESSION['page_infos'] = [
            l10n('Information data registered in database'),
        ];

        // let's refresh the page because we the current set might be modified
        if ($page['prefilter'] == 'no_album') {
            $redirect = true;
        } elseif ($page['prefilter'] == 'no_virtual_album') {
            $category_info = get_cat_info($_POST['associate']);
            if (empty($category_info['dir'])) {
                $redirect = true;
            }
        }
    } elseif ($action == 'move') {
        move_images_to_categories($collection, [$_POST['associate']]);

        $_SESSION['page_infos'] = [
            l10n('Information data registered in database'),
        ];

        // let's refresh the page because we the current set might be modified
        if ($page['prefilter'] == 'no_album') {
            $redirect = true;
        } elseif ($page['prefilter'] == 'no_virtual_album') {
            $category_info = get_cat_info($_POST['associate']);
            if (empty($category_info['dir'])) {
                $redirect = true;
            }
        } elseif (isset($_SESSION['bulk_manager_filter']['category'])
            and $_POST['move'] != $_SESSION['bulk_manager_filter']['category']) {
            $redirect = true;
        }
    } elseif ($action == 'dissociate') {
        $nb_dissociated = dissociate_images_from_category($collection, $_POST['dissociate']);

        if ($nb_dissociated > 0) {
            $_SESSION['page_infos'] = [
                l10n('Information data registered in database'),
            ];

            // let's refresh the page because the current set might be modified
            $redirect = true;
        }
    }

    // author
    elseif ($action == 'author') {
        if (isset($_POST['remove_author'])) {
            $_POST['author'] = null;
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'author' => $_POST['author'],
            ];
        }

        mass_updates(
            'images',
            [
                'primary' => ['id'],
                'update' => ['author'],
            ],
            $datas
        );

        pwg_activity('photo', $collection, 'edit', [
            'action' => 'author',
        ]);
    }

    // title
    elseif ($action == 'title') {
        if (isset($_POST['remove_title'])) {
            $_POST['title'] = null;
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'name' => $_POST['title'],
            ];
        }

        mass_updates(
            'images',
            [
                'primary' => ['id'],
                'update' => ['name'],
            ],
            $datas
        );

        pwg_activity('photo', $collection, 'edit', [
            'action' => 'title',
        ]);
    }

    // date_creation
    elseif ($action == 'date_creation') {
        if (isset($_POST['remove_date_creation']) || empty($_POST['date_creation'])) {
            $date_creation = null;
        } else {
            $date_creation = $_POST['date_creation'];
        }

        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'date_creation' => $date_creation,
            ];
        }

        mass_updates(
            'images',
            [
                'primary' => ['id'],
                'update' => ['date_creation'],
            ],
            $datas
        );

        pwg_activity('photo', $collection, 'edit', [
            'action' => 'date_creation',
        ]);
    }

    // privacy_level
    elseif ($action == 'level') {
        $datas = [];
        foreach ($collection as $image_id) {
            $datas[] = [
                'id' => $image_id,
                'level' => $_POST['level'],
            ];
        }

        mass_updates(
            'images',
            [
                'primary' => ['id'],
                'update' => ['level'],
            ],
            $datas
        );

        pwg_activity('photo', $collection, 'edit', [
            'action' => 'privacy_level',
        ]);

        if (isset($_SESSION['bulk_manager_filter']['level'])) {
            if ($_POST['level'] < $_SESSION['bulk_manager_filter']['level']) {
                $redirect = true;
            }
        }
    }

    // add_to_caddie
    elseif ($action == 'add_to_caddie') {
        fill_caddie($collection);
    }

    // delete
    elseif ($action == 'delete') {
        if (isset($_POST['confirm_deletion']) and $_POST['confirm_deletion'] == 1) {
            // now done with ajax calls, with blocks
            // $deleted_count = delete_elements($collection, true);
            if (count($collection) > 0) {
                $_SESSION['page_infos'][] = l10n_dec(
                    '%d photo was deleted',
                    '%d photos were deleted',
                    count($collection)
                );

                $redirect_url = get_root_url() . 'admin.php?page=' . $_GET['page'];
                $redirect = true;
            } else {
                $page['errors'][] = l10n('No photo can be deleted');
            }
        } else {
            $page['errors'][] = l10n('You need to confirm deletion');
        }
    }

    // synchronize metadata
    elseif ($action == 'metadata') {
        $page['infos'][] = l10n('Metadata synchronized from file') . ' <span class="badge">' . count($collection) . '</span>';
    } elseif ($action == 'delete_derivatives' && ! empty($_POST['del_derivatives_type'])) {
        $collection_str = implode(',', $collection);
        $query = <<<SQL
            SELECT path, representative_ext
            FROM images
            WHERE id IN ({$collection_str});
            SQL;
        $result = pwg_query($query);
        while ($info = pwg_db_fetch_assoc($result)) {
            foreach ($_POST['del_derivatives_type'] as $type) {
                delete_element_derivatives($info, $type);
            }
        }
    } elseif ($action == 'generate_derivatives') {
        if ($_POST['regenerateSuccess'] != '0') {
            $page['infos'][] = l10n('%s photos have been regenerated', $_POST['regenerateSuccess']);
        }
        if ($_POST['regenerateError'] != '0') {
            $page['warnings'][] = l10n('%s photos can not be regenerated', $_POST['regenerateError']);
        }
    }

    if (! in_array($action, ['remove_from_caddie', 'add_to_caddie', 'delete_derivatives', 'generate_derivatives'])) {
        invalidate_user_cache();
    }

    trigger_notify('element_set_global_action', $action, $collection);

    if ($redirect) {
        redirect($redirect_url);
    }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+
$template->set_filenames([
    'batch_manager_global' => 'batch_manager_global.tpl',
]);

$base_url = get_root_url() . 'admin.php';

$prefilters = [
    [
        'ID' => 'caddie',
        'NAME' => l10n('Caddie'),
    ],
    [
        'ID' => 'favorites',
        'NAME' => l10n('Your favorites'),
    ],
    [
        'ID' => 'last_import',
        'NAME' => l10n('Last import'),
    ],
    [
        'ID' => 'no_album',
        'NAME' => l10n('With no album') . ' (' . l10n('Orphans') . ')',
    ],
    [
        'ID' => 'no_tag',
        'NAME' => l10n('With no tag'),
    ],
    [
        'ID' => 'duplicates',
        'NAME' => l10n('Duplicates'),
    ],
    [
        'ID' => 'all_photos',
        'NAME' => l10n('All'),
    ],
];

if ($conf['enable_synchronization']) {
    $prefilters[] = [
        'ID' => 'no_virtual_album',
        'NAME' => l10n('With no virtual album'),
    ];
    $prefilters[] = [
        'ID' => 'no_sync_md5sum',
        'NAME' => l10n('With no checksum'),
    ];
}

function UC_name_compare(
    array $a,
    array $b
): int {
    return strcmp(strtolower($a['NAME']), strtolower($b['NAME']));
}

$prefilters = trigger_change('get_batch_manager_prefilters', $prefilters);

// Sort prefilters by localized name.
usort($prefilters, function (array $a, array $b): int {
    return strcmp(strtolower($a['NAME']), strtolower($b['NAME']));
});

$template->assign(
    [
        'conf_checksum_compute_blocksize' => $conf['checksum_compute_blocksize'],
        'prefilters' => $prefilters,
        'filter' => $_SESSION['bulk_manager_filter'],
        'selection' => $collection,
        'all_elements' => $page['cat_elements_id'],
        'START' => $page['start'],
        'PWG_TOKEN' => get_pwg_token(),
        'U_DISPLAY' => $base_url . get_query_string_diff(['display']),
        'F_ACTION' => $base_url . get_query_string_diff(['cat', 'start', 'tag', 'filter']),
        'ADMIN_PAGE_TITLE' => l10n('Batch Manager'),
    ]
);

if (isset($page['no_md5sum_number'])) {
    $template->assign(
        [
            'NB_NO_MD5SUM' => $page['no_md5sum_number'],
        ]
    );
} else {
    $template->assign('NB_NO_MD5SUM', '');
}

// +-----------------------------------------------------------------------+
// |                            caddie options                             |
// +-----------------------------------------------------------------------+
$template->assign('IN_CADDIE', $page['prefilter'] == 'caddie');

// +-----------------------------------------------------------------------+
// |                           global mode form                            |
// +-----------------------------------------------------------------------+

// privacy level
foreach ($conf['available_permission_levels'] as $level) {
    $level_options[$level] = l10n(sprintf('Level %d', $level));

    if ($level == 0) {
        $level_options[$level] = l10n('Everybody');
    }
}
$template->assign(
    [
        'filter_level_options' => $level_options,
        'filter_level_options_selected' => isset($_SESSION['bulk_manager_filter']['level'])
        ? $_SESSION['bulk_manager_filter']['level']
        : 0,
    ]
);

// tags
$filter_tags = [];

if (! empty($_SESSION['bulk_manager_filter']['tags'])) {
    $tag_ids = implode(',', $_SESSION['bulk_manager_filter']['tags']);
    $query = <<<SQL
        SELECT id, name
        FROM tags
        WHERE id IN ({$tag_ids});
        SQL;

    $filter_tags = get_taglist($query);
}

$template->assign('filter_tags', $filter_tags);

// in the filter box, which category to select by default
$selected_category = [];

if (isset($_SESSION['bulk_manager_filter']['category'])) {
    $selected_category = [$_SESSION['bulk_manager_filter']['category']];
} else {
    // we need to know the category in which the last photo was added
    $query = <<<SQL
        SELECT category_id
        FROM image_category
        ORDER BY image_id DESC
        LIMIT 1;
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) > 0) {
        $row = pwg_db_fetch_assoc($result);
        $selected_category[] = $row['category_id'];
    }
}

$template->assign('filter_category_selected', $selected_category);

// Dissociate from a category: categories listed for dissociation can only
// represent virtual links. We can't create orphans. Links to physical
// categories can't be broken.
$associated_categories = [];

if (count($page['cat_elements_id']) > 0) {
    $implodedCatElementsId = implode(',', $page['cat_elements_id']);
    $query = <<<SQL
        SELECT DISTINCT(category_id) AS id
        FROM image_category AS ic
        JOIN images AS i ON i.id = ic.image_id
        WHERE ic.image_id IN ({$implodedCatElementsId})
            AND (ic.category_id != i.storage_category_id OR i.storage_category_id IS NULL);
        SQL;

    $associated_categories = query2array($query, 'id', 'id');
}

$template->assign('associated_categories', $associated_categories);

if (count($page['cat_elements_id']) > 0) {
    // remove tags
    $template->assign('associated_tags', get_common_tags($page['cat_elements_id'], -1));
}

// creation date
$template->assign(
    'DATE_CREATION',
    empty($_POST['date_creation']) ? date('Y-m-d') . ' 00:00:00' : $_POST['date_creation']
);

// image level options
$template->assign(
    [
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => 0,
    ]
);

// metadata
require_once PHPWG_ROOT_PATH . 'admin/site_reader_local.php';
$site_reader = new LocalSiteReader('./');
$used_metadata = implode(', ', $site_reader->get_metadata_attributes());

$template->assign(
    [
        'used_metadata' => $used_metadata,
    ]
);

//derivatives
$del_deriv_map = [];
foreach (ImageStdParams::get_defined_type_map() as $params) {
    $del_deriv_map[$params->type] = l10n($params->type);
}
$gen_deriv_map = $del_deriv_map;
$del_deriv_map[IMG_CUSTOM] = l10n(IMG_CUSTOM);
$template->assign(
    [
        'del_derivatives_types' => $del_deriv_map,
        'generate_derivatives_types' => $gen_deriv_map,
    ]
);

// +-----------------------------------------------------------------------+
// |                        global mode thumbnails                         |
// +-----------------------------------------------------------------------+

// how many items to display on this page
if (! empty($_GET['display'])) {
    if ($_GET['display'] == 'all') {
        $page['nb_images'] = count($page['cat_elements_id']);
    } else {
        $page['nb_images'] = intval($_GET['display']);
    }
} elseif (in_array($conf['batch_manager_images_per_page_global'], [20, 50, 100])) {
    $page['nb_images'] = $conf['batch_manager_images_per_page_global'];
} else {
    $page['nb_images'] = 20;
}

$nb_thumbs_page = 0;

if (count($page['cat_elements_id']) > 0) {
    $nav_bar = create_navigation_bar(
        $base_url . get_query_string_diff(['start']),
        count($page['cat_elements_id']),
        (int) $page['start'],
        $page['nb_images']
    );
    $template->assign('navbar', $nav_bar);

    $is_category = false;
    if (isset($_SESSION['bulk_manager_filter']['category'])
        and ! isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $is_category = true;
    }

    // If using the 'duplicates' filter,
    // order by the fields that are used to find duplicates.
    if (isset($_SESSION['bulk_manager_filter']['prefilter'])
        and $_SESSION['bulk_manager_filter']['prefilter'] === 'duplicates'
        and isset($duplicates_on_fields)) {
        // The $duplicates_on_fields variable is defined in ./batch_manager.php
        $order_by_fields = array_merge($duplicates_on_fields, ['id']);
        $conf['order_by'] = ' ORDER BY ' . join(', ', $order_by_fields);
    }

    $query = <<<SQL
        SELECT id, path, representative_ext, file, filesize, level, name, width, height, rotation
        FROM images

        SQL;

    if ($is_category) {
        $category_info = get_cat_info($_SESSION['bulk_manager_filter']['category']);

        $conf['order_by'] = $conf['order_by_inside_category'];
        if (! empty($category_info['image_order'])) {
            $conf['order_by'] = ' ORDER BY ' . $category_info['image_order'];
        }

        $query .= <<<SQL
            JOIN image_category ON id = image_id

            SQL;
    }

    $cat_elements_id = implode(',', $page['cat_elements_id']);
    $query .= <<<SQL
        WHERE id IN ({$cat_elements_id})

        SQL;

    if ($is_category) {
        $query .= <<<SQL
            AND category_id = {$_SESSION['bulk_manager_filter']['category']}

            SQL;
    }

    $query .= <<<SQL
        {$conf['order_by']}
        LIMIT {$page['nb_images']} OFFSET {$page['start']};
        SQL;
    $result = pwg_query($query);

    $thumb_params = ImageStdParams::get_by_type(IMG_SQUARE);
    // template thumbnail initialization
    while ($row = pwg_db_fetch_assoc($result)) {
        $nb_thumbs_page++;
        $src_image = new SrcImage($row);

        $title = render_element_name($row);
        if ($title != get_name_from_file($row['file'])) {
            $title .= ' (' . $row['file'] . ')';
        }

        $title .= '<br>' . $row['width'] . '&times;' . $row['height'] . ' pixels, ' . sprintf('%.2f', $row['filesize'] / 1024) . 'MB';

        $template->append(
            'thumbnails',
            array_merge(
                $row,
                [
                    'thumb' => new DerivativeImage($thumb_params, $src_image),
                    'TITLE' => $title,
                    'FILE_SRC' => DerivativeImage::url(IMG_LARGE, $src_image),
                    'U_EDIT' => get_root_url() . 'admin.php?page=photo-' . $row['id'],
                ]
            )
        );
    }
    $template->assign('thumb_params', $thumb_params);
}

$template->assign([
    'nb_thumbs_page' => $nb_thumbs_page,
    'nb_thumbs_set' => count($page['cat_elements_id']),
    'CACHE_KEYS' => get_admin_client_cache_keys(['tags', 'categories']),
]);

trigger_notify('loc_end_element_set_global');

//----------------------------------------------------------- sending html code
$template->assign_var_from_handle('ADMIN_CONTENT', 'batch_manager_global');
