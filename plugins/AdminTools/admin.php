<?php

use Piwigo\inc\functions;

defined('ADMINTOOLS_PATH') or die('Hacking attempt!');

if (isset($_POST['save_config'])) {
    $conf['AdminTools'] = [
        'default_open' => isset($_POST['default_open']),
        'closed_position' => $_POST['closed_position'],
        'public_quick_edit' => isset($_POST['public_quick_edit']),
    ];

    functions::conf_update_param('AdminTools', $conf['AdminTools']);
    $page['infos'][] = functions::l10n('Information data registered in database');
}

$template->assign([
    'AdminTools' => $conf['AdminTools'],
]);

$template->set_filename('admintools_content', realpath(ADMINTOOLS_PATH . 'template/admin.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'admintools_content');
