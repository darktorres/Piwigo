<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\inc\DerivativeImage;
use Piwigo\inc\SrcImage;
use function Piwigo\admin\inc\delete_elements;
use function Piwigo\admin\inc\get_admin_client_cache_keys;
use function Piwigo\admin\inc\get_image_infos;
use function Piwigo\admin\inc\get_tag_ids;
use function Piwigo\admin\inc\get_taglist;
use function Piwigo\admin\inc\invalidate_user_cache;
use function Piwigo\admin\inc\move_images_to_categories;
use function Piwigo\admin\inc\set_random_representant;
use function Piwigo\admin\inc\set_tags;
use function Piwigo\admin\inc\sync_metadata;
use function Piwigo\inc\calculate_permissions;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\dbLayer\single_update;
use function Piwigo\inc\format_date;
use function Piwigo\inc\get_cat_display_name_cache;
use function Piwigo\inc\get_cat_info;
use function Piwigo\inc\get_extension;
use function Piwigo\inc\get_privacy_level_options;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_query_string_diff;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\make_picture_url;
use function Piwigo\inc\pwg_activity;
use function Piwigo\inc\redirect;
use function Piwigo\inc\render_element_name;
use function Piwigo\inc\time_since;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\trigger_notify;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\IMG_LARGE;
use const Piwigo\inc\IMG_MEDIUM;
use const Piwigo\inc\PATTERN_ID;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('image_id', $_GET, false, PATTERN_ID);
check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

// retrieving direct information about picture. This may have been already
// done on admin/photo.php but this page can also be accessed without
// photo.php as proxy.
if (! isset($page['image'])) {
    $page['image'] = get_image_infos($_GET['image_id'], true);
}

// represent
$query = '
SELECT id
  FROM ' . CATEGORIES_TABLE . '
  WHERE representative_picture_id = ' . $_GET['image_id'] . '
;';
$represented_albums = query2array($query, null, 'id');

// +-----------------------------------------------------------------------+
// |                             delete photo                              |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete'])) {
    check_pwg_token();

    delete_elements([$_GET['image_id']], true);
    invalidate_user_cache();

    // where to redirect the user now?
    //
    // 1. if a category is available in the URL, use it
    // 2. else use the first reachable linked category
    // 3. redirect to gallery root

    if (isset($_GET['cat_id']) && ! empty($_GET['cat_id'])) {
        redirect(
            make_index_url(
                [
                    'category' => get_cat_info($_GET['cat_id']),
                ]
            )
        );
    }

    $query = '
SELECT category_id
  FROM ' . IMAGE_CATEGORY_TABLE . '
  WHERE image_id = ' . $_GET['image_id'] . '
;';

    $authorizeds = array_diff(
        query2array($query, null, 'category_id'),
        explode(',', calculate_permissions($user['id'], $user['status']))
    );

    foreach ($authorizeds as $category_id) {
        redirect(
            make_index_url(
                [
                    'category' => get_cat_info($category_id),
                ]
            )
        );
    }

    redirect(make_index_url());
}

// +-----------------------------------------------------------------------+
// |                          synchronize metadata                         |
// +-----------------------------------------------------------------------+

if (isset($_GET['sync_metadata'])) {
    sync_metadata([intval($_GET['image_id'])]);
    $page['infos'][] = l10n('Metadata synchronized from file');
}

//--------------------------------------------------------- update informations
if (isset($_POST['submit'])) {
    check_pwg_token();

    $data = [];
    $data['id'] = $_GET['image_id'];
    $data['name'] = $_POST['name'];
    $data['author'] = $_POST['author'];
    $data['level'] = $_POST['level'];

    if ($conf['allow_html_descriptions']) {
        $data['comment'] = $_POST['description'];
    } else {
        $data['comment'] = strip_tags((string) $_POST['description']);
    }

    $data['date_creation'] = empty($_POST['date_creation']) ? null : $_POST['date_creation'];

    $data = trigger_change('picture_modify_before_update', $data);

    single_update(
        IMAGES_TABLE,
        $data,
        [
            'id' => $data['id'],
        ]
    );

    // time to deal with tags
    $tag_ids = [];
    if (! empty($_POST['tags'])) {
        $tag_ids = get_tag_ids($_POST['tags']);
    }

    set_tags($tag_ids, $_GET['image_id']);

    // association to albums
    if (! isset($_POST['associate'])) {
        $_POST['associate'] = [];
    }

    check_input_parameter('associate', $_POST, true, PATTERN_ID);
    move_images_to_categories([$_GET['image_id']], $_POST['associate']);

    invalidate_user_cache();

    // thumbnail for albums
    if (! isset($_POST['represent'])) {
        $_POST['represent'] = [];
    }

    check_input_parameter('represent', $_POST, true, PATTERN_ID);

    $no_longer_thumbnail_for = array_diff($represented_albums, $_POST['represent']);
    if ($no_longer_thumbnail_for !== []) {
        set_random_representant($no_longer_thumbnail_for);
    }

    $new_thumbnail_for = array_diff($_POST['represent'], $represented_albums);
    if ($new_thumbnail_for !== []) {
        $query = '
UPDATE ' . CATEGORIES_TABLE . '
  SET representative_picture_id = ' . $_GET['image_id'] . '
  WHERE id IN (' . implode(',', $new_thumbnail_for) . ')
;';
        pwg_query($query);
    }

    $represented_albums = $_POST['represent'];

    $page['infos'][] = l10n('Photo informations updated');
    pwg_activity('photo', $_GET['image_id'], 'edit');

    // refresh page cache
    $page['image'] = get_image_infos($_GET['image_id'], true);
}

