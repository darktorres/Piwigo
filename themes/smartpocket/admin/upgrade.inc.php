<?php

declare(strict_types=1);

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $conf;

if (! isset($conf['smartpocket'])) {
    $config = [
        'loop' => true,
        //true - false
        'autohide' => 5000,
        //5000 - 0
    ];

    conf_update_param('smartpocket', $config, true);
} elseif (count(safe_unserialize($conf['smartpocket'])) != 2) {
    $conff = safe_unserialize($conf['smartpocket']);
    $config = [
        'loop' => (empty($conff['loop'])) ? true : $conff['loop'],
        'autohide' => (empty($conff['autohide'])) ? 5000 : $conff['autohide'],
    ];
    conf_update_param('smartpocket', $config, true);
}
