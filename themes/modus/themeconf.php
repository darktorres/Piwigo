<?php

/*
Theme Name: modus
Version: 14.5.0.1
Description: Responsive, horizontal menu, retina aware, no lost space.
Theme URI: https://piwigo.org/ext/extension_view.php?eid=728
Author: rvelices
Author URI: http://www.modusoptimus.com
*/

use Piwigo\inc\derivative_std_params;
use Piwigo\inc\functions;
use Piwigo\inc\functions_cookie;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_session;
use Piwigo\inc\ImageStdParams;

$themeconf = [
    'name' => 'modus',
    'parent' => 'default',
    'colorscheme' => 'dark',
];

define('MODUS_STR_RECENT', "\xe2\x9c\xbd"); //HEAVY TEARDROP-SPOKED ASTERISK
define('MODUS_STR_RECENT_CHILD', "\xe2\x9c\xbb"); //TEARDROP-SPOKED ASTERISK

if (isset($conf['modus_theme']) && ! is_array($conf['modus_theme'])) {
    $conf['modus_theme'] = unserialize($conf['modus_theme']);
}

if (! empty($_GET['skin']) && ! preg_match('/[^a-zA-Z0-9_-]/', $_GET['skin'])) {
    $conf['modus_theme']['skin'] = $_GET['skin'];
}

// we're mainly interested in an override of the colorscheme
include(dirname(__FILE__) . '/skins/' . $conf['modus_theme']['skin'] . '.php');

$this->assign(
    [
        'MODUS_CSS_VERSION' => crc32(implode(',', [
            'a' . @$conf['modus_theme']['skin'],
            @$conf['modus_theme']['album_thumb_size'],
            ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE)->max_width(),
            $conf['index_created_date_icon'],
            $conf['index_posted_date_icon'],
        ])),
        'MODUS_DISPLAY_PAGE_BANNER' => @$conf['modus_theme']['display_page_banner'],
    ]
);

if (file_exists(dirname(__FILE__) . '/skins/' . $conf['modus_theme']['skin'] . '.css')) {
    $this->assign('MODUS_CSS_SKIN', $conf['modus_theme']['skin']);
}

if (! $conf['compiled_template_cache_language']) {
    functions::load_language('theme.lang', dirname(__FILE__) . '/');
    functions::load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, [
        'no_fallback' => true,
        'local' => true,
    ]);
}

if (isset($_COOKIE['caps'])) {
    setcookie('caps', false, 0, functions_cookie::cookie_path());
    functions_session::pwg_set_session_var('caps', explode('x', $_COOKIE['caps']));
    /*file_put_contents(PHPWG_ROOT_PATH.$conf['data_location'].'tmp/modus.log', implode("\t", array(
        date("Y-m-d H:i:s"), $_COOKIE['caps'], $_SERVER['HTTP_USER_AGENT']
        ))."\n", FILE_APPEND);*/
}

if (functions::get_device() == 'mobile') {
    $conf['tag_letters_column_number'] = 1;
} elseif (functions::get_device() == 'tablet') {
    $conf['tag_letters_column_number'] = min($conf['tag_letters_column_number'], 3);
}

$this->smarty->registerFilter('pre', '\Piwigo\inc\functions::modus_smarty_prefilter_wrap');

if (! defined('IN_ADMIN') && defined('RVCDN')) {
    $this->smarty->registerFilter('pre', 'rv_cdn_prefilter');
    functions_plugins::add_event_handler('combined_script', '\Piwigo\inc\functions::rv_cdn_combined_script', EVENT_HANDLER_PRIORITY_NEUTRAL, 2);
}

// Add prefilter to remove fontello loaded by piwigo 14 search,
// this avoids conflicts of loading 2 fontellos
functions_plugins::add_event_handler('loc_begin_index', '\Piwigo\inc\functions::modus_loc_begin_index', 60);

if (defined('RVPT_JQUERY_SRC')) {
    functions_plugins::add_event_handler('loc_begin_page_header', '\Piwigo\inc\functions::modus_loc_begin_page_header');
}

functions_plugins::add_event_handler('combinable_preparse', '\Piwigo\inc\functions::modus_combinable_preparse');

$this->smarty->registerPlugin('function', 'cssResolution', '\Piwigo\inc\functions::modus_css_resolution');
$this->smarty->registerPlugin('function', 'modus_thumbs', '\Piwigo\inc\functions::modus_thumbs');

functions_plugins::add_event_handler('loc_end_index', '\Piwigo\inc\functions::modus_on_end_index');
functions_plugins::add_event_handler('get_index_derivative_params', '\Piwigo\inc\functions::modus_get_index_photo_derivative_params', EVENT_HANDLER_PRIORITY_NEUTRAL + 1);
functions_plugins::add_event_handler('loc_end_index_category_thumbnails', '\Piwigo\inc\functions::modus_index_category_thumbnails');
functions_plugins::add_event_handler('loc_begin_picture', '\Piwigo\inc\functions::modus_loc_begin_picture');
functions_plugins::add_event_handler('render_element_content', '\Piwigo\inc\functions::modus_picture_content', EVENT_HANDLER_PRIORITY_NEUTRAL - 1, 2);
