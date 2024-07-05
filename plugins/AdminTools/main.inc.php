<?php

use Piwigo\inc\FunctionsPlugins;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\load_language;

/*
Plugin Name: Admin Tools
Version: 14.4.0
Description: Do some admin task from the public pages
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=720
Author: Piwigo team
Author URI: http://piwigo.org
Has Settings: webmaster
*/

defined(
    'PHPWG_ROOT_PATH'
) || die('Hacking attempt!');

define('ADMINTOOLS_ID', basename(__DIR__));
define('ADMINTOOLS_PATH', PHPWG_PLUGINS_PATH . ADMINTOOLS_ID . '/');
define('ADMINTOOLS_ADMIN', get_root_url() . 'admin.php?page=plugin-' . ADMINTOOLS_ID);

require_once(ADMINTOOLS_PATH . 'inc/events.inc.php');
require_once(ADMINTOOLS_PATH . 'inc/MultiView.class.php');

global $MultiView;
$MultiView = new MultiView();

FunctionsPlugins::add_event_handler('init', 'admintools_init');

FunctionsPlugins::add_event_handler('user_init', $MultiView->user_init(...));
FunctionsPlugins::add_event_handler('init', $MultiView->init(...));

FunctionsPlugins::add_event_handler('ws_add_methods', ['MultiView', 'register_ws']);
FunctionsPlugins::add_event_handler('delete_user', ['MultiView', 'invalidate_cache']);
FunctionsPlugins::add_event_handler('FunctionsUser::register_user', ['MultiView', 'invalidate_cache']);

if (! defined('IN_ADMIN')) {
    FunctionsPlugins::add_event_handler('loc_after_page_header', 'admintools_add_public_controller');
    FunctionsPlugins::add_event_handler('loc_begin_picture', 'admintools_save_picture');
    FunctionsPlugins::add_event_handler('loc_begin_index', 'admintools_save_category');
} else {
    FunctionsPlugins::add_event_handler('loc_begin_page_header', 'admintools_add_admin_controller_setprefilter');
    FunctionsPlugins::add_event_handler('loc_after_page_header', 'admintools_add_admin_controller');
}

function admintools_init(): void
{
    global $conf;

    load_language('plugin.lang', ADMINTOOLS_PATH);
}