// tags
$query = '
SELECT
    id,
    name
  FROM ' . IMAGE_TAG_TABLE . ' AS it
    JOIN ' . TAGS_TABLE . ' AS t ON t.id = it.tag_id
  WHERE image_id = ' . $_GET['image_id'] . '
;';
$tag_selection = get_taglist($query);

$row = $page['image'];

if (isset($data['date_creation'])) {
    $row['date_creation'] = $data['date_creation'];
}

$storage_category_id = null;
if (! empty($row['storage_category_id'])) {
    $storage_category_id = $row['storage_category_id'];
}

$image_file = $row['file'];

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'picture_modify' => 'picture_modify.tpl',
    ]
);

$admin_url_start = $admin_photo_base_url . '-properties';
$admin_url_start .= isset($_GET['cat_id']) ? '&amp;cat_id=' . $_GET['cat_id'] : '';

$src_image = new SrcImage($row);

// in case the photo needs a rotation of 90 degrees (clockwise or counterclockwise), we switch width and height
if (in_array(
    $row['rotation'],
    [1, 3]
)) {
    [$row['width'], $row['height']] = [$row['height'], $row['width']];
}

$template->assign(
    [
        'tag_selection' => $tag_selection,
        'U_DOWNLOAD' => 'action.php?id=' . $_GET['image_id'] . '&amp;part=e&amp;pwg_token=' . get_pwg_token() . '&amp;download',
        'U_SYNC' => $admin_url_start . '&amp;sync_metadata=1',
        'U_DELETE' => $admin_url_start . '&amp;delete=1&amp;pwg_token=' . get_pwg_token(),
        'U_HISTORY' => get_root_url() . 'admin.php?page=history&amp;filter_image_id=' . $_GET['image_id'],

        'PATH' => $row['path'],

        'TN_SRC' => DerivativeImage::url(IMG_MEDIUM, $src_image),
        'FILE_SRC' => DerivativeImage::url(IMG_LARGE, $src_image),

        'NAME' =>
          isset($_POST['name']) ?
            stripslashes((string) $_POST['name']) : $row['name'],

        'TITLE' => render_element_name($row),

        'DIMENSIONS' => $row['width'] . ' * ' . $row['height'],

        'FORMAT' => ($row['width'] >= $row['height']) ? 1 : 0, //0:horizontal, 1:vertical

        'FILESIZE' => $row['filesize'] . ' KB',

        'REGISTRATION_DATE' => format_date($row['date_available']),

        'AUTHOR' => htmlspecialchars(
            (string) (isset($_POST['author'])
            ? stripslashes((string) $_POST['author'])
            : (empty($row['author']) ? '' : $row['author']))
        ),

        'DATE_CREATION' => $row['date_creation'],

        'DESCRIPTION' =>
          htmlspecialchars((string) (isset($_POST['description']) ?
            stripslashes((string) $_POST['description']) : (empty($row['comment']) ? '' : $row['comment']))),

        'F_ACTION' =>
            get_root_url() . 'admin.php'
            . get_query_string_diff(['sync_metadata']),
    ]
);

$added_by = 'N/A';
$query = '
SELECT ' . $conf['user_fields']['username'] . ' AS username
  FROM ' . USERS_TABLE . '
  WHERE ' . $conf['user_fields']['id'] . ' = ' . $row['added_by'] . '
;';
$result = pwg_query($query);
while ($user_row = pwg_db_fetch_assoc($result)) {
    $row['added_by'] = $user_row['username'];
}

$extTab = explode('.', (string) $row['file']);

$intro_vars = [
    'file' => l10n('%s', $row['file']),
    'date' => l10n('Posted the %s', format_date($row['date_available'], ['day', 'month', 'year'])),
    'age' => l10n(ucfirst(time_since($row['date_available'], 'year'))),
    'added_by' => l10n('Added by %s', $row['added_by']),
    'size' => l10n('%s pixels, %.2f MB', $row['width'] . '&times;' . $row['height'], $row['filesize'] / 1024),
    'stats' => l10n('Visited %d times', $row['hit']),
    'id' => l10n((string) $row['id']),
    'ext' => l10n('%s file type', strtoupper(end($extTab))),
    'is_svg' => (strtoupper(end($extTab)) === 'SVG'),
];

