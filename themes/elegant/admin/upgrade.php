<?php

use Piwigo\inc\functions;

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $conf;

if (! isset($conf['elegant'])) {
    $config = [
        'p_main_menu' => 'on', //on - off - disabled
        'p_pict_descr' => 'on', //on - off - disabled
        'p_pict_comment' => 'off', //on - off - disabled
    ];

    functions::conf_update_param('elegant', $config, true);
} elseif (count(functions::safe_unserialize($conf['elegant'])) != 3) {
    $conff = functions::safe_unserialize($conf['elegant']);
    $config = [
        'p_main_menu' => (isset($conff['p_main_menu'])) ? $conff['p_main_menu'] : 'on',
        'p_pict_descr' => (isset($conff['p_pict_descr'])) ? $conff['p_pict_descr'] : 'on',
        'p_pict_comment' => (isset($conff['p_pict_comment'])) ? $conff['p_pict_comment'] : 'off',
    ];

    functions::conf_update_param('elegant', $config, true);
}
