<?php

declare(strict_types=1);

// Need upgrade?
global $conf;
require PHPWG_THEMES_PATH . 'smartpocket/admin/upgrade.inc.php';

load_language('theme.lang', PHPWG_THEMES_PATH . 'smartpocket/');

$config_send = [];

if (isset($_POST['submit_smartpocket'])) {
    $config_send['loop'] = isset($_POST['loop']);
    $config_send['autohide'] = (isset($_POST['autohide']) ? 5000 : 0);

    conf_update_param('smartpocket', $config_send, true);

    array_push($page['infos'], l10n('Information data registered in database'));
}

$template->set_filenames([
    'theme_admin_content' => dirname(__FILE__) . '/admin.tpl',
]);

$template->assign('options', $conf['smartpocket']);

$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
