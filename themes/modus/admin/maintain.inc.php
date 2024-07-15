<?php

declare(strict_types=1);

function theme_activate(
    string $id,
    string $version,
    array &$errors
): void {
    global $conf;

    include_once(dirname(dirname(__FILE__)) . '/functions.inc.php');
    $default_conf = modus_get_default_config();

    $my_conf = $conf['modus_theme'];
    if (empty($my_conf)) {
        $my_conf = $default_conf;
    }

    $my_conf = array_merge($default_conf, $my_conf);
    $my_conf = array_intersect_key($my_conf, $default_conf);
    conf_update_param('modus_theme', addslashes(serialize($my_conf)));
}

function theme_delete(): void
{
    $query = <<<SQL
        DELETE FROM config
        WHERE param = "modus_theme";
        SQL;
    pwg_query($query);
}
