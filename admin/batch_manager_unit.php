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

trigger_notify('loc_begin_element_set_unit');

// +-----------------------------------------------------------------------+
// |                        unit mode form submission                      |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    check_pwg_token();
    check_input_parameter('element_ids', $_POST, false, '/^\d+(,\d+)*$/');
    $collection = explode(',', (string) $_POST['element_ids']);

    $datas = [];

    $imploded_collection = implode(',', $collection);
    $query = <<<SQL
        SELECT id, date_creation
        FROM images
        WHERE id IN ({$imploded_collection});
        SQL;
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_assoc($result)) {
        $data = [];

        $data['id'] = $row['id'];
        $data['name'] = $_POST['name-' . $row['id']];
        $data['author'] = $_POST['author-' . $row['id']];
        $data['level'] = $_POST['level-' . $row['id']];

        if ($conf['allow_html_descriptions']) {
            $data['comment'] = $_POST['description-' . $row['id']];
        } else {
            $data['comment'] = strip_tags((string) $_POST['description-' . $row['id']]);
        }

        $data['date_creation'] = empty($_POST['date_creation-' . $row['id']]) ? null : $_POST['date_creation-' . $row['id']];

        $datas[] = $data;

        // tags management
        $tag_ids = [];
        if (! empty($_POST['tags-' . $row['id']])) {
            $tag_ids = get_tag_ids($_POST['tags-' . $row['id']]);
        }

        set_tags($tag_ids, $row['id']);
    }

    mass_updates(
        'images',
        [
            'primary' => ['id'],
            'update' => ['name', 'author', 'level', 'comment', 'date_creation'],
        ],
        $datas
    );

    $page['infos'][] = l10n('Photo informations updated');
    invalidate_user_cache();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'batch_manager_unit' => 'batch_manager_unit.tpl',
    ]
);

$base_url = PHPWG_ROOT_PATH . 'admin.php';

$template->assign(
    [
        'U_ELEMENTS_PAGE' => $base_url . get_query_string_diff(['display', 'start']),
        'F_ACTION' => $base_url . get_query_string_diff([]),
        'level_options' => get_privacy_level_options(),
        'ADMIN_PAGE_TITLE' => l10n('Batch Manager'),
        'PWG_TOKEN' => get_pwg_token(),
    ]
);

// +-----------------------------------------------------------------------+
// |                        global mode thumbnails                         |
// +-----------------------------------------------------------------------+

// how many items to display on this page
if (! empty($_GET['display'])) {
    $page['nb_images'] = intval($_GET['display']);
} elseif (in_array($conf['batch_manager_images_per_page_unit'], [5, 10, 50])) {
    $page['nb_images'] = $conf['batch_manager_images_per_page_unit'];
} else {
    $page['nb_images'] = 5;
}

if (count($page['cat_elements_id']) > 0) {
    $nav_bar = create_navigation_bar(
        $base_url . get_query_string_diff(['start']),
        count($page['cat_elements_id']),
        (int) $page['start'],
        $page['nb_images']
    );
    $template->assign([
        'navbar' => $nav_bar,
    ]);

    $element_ids = [];

    $is_category = false;
    if (isset($_SESSION['bulk_manager_filter']['category']) && ! isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $is_category = true;
    }

    if (isset($_SESSION['bulk_manager_filter']['prefilter']) && $_SESSION['bulk_manager_filter']['prefilter'] == 'duplicates') {
        $conf['order_by'] = ' ORDER BY file, id';
    }

    $query = <<<SQL
        SELECT *
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

    $ids = implode(',', $page['cat_elements_id']);
    $query .= <<<SQL
        WHERE id IN ({$ids})

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

    while ($row = pwg_db_fetch_assoc($result)) {
        $element_ids[] = $row['id'];

        $src_image = new SrcImage($row);

        $query = <<<SQL
            SELECT id, name
            FROM image_tag AS it
            JOIN tags AS t ON t.id = it.tag_id
            WHERE image_id = {$row['id']};
            SQL;
        $tag_selection = get_taglist($query);

        $legend = render_element_name($row);
        if ($legend !== get_name_from_file($row['file'])) {
            $legend .= ' (' . $row['file'] . ')';
        }

        $extTab = explode('.', (string) $row['path']);

        $template->append(
            'elements',
            array_merge(
                $row,
                [
                    'ID' => $row['id'],
                    'TN_SRC' => DerivativeImage::url(IMG_THUMB, $src_image),
                    'FILE_SRC' => DerivativeImage::url(IMG_LARGE, $src_image),
                    'LEGEND' => $legend,
                    'U_EDIT' => get_root_url() . 'admin.php?page=photo-' . $row['id'],
                    'NAME' => htmlspecialchars($row['name'] ?? ''),
                    'AUTHOR' => htmlspecialchars($row['author'] ?? ''),
                    'LEVEL' => empty($row['level']) ? '0' : $row['level'],
                    'DESCRIPTION' => htmlspecialchars($row['comment'] ?? ''),
                    'DATE_CREATION' => $row['date_creation'],
                    'TAGS' => $tag_selection,
                    'is_svg' => (strtoupper(end($extTab)) === 'SVG'),
                ]
            )
        );
    }

    $template->assign([
        'ELEMENT_IDS' => implode(',', $element_ids),
        'CACHE_KEYS' => get_admin_client_cache_keys(['tags']),
    ]);
}

trigger_notify('loc_end_element_set_unit');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'batch_manager_unit');
