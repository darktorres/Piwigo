<?php

declare(strict_types=1);

use function Piwigo\inc\pwg_set_session_var;

/*
Theme Name: Bootstrap Darkroom
Version: 2.5.17
Description: A mobile-ready & feature-rich theme based on Boostrap 4, with PhotoSwipe full-screen slideshow, Slick carousel, over 30 color styles and lots of configuration options
Theme URI: http://piwigo.org/ext/extension_view.php?eid=831
Author: Thomas Kuther
Author URI: https://github.com/tkuther/piwigo-bootstrap-darkroom
*/
require_once(PHPWG_THEMES_PATH . 'bootstrap_darkroom/inc/themecontroller.php');
require_once(PHPWG_THEMES_PATH . 'bootstrap_darkroom/inc/config.php');

$themeconf = [
    'name' => 'bootstrap_darkroom',
    'parent' => 'default',
    'load_parent_css' => false,
    'load_parent_local_head' => true,
    'local_head' => 'local_head.tpl',
    'url' => 'https://kuther.net/',
];

//debug
//$conf['template_combine_files'] = false;

// always show metadata initially
pwg_set_session_var('show_metadata', true);

// register video files
$video_ext = ['mp4', 'm4v'];
$conf['file_ext'] = array_merge($conf['file_ext'], $video_ext, array_map('strtoupper', $video_ext));

$controller = new \BootstrapDarkroom\ThemeController();
$controller->init();
