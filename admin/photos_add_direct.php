<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\inc\DerivativeImage;
use Piwigo\inc\SrcImage;
use function Piwigo\admin\inc\get_image_infos;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\dbLayer\mass_inserts;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\l10n;
use function Piwigo\inc\redirect;
use function Piwigo\inc\trigger_notify;
use function Piwigo\inc\userprefs_get_param;
use const Piwigo\inc\IMG_SQUARE;
use const Piwigo\inc\PATTERN_ID;
use const Piwigo\inc\PHPWG_URL;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHOTOS_ADD_BASE_URL')) {
    die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// |                        batch management request                       |
// +-----------------------------------------------------------------------+

if (isset($_GET['batch'])) {
    check_input_parameter('batch', $_GET, false, '/^\d+(,\d+)*$/');

    $query = '
DELETE FROM ' . CADDIE_TABLE . '
  WHERE user_id = ' . $user['id'] . '
;';
    pwg_query($query);

    $inserts = [];
    foreach (explode(',', (string) $_GET['batch']) as $image_id) {
        $inserts[] = [
            'user_id' => $user['id'],
            'element_id' => $image_id,
        ];
    }

    mass_inserts(
        CADDIE_TABLE,
        array_keys($inserts[0]),
        $inserts
    );

    redirect(get_root_url() . 'admin.php?page=batch_manager&filter=prefilter-caddie');
}

if (userprefs_get_param('promote-mobile-apps', true)) {
    $query = '
SELECT registration_date 
  FROM ' . USER_INFOS_TABLE . '
  WHERE registration_date IS NOT NULL  
  ORDER BY user_id ASC
  LIMIT 1
;';
    [$register_date] = pwg_db_fetch_row(pwg_query($query));

    $query = '
SELECT COUNT(*)
  FROM ' . CATEGORIES_TABLE . '
;';
    [$nb_cats] = pwg_db_fetch_row(pwg_query($query));

    $query = '
SELECT COUNT(*)
  FROM ' . IMAGES_TABLE . '
;';
    [$nb_images] = pwg_db_fetch_row(pwg_query($query));

    $uagent_obj = new \uagent_info();
    // To see the mobile app promote, the account must have 2 weeks ancient, 3 albums created and 30 photos uploaded
    $template->assign(
        'PROMOTE_MOBILE_APPS',
        (! $uagent_obj->DetectIos() && strtotime((string) $register_date) < strtotime(
            '2 weeks ago'
        ) && $nb_cats >= 3 && $nb_images >= 30)
    );
} else {
    $template->assign('PROMOTE_MOBILE_APPS', false);
}

$template->assign('PHPWG_URL', PHPWG_URL);

// +-----------------------------------------------------------------------+
// |                             Formats Mode                              |
// +-----------------------------------------------------------------------+

$display_formats = $conf['enable_formats'] && isset($_GET['formats']);

$have_formats_original = false;
$formats_original_info = [];

// If URL parameter isn't empty
if ($display_formats && $_GET['formats']) {
    check_input_parameter('formats', $_GET, false, PATTERN_ID);

    $formats_original_info = get_image_infos($_GET['formats']);
    if ($formats_original_info) {
        $src_image = new SrcImage($formats_original_info);

        $formats_original_info['src'] = DerivativeImage::url(IMG_SQUARE, $src_image);

        // Fetch actual formats
        $query = '
SELECT *
  FROM ' . IMAGE_FORMAT_TABLE . '
  WHERE image_id = ' . $formats_original_info['id'] . '
;';
        $formats = query2array($query);

        if ($formats !== []) {
            $format_strings = [];

            foreach ($formats as $format) {
                $format_strings[] = sprintf('%s (%.2fMB)', $format['ext'], $format['filesize'] / 1024);
            }

            $formats_original_info['formats'] = l10n('Formats: %s', implode(', ', $format_strings));
        }

        $extTab = explode('.', (string) $formats_original_info['file']);

        $formats_original_info['ext'] = l10n('%s file type', strtoupper(end($extTab)));

        $formats_original_info['u_edit'] = get_root_url() . 'admin.php?page=photo-' . $formats_original_info['id'];

        $have_formats_original = true;
    } else {
        $page['errors'][] = l10n("The original picture selected dosen't exists.");
    }

}

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/inc/photos_add_direct_prepare.inc.php');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

trigger_notify('loc_end_photo_add_direct');

$template->assign([
    'ENABLE_FORMATS' => $conf['enable_formats'],
    'DISPLAY_FORMATS' => $display_formats,
    'HAVE_FORMATS_ORIGINAL' => $have_formats_original,
    'FORMATS_ORIGINAL_INFO' => $formats_original_info,
    'SWITCH_MODE_URL' => get_root_url() . 'admin.php?page=photos_add' . ($display_formats ? '' : '&formats'),
    'format_ext' => implode(',', $conf['format_ext']),
    'str_format_ext' => implode(', ', $conf['format_ext']),
]);

$template->assign_var_from_handle('ADMIN_CONTENT', 'photos_add');