if ($conf['rate'] && ! empty($row['rating_score'])) {
    $query = '
SELECT
    COUNT(*)
  FROM ' . RATE_TABLE . '
  WHERE element_id = ' . $_GET['image_id'] . '
;';
    [$row['nb_rates']] = pwg_db_fetch_row(pwg_query($query));

    $intro_vars['stats'] .= ', ' . sprintf(
        l10n('Rated %d times, score : %.2f'),
        $row['nb_rates'],
        $row['rating_score']
    );
}

$query = '
SELECT *
  FROM ' . IMAGE_FORMAT_TABLE . '
  WHERE image_id = ' . $row['id'] . '
;';
$formats = query2array($query);

if ($formats !== []) {
    $format_strings = [];

    foreach ($formats as $format) {
        $format_strings[] = sprintf('%s (%.2fMB)', $format['ext'], $format['filesize'] / 1024);
    }

    $intro_vars['formats'] = l10n('Formats: %s', implode(', ', $format_strings));
}

$template->assign('INTRO', $intro_vars);

if (in_array(get_extension($row['path']), $conf['picture_ext'])) {
    $template->assign('U_COI', get_root_url() . 'admin.php?page=picture_coi&amp;image_id=' . $_GET['image_id']);
}

// image level options
$selected_level = $_POST['level'] ?? $row['level'];
$template->assign(
    [
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => [$selected_level],
    ]
);

// categories
$query = '
SELECT category_id, uppercats, dir
  FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic
    INNER JOIN ' . CATEGORIES_TABLE . ' AS c
      ON c.id = ic.category_id
  WHERE image_id = ' . $_GET['image_id'] . '
;';
$result = pwg_query($query);

$related_categories = [];
$related_categories_ids = [];

while ($row = pwg_db_fetch_assoc($result)) {
    $name =
      get_cat_display_name_cache(
          $row['uppercats'],
          get_root_url() . 'admin.php?page=album-'
      );

    if ($row['category_id'] == $storage_category_id) {
        $template->assign('STORAGE_CATEGORY', $name);
    }

    $related_categories[$row['category_id']] = [
        'name' => $name,
        'unlinkable' => $row['category_id'] != $storage_category_id,
    ];
    $related_categories_ids[] = $row['category_id'];
}

$template->assign('related_categories', $related_categories);
$template->assign('related_categories_ids', $related_categories_ids);

// jump to link
//
// 1. find all linked categories that are reachable for the current user.
// 2. if a category is available in the URL, use it if reachable
// 3. if URL category not available or reachable, use the first reachable
//    linked category
// 4. if no category reachable, no jumpto link
// 5. if level is too high for current user, no jumpto link

$query = '
SELECT category_id
  FROM ' . IMAGE_CATEGORY_TABLE . '
  WHERE image_id = ' . $_GET['image_id'] . '
;';

$authorizeds = array_diff(
    query2array($query, null, 'category_id'),
    explode(
        ',',
        calculate_permissions($user['id'], $user['status'])
    )
);

if (isset($_GET['cat_id'])
    && in_array($_GET['cat_id'], $authorizeds)) {
    $url_img = make_picture_url(
        [
            'image_id' => $_GET['image_id'],
            'image_file' => $image_file,
            'category' => $cache['cat_names'][$_GET['cat_id']],
        ]
    );
} else {
    foreach ($authorizeds as $category) {
        $url_img = make_picture_url(
            [
                'image_id' => $_GET['image_id'],
                'image_file' => $image_file,
                'category' => $cache['cat_names'][$category],
            ]
        );
        break;
    }
}

if (isset($url_img) && $user['level'] >= $page['image']['level']) {
    $template->assign('U_JUMPTO', $url_img);
}

// associate to albums
$query = '
SELECT id
  FROM ' . CATEGORIES_TABLE . '
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = category_id
  WHERE image_id = ' . $_GET['image_id'] . '
;';
$associated_albums = query2array($query, null, 'id');

$template->assign([
    'associated_albums' => $associated_albums,
    'represented_albums' => $represented_albums,
    'STORAGE_ALBUM' => $storage_category_id,
    'CACHE_KEYS' => get_admin_client_cache_keys(['tags', 'categories']),
    'PWG_TOKEN' => get_pwg_token(),
]);

trigger_notify('loc_end_picture_modify');

//----------------------------------------------------------- sending html code

$template->smarty->registerPlugin(
    'modifier',
    'url_is_remote',
    'url_is_remote'
);
$template->assign_var_from_handle('ADMIN_CONTENT', 'picture_modify');
