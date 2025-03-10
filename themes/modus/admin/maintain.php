<?php

use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;

function theme_activate($id, $version, &$errors)
{
    global $conf;

    include_once(dirname(dirname(__FILE__)) . '/functions.php');
    $default_conf = modus_get_default_config();

    $my_conf = @$conf['modus_theme'];
    $my_conf = @unserialize($my_conf);
    if (empty($my_conf)) {
        $my_conf = $default_conf;
    }

    $my_conf = array_merge($default_conf, $my_conf);
    $my_conf = array_intersect_key($my_conf, $default_conf);
    functions::conf_update_param('modus_theme', addslashes(serialize($my_conf)));
}

function theme_delete()
{
    $query = 'DELETE FROM config WHERE param="modus_theme"';
    functions_mysqli::pwg_query($query);
}
