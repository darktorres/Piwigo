<?php

declare(strict_types=1);

/*
Plugin Name: gdThumb
Version: 1.0.26
Description: Apply Masonry style to album or image thumbs
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=771
Author: Serge Dosyukov
Author URI: http://blog.dragonsoft.us
Has Settings: true
*/
// Original work by P@t - GTHumb+

global $conf;

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

if (mobile_theme()) {
    return;
}

// +-----------------------------------------------------------------------+
// | Plugin constants                                               |
// +-----------------------------------------------------------------------+
const GDTHUMB_VERSION = '1.0.26';
define('GDTHUMB_ID', basename(__DIR__));
const GDTHUMB_PATH = PHPWG_PLUGINS_PATH . GDTHUMB_ID . '/';
if (! defined('GDTHEME_PATH')):
    define('GDTHEME_PATH', PHPWG_THEMES_PATH . 'greydragon/');
endif;

if (! isset($conf['gdThumb'])):
    include(__DIR__ . '/config_default.inc.php');
    conf_update_param('gdThumb', $config_default);
    load_conf_from_db();
endif;

// RV Thumbnails Scroller
if (isset($_GET['rvts'])):
    $conf['gdThumb']['big_thumb'] = false;
    add_event_handler('loc_end_index_thumbnails', 'GDThumb_process_thumb');
endif;

add_event_handler('init', 'GDThumb_init');
add_event_handler('loc_begin_index', 'GDThumb_index', 60);
// add_event_handler('loc_end_index_category_thumbnails', 'GDThumb_process_category');
add_event_handler(
    'get_admin_plugin_menu_links',
    'GDThumb_admin_menu'
);
add_event_handler('loc_end_index', 'GDThumb_remove_thumb_size');

function GDThumb_init(): void
{
    global $conf, $user, $page, $stripped;

    $confTemp = $conf['gdThumb'];
    $user['nb_image_page'] = $confTemp['nb_image_page'];
    $page['nb_image_page'] = $confTemp['nb_image_page'];
    $stripped['maxThumb'] = $confTemp['nb_image_page'];
}

function GDThumb_index(): void
{
    global $template;

    $template->smarty->registerPlugin('function', 'media_type', 'GDThumb_media_type');
    $template->set_prefilter('index', 'GDThumb_prefilter');

    add_event_handler('loc_end_index_thumbnails', 'GDThumb_process_thumb');
}

function GDThumb_endsWith($needles, $haystack): bool
{
    if(! empty($needles) && ! empty($haystack)):
        $arr_needles = explode(',', (string) $needles);
    endif;
    return false;
}

function GDThumb_media_type($params, $smarty): string
{
    if(empty($params['file'])) {
        return 'image';
    }

    $file = $params['file'];
    if (GDThumb_endsWith('webm,webmv,ogv,m4v,flv,mp4', $file)) {
        return 'video';
    }
    if (GDThumb_endsWith('mp3,ogg,oga,m4a,webma,fla,wav', $file)) {
        return 'music';
    }
    if (GDThumb_endsWith('pdf', $file)) {
        return 'pdf';
    }
    if (GDThumb_endsWith('doc,docx,odt', $file)) {
        return 'doc';
    }
    if (GDThumb_endsWith('xls,xlsx,ods', $file)) {
        return 'xls';
    }
    if (GDThumb_endsWith('ppt,pptx,odp', $file)) {
        return 'ppt';
    }

    return 'image';
}

function GDThumb_process_thumb($tpl_vars, $pictures): mixed
{
    global $template, $conf;
    $confTemp = $conf['gdThumb'];
    $confTemp['GDTHUMB_ROOT'] = 'plugins/' . GDTHUMB_ID;
    $confTemp['big_thumb_noinpw'] = (isset($confTemp['big_thumb_noinpw']) && ($confTemp['big_thumb_noinpw'])) ? 1 : 0;
    if ($confTemp['normalize_title'] == '1'):
        $confTemp['normalize_title'] = 'on';
    endif;

    $template->set_filename('index_thumbnails', __DIR__ . '/template/gdthumb_thumb.tpl');
    $template->assign('GDThumb', $confTemp);
    if (($confTemp['method'] == 'slide') || ($confTemp['method'] == 'square')):
        $template->assign('GDThumb_derivative_params', ImageStdParams::get_custom($confTemp['height'], 9999));
    else:
        $template->assign('GDThumb_derivative_params', ImageStdParams::get_custom(9999, $confTemp['height']));
    endif;

    if ($confTemp['big_thumb'] && ! empty($tpl_vars[0])):
        if (($confTemp['method'] == 'slide') || ($confTemp['method'] == 'square')):
            $derivative_params = ImageStdParams::get_custom(2 * $confTemp['height'] + $confTemp['margin'], 9999);
        else:
            $derivative_params = ImageStdParams::get_custom(9999, 2 * $confTemp['height'] + $confTemp['margin']);
        endif;
    $template->assign('GDThumb_big', new DerivativeImage($derivative_params, $tpl_vars[0]['src_image']));
    endif;

    return $tpl_vars;
}

function GDThumb_process_category($tpl_vars): mixed
{

    global $template, $conf;
    $confTemp = $conf['gdThumb'];
    $confTemp['GDTHUMB_ROOT'] = 'plugins/' . GDTHUMB_ID;
    $confTemp['big_thumb_noinpw'] = isset($confTemp['big_thumb_noinpw']) ? 1 : 0;

    $template->set_filename('index_category_thumbnails', __DIR__ . '/template/gdthumb_cat.tpl');
    $template->assign('GDThumb', $confTemp);
    if (($confTemp['method'] == 'slide') || ($confTemp['method'] == 'square')):
        $template->assign('GDThumb_derivative_params', ImageStdParams::get_custom($confTemp['height'], 9999));
    else:
        $template->assign('GDThumb_derivative_params', ImageStdParams::get_custom(9999, $confTemp['height']));
    endif;

    if ($confTemp['big_thumb'] && ! empty($tpl_vars[0])):
        $id = $tpl_vars[0]['representative_picture_id'];
        if (($id) && ($rep = $tpl_vars[0]['representative'])):
            if (($confTemp['method'] == 'slide') || ($confTemp['method'] == 'square')):
                $derivative_params = ImageStdParams::get_custom(2 * $confTemp['height'] + $confTemp['margin'], 9999);
            else:
                $derivative_params = ImageStdParams::get_custom(9999, 2 * $confTemp['height'] + $confTemp['margin']);
            endif;
        $template->assign('GDThumb_big', new DerivativeImage($derivative_params, $rep['src_image']));
    endif;
    endif;

    return $tpl_vars;
}

function GDThumb_prefilter($content): array|string|null
{
    $pattern = '#\<div.*?id\="thumbnails".*?\>\{\$THUMBNAILS\}\</div\>#';
    $replacement = '<ul id="thumbnails">{$THUMBNAILS}</ul>';

    return preg_replace($pattern, $replacement, $content);
}

function GDThumb_admin_menu($menu): mixed
{
    $menu[] = [
        'NAME' => 'gdThumb',
        'URL' => get_root_url() . 'admin.php?page=plugin-' . basename(__DIR__),
    ];
    return $menu;
}

function GDThumb_remove_thumb_size(): void
{
    global $template;
    $template->clear_assign('image_derivatives');
}
