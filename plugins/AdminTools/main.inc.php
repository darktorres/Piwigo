<?php

declare(strict_types=1);

/*
Plugin Name: Admin Tools
Version: 14.5.0
Description: Do some admin task from the public pages
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=720
Author: Piwigo team
Author URI: http://piwigo.org
Has Settings: webmaster
*/

defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

define('ADMINTOOLS_ID', basename(__DIR__));
define('ADMINTOOLS_PATH', PHPWG_PLUGINS_PATH . ADMINTOOLS_ID . '/');
define('ADMINTOOLS_ADMIN', get_root_url() . 'admin.php?page=plugin-' . ADMINTOOLS_ID);

require_once ADMINTOOLS_PATH . 'include/events.inc.php';
require_once ADMINTOOLS_PATH . 'include/MultiView.class.php';

global $MultiView;
$MultiView = new MultiView();

add_event_handler('init', admintools_init(...));

add_event_handler('user_init', $MultiView->user_init(...));
add_event_handler('init', $MultiView->init(...));

add_event_handler('ws_add_methods', MultiView::register_ws(...));
add_event_handler('delete_user', MultiView::invalidate_cache(...));
add_event_handler('register_user', MultiView::invalidate_cache(...));

if (! defined('IN_ADMIN')) {
    add_event_handler('loc_after_page_header', admintools_add_public_controller(...));
    add_event_handler('loc_begin_picture', admintools_save_picture(...));
    add_event_handler('loc_begin_index', admintools_save_category(...));
} else {
    add_event_handler('loc_begin_page_header', admintools_add_admin_controller_setprefilter(...));
    add_event_handler('loc_after_page_header', admintools_add_admin_controller(...));
}

function admintools_init(): void
{
    global $conf;

    load_language('plugin.lang', ADMINTOOLS_PATH);
}
