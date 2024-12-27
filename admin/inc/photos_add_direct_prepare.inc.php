<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// | Photo selection                                                       |
// +-----------------------------------------------------------------------+

$template->assign(
    [
        'F_ADD_ACTION' => PHOTOS_ADD_BASE_URL,
        'chunk_size' => $conf['upload_form_chunk_size'],
        'max_file_size' => $conf['upload_form_max_file_size'],
        'ADMIN_PAGE_TITLE' => l10n('Upload Photos'),
    ]
);

//warn the user if the picture will be resized after upload
if ($conf['original_resize']) {
    $template->assign(
        [
            'original_resize_maxwidth' => $conf['original_resize_maxwidth'],
            'original_resize_maxheight' => $conf['original_resize_maxheight'],
        ]
    );
}

$template->assign(
    [
        'form_action' => PHOTOS_ADD_BASE_URL,
        'pwg_token' => get_pwg_token(),
    ]
);

$unique_exts = array_unique(
    array_map(
        strtolower(...),
        $conf['upload_form_all_types'] ? $conf['file_ext'] : $conf['picture_ext']
    )
);

$template->assign(
    [
        'upload_file_types' => implode(', ', $unique_exts),
        'file_exts' => implode(',', $unique_exts),
    ]
);

// +-----------------------------------------------------------------------+
// | Categories                                                            |
// +-----------------------------------------------------------------------+

// we need to know the category in which the last photo was added
$selected_category = [];

if (isset($_GET['album'])) {
    // set the category from get url or ...
    check_input_parameter('album', $_GET, false, PATTERN_ID);

    // test if album really exists
    $query = <<<SQL
        SELECT id, uppercats
        FROM categories
        WHERE id = {$_GET['album']};
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) == 1) {
        $selected_category = [$_GET['album']];

        $cat = pwg_db_fetch_assoc($result);
        $template->assign('ADD_TO_ALBUM', get_cat_display_name_cache($cat['uppercats'], null));
    } else {
        fatal_error('[Hacking attempt] the album id = "' . $_GET['album'] . '" is not valid');
    }
} else {
    // we need to know the category in which the last photo was added
    $query = <<<SQL
        SELECT category_id
        FROM images AS i
        JOIN image_category AS ic ON image_id = i.id
        JOIN categories AS c ON category_id = c.id
        ORDER BY i.id DESC
        LIMIT 1;
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) > 0) {
        $row = pwg_db_fetch_assoc($result);
        $selected_category = [$row['category_id']];
    }
}

// existing album
$template->assign('selected_category', $selected_category);

// how many existing albums?
$query = <<<SQL
    SELECT COUNT(*)
    FROM categories;
    SQL;
[$nb_albums] = pwg_db_fetch_row(pwg_query($query));
// $nb_albums = 0;
$template->assign('NB_ALBUMS', $nb_albums);

// image level options
$selected_level = $_POST['level'] ?? 0;
$template->assign(
    [
        'level_options' => get_privacy_level_options(),
        'level_options_selected' => [$selected_level],
    ]
);

// +-----------------------------------------------------------------------+
// | Setup errors/warnings                                                 |
// +-----------------------------------------------------------------------+

// Errors
$setup_errors = [];

$error_message = ready_for_upload_message();
if ($error_message !== null && $error_message !== '' && $error_message !== '0') {
    $setup_errors[] = $error_message;
}

$template->assign([
    'setup_errors' => $setup_errors,
    'CACHE_KEYS' => get_admin_client_cache_keys(['categories']),
]);

// Warnings
if (isset($_GET['hide_warnings'])) {
    $_SESSION['upload_hide_warnings'] = true;
}

if (! isset($_SESSION['upload_hide_warnings'])) {
    $setup_warnings = [];

    if ($conf['use_exif'] && ! function_exists('exif_read_data')) {
        $setup_warnings[] = l10n('Exif extension not available, admin should disable exif use');
    }

    if (get_ini_size('upload_max_filesize') > get_ini_size('post_max_size')) {
        $setup_warnings[] = l10n(
            'In your php.ini file, the upload_max_filesize (%sB) is bigger than post_max_size (%sB), you should change this setting',
            get_ini_size('upload_max_filesize', false),
            get_ini_size('post_max_size', false)
        );
    }

    if (get_ini_size('upload_max_filesize') < $conf['upload_form_chunk_size'] * 1024) {
        $setup_warnings[] = sprintf(
            'Piwigo setting upload_form_chunk_size (%ukB) should be smaller than PHP configuration setting upload_max_filesize (%ukB)',
            $conf['upload_form_chunk_size'],
            ceil(get_ini_size('upload_max_filesize') / 1024)
        );
    }

    $template->assign(
        [
            'setup_warnings' => $setup_warnings,
            'hide_warnings_link' => PHOTOS_ADD_BASE_URL . '&amp;hide_warnings=1',
        ]
    );
}
