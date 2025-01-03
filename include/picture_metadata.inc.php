<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * This file is included by the picture page to manage picture metadata
 */

require_once PHPWG_ROOT_PATH . 'include/functions_metadata.inc.php';
if ($conf['show_exif'] && function_exists('exif_read_data')) {
    $exif_mapping = [];
    foreach ($conf['show_exif_fields'] as $field) {
        $exif_mapping[$field] = $field;
    }

    $exif = get_exif_data($picture['current']['src_image']->get_path(), $exif_mapping);

    if ($exif !== []) {
        $tpl_meta = [
            'TITLE' => l10n('EXIF Metadata'),
            'lines' => [],
        ];

        foreach ($conf['show_exif_fields'] as $field) {
            if (! str_contains((string) $field, ';')) {
                // template cannot deal with an array as value, we skip it
                if (isset($exif[$field]) && ! is_array($exif[$field])) {
                    $key = $field;
                    if (isset($lang['exif_field_' . $field])) {
                        $key = $lang['exif_field_' . $field];
                    }

                    $tpl_meta['lines'][$key] = $exif[$field];
                }
            } else {
                $tokens = explode(';', (string) $field);
                // template cannot deal with an array as value, we skip it
                if (isset($exif[$field]) && ! is_array($exif[$field])) {
                    $key = $tokens[1];
                    if (isset($lang['exif_field_' . $key])) {
                        $key = $lang['exif_field_' . $key];
                    }

                    $tpl_meta['lines'][$key] = $exif[$field];
                }
            }
        }

        $template->append('metadata', $tpl_meta);
    }
}

if ($conf['show_iptc']) {
    $iptc = get_iptc_data($picture['current']['src_image']->get_path(), $conf['show_iptc_mapping'], ', ');

    if ($iptc !== []) {
        $tpl_meta = [
            'TITLE' => l10n('IPTC Metadata'),
            'lines' => [],
        ];

        foreach ($iptc as $field => $value) {
            $key = $field;
            if (isset($lang[$field])) {
                $key = $lang[$field];
            }

            $tpl_meta['lines'][$key] = $value;
        }

        $template->append('metadata', $tpl_meta);
    }
}
