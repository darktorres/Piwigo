<?php

declare(strict_types=1);

/*
Theme Name: elegant
Version: 14.5.0
Description: Dark background, grayscale.
Theme URI: https://piwigo.org/ext/extension_view.php?eid=685
Author: Piwigo team
Author URI: http://piwigo.org
*/
$themeconf = [
    'name' => 'elegant',
    'parent' => 'default',
    'local_head' => 'local_head.tpl',
];
// Need upgrade?
global $conf;
include(PHPWG_THEMES_PATH . 'elegant/admin/upgrade.inc.php');

add_event_handler('init', 'set_config_values_elegant');
function set_config_values_elegant()
{
    global $conf, $template;
    $config = safe_unserialize($conf['elegant']);
    $template->assign('elegant', $config);
}
