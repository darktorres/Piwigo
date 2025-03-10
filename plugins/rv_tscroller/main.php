<?php /*
Plugin Name: RV Thumb Scroller
Version: 12.a
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=493
Description: Infinite scroll - loads thumbnails on index page as you scroll down the page
Author: rvelices
Author URI: http://www.modusoptimus.com
Has Settings: false
*/

use Piwigo\inc\functions_plugins;

define('RVTS_VERSION', '12.a');

functions_plugins::add_event_handler('loc_end_section_init', array('Piwigo\plugins\rv_tscroller\RVTS','on_end_section_init'));
?>