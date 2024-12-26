<?php

declare(strict_types=1);

// Need upgrade?
global $conf;
require PHPWG_THEMES_PATH . 'elegant/admin/upgrade.inc.php';

load_language('theme.lang', PHPWG_THEMES_PATH . 'elegant/');

$config_send = [];

if (isset($_POST['submit_elegant'])) {
    $config_send['p_main_menu'] = (isset($_POST['p_main_menu']) && ! empty($_POST['p_main_menu'])) ? $_POST['p_main_menu'] : 'on';
    $config_send['p_pict_descr'] = (isset($_POST['p_pict_descr']) && ! empty($_POST['p_pict_descr'])) ? $_POST['p_pict_descr'] : 'on';
    $config_send['p_pict_comment'] = (isset($_POST['p_pict_comment']) && ! empty($_POST['p_pict_comment'])) ? $_POST['p_pict_comment'] : 'off';

    conf_update_param('elegant', $config_send, true);

    $page['infos'][] = l10n('Information data registered in database');
}

$template->set_filenames([
    'theme_admin_content' => __DIR__ . '/admin.tpl',
]);

$template->assign('options', $conf['elegant']);

$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
