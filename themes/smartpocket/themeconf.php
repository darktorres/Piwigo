<?php
/*
Theme Name: Smart Pocket
Version: 14.5.0
Description: Mobile theme.
Theme URI: https://piwigo.org/ext/extension_view.php?eid=599
Author: P@t
Author URI: http://piwigo.org
*/

use Piwigo\inc\derivative_std_params;
use Piwigo\inc\functions;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\ImageStdParams;
use Piwigo\themes\smartpocket\SPThumbPicker;

$themeconf = array(
  'mobile' => true,
);

// Need upgrade?
global $conf;
include(PHPWG_THEMES_PATH.'smartpocket/admin/upgrade.php');

functions::load_language('theme.lang', PHPWG_THEMES_PATH.'smartpocket/');


// Redirect if page is not compatible with mobile theme
/*if (!in_array(\Piwigo\inc\functions::script_basename(), array('index', 'register', 'profile', 'identification', 'ws', 'admin')))
  \Piwigo\inc\functions::redirect(\Piwigo\inc\functions_url::duplicate_index_url());
*/

// avoid trying to load slideshow.tpl which does not exist in SmartPocket theme
if (isset($_GET['slideshow']))
{
  unset($_GET['slideshow']);
}

//Retrive all pictures on thumbnails page
functions_plugins::add_event_handler('loc_index_thumbnails_selection', '\Piwigo\inc\functions::sp_select_all_thumbnails');

// Retrive all categories on thumbnails page
functions_plugins::add_event_handler('loc_end_index_category_thumbnails', '\Piwigo\inc\functions::sp_select_all_categories');

// Get better derive parameters for screen size
$type = derivative_std_params::IMG_LARGE;
if (!empty($_COOKIE['screen_size']))
{
  $screen_size = explode('x', $_COOKIE['screen_size']);
  foreach (ImageStdParams::get_all_type_map() as $type => $map)
  {
    if (max($map->sizing->ideal_size) >= max($screen_size) and min($map->sizing->ideal_size) >= min($screen_size))
      break;
  }
}

$this->assign('picture_derivative_params', ImageStdParams::get_by_type($type));
$this->assign('thumbnail_derivative_params', ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE));

functions_plugins::add_event_handler('loc_end_section_init', '\Piwigo\inc\functions::sp_end_section_init');

//------------------------------------------------------------- mobile version & theme config
functions_plugins::add_event_handler('init', '\Piwigo\inc\functions::mobile_link');

if ( !function_exists( 'add_menu_on_public_pages' ) ) { 
  if ( defined('IN_ADMIN') and IN_ADMIN ) return false; 
  functions_plugins::add_event_handler('loc_after_page_header', 'add_menu_on_public_pages', 20);

  function  add_menu_on_public_pages() { 
    if ( function_exists( 'initialize_menu') ) return false; # The current page has already the menu  
    global $template, $page, $conf; 
    if ( isset($page['body_id']) and $page['body_id']=="thePicturePage" ) 
    {	 	 
      $template->set_filenames(array( 
            'add_menu_on_public_pages' => dirname(__FILE__) . '/template/add_menu_on_public_pages.tpl', 
      )); 
      include_once(PHPWG_ROOT_PATH.'inc/menubar.php'); 
      $template->parse('add_menu_on_public_pages');
    }
     
     
  } 
} 


?>
