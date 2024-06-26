<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\inc\DerivativeImage;
use Piwigo\inc\ImageStdParams;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_root_url;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;
use const Piwigo\inc\IMG_SQUARE;
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

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('image_id', $_GET, false, PATTERN_ID);

$query = '
SELECT *
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . $_GET['image_id'] . '
;';
$images = query2array($query);
$image = $images[0];

$query = '
SELECT
    *
  FROM ' . IMAGE_FORMAT_TABLE . '
  WHERE image_id = ' . $_GET['image_id'] . '
;';

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
