<?php
/*
Plugin Name: Admin Tools
Version: 14.5.0
Description: Do some admin task from the public pages
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=720
Author: Piwigo team
Author URI: http://piwigo.org
Has Settings: webmaster
*/

use Piwigo\inc\functions;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_url;
use Piwigo\plugins\AdminTools\inc\MultiView;

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('ADMINTOOLS_ID',       basename(dirname(__FILE__)));
define('ADMINTOOLS_PATH' ,    PHPWG_PLUGINS_PATH . ADMINTOOLS_ID . '/');
define('ADMINTOOLS_ADMIN',    functions_url::get_root_url() . 'admin.php?page=plugin-' . ADMINTOOLS_ID);

include_once(ADMINTOOLS_PATH . 'inc/events.php');


global $MultiView;
$MultiView = new MultiView();

functions_plugins::add_event_handler('init', 'admintools_init');

functions_plugins::add_event_handler('user_init', array(&$MultiView, 'user_init'));
functions_plugins::add_event_handler('init', array(&$MultiView, 'init'));

functions_plugins::add_event_handler('ws_add_methods', array('Piwigo\plugins\AdminTools\inc\MultiView', 'register_ws'));
functions_plugins::add_event_handler('delete_user', array('Piwigo\plugins\AdminTools\inc\MultiView', 'invalidate_cache'));
functions_plugins::add_event_handler('register_user', array('Piwigo\plugins\AdminTools\inc\MultiView', 'invalidate_cache'));

if (!defined('IN_ADMIN'))
{
  functions_plugins::add_event_handler('loc_after_page_header', 'admintools_add_public_controller');
  functions_plugins::add_event_handler('loc_begin_picture', 'admintools_save_picture');
  functions_plugins::add_event_handler('loc_begin_index', 'admintools_save_category');
}
else
{
  functions_plugins::add_event_handler('loc_begin_page_header', 'admintools_add_admin_controller_setprefilter');
  functions_plugins::add_event_handler('loc_after_page_header', 'admintools_add_admin_controller');
}


function admintools_init()
{
  global $conf;
  $conf['AdminTools'] = functions::safe_unserialize($conf['AdminTools']);

  functions::load_language('plugin.lang', ADMINTOOLS_PATH);
}
