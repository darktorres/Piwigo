<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('image_id', $_GET, false, PATTERN_ID);

$query = <<<SQL
    SELECT *
    FROM images
    WHERE id = {$_GET['image_id']};
    SQL;
$images = query2array($query);
$image = $images[0];

$query = <<<SQL
    SELECT *
    FROM image_format
    WHERE image_id = {$_GET['image_id']};
    SQL;

$formats = query2array($query);

foreach ($formats as &$format) {
    $format['download_url'] = 'action.php?format=' . $format['format_id'] . '&amp;download';

    $format['label'] = strtoupper((string) $format['ext']);
    $lang_key = 'format ' . strtoupper((string) $format['ext']);
    if (isset($lang[$lang_key])) {
        $format['label'] = $lang[$lang_key];
    }

    $format['filesize'] = round($format['filesize'] / 1024, 2);
}

$template->assign([
    'ADD_FORMATS_URL' => get_root_url() . 'admin.php?page=photos_add&formats=' . $_GET['image_id'],
    'IMG_SQUARE_SRC' => DerivativeImage::url(ImageStdParams::get_by_type(IMG_SQUARE), $image),
    'FORMATS' => $formats,
    'PWG_TOKEN' => get_pwg_token(),
]);

$template->set_filename('picture_formats', 'picture_formats.tpl');

$template->assign_var_from_handle('ADMIN_CONTENT', 'picture_formats');
