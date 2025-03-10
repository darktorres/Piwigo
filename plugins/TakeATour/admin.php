<?php

use Piwigo\admin\inc\tabsheet;
use Piwigo\inc\functions;
use Piwigo\inc\functions_url;

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $template, $conf, $user, $page;

functions::load_language('plugin.lang', PHPWG_PLUGINS_PATH . 'TakeATour/', [
    'force_fallback' => 'en_UK',
]);

$page['tab'] = 'list';

$tabsheet = new tabsheet();
$tabsheet->add('list', '<span class="icon-menu"></span>' . 'Take a Tour', functions_url::get_root_url() . 'admin.php?page=plugin-TakeATour');
$tabsheet->select($page['tab']);
$tabsheet->assign();

$tat_28url = 'http://';
if (substr($user['language'], 0, 2) == 'fr') {
    $tat_28url .= 'fr.';
}

$tat_28url .= 'piwigo.org/releases/2.8.0';

$tat_29url = 'http://';
if (substr($user['language'], 0, 2) == 'fr') {
    $tat_28url .= 'fr.';
}

$tat_29url .= 'piwigo.org/releases/2.9.0';

$template->assign(
    [
        'F_ACTION' => functions_url::get_root_url() . 'admin.php',
        'pwg_token' => functions::get_pwg_token(),
    ]
);

$template->func_combine_css(
    [
        'path' => 'plugins/TakeATour/css/admin.css',
    ]
);

$tours = [
    'first_contact',
    'edit_photos',
    'manage_albums',
    'config',
    'plugins',
    'privacy',
];

$version_tour = str_replace('.', '_', functions::get_branch_from_version(PHPWG_VERSION)) . '_0';

if (file_exists(PHPWG_PLUGINS_PATH . 'TakeATour/tours/' . $version_tour . '/config.php')) {
    $tours[] = $version_tour;
}

if (isset($conf['TakeATour_tour_ignored'])) {
    if (! is_array($conf['TakeATour_tour_ignored'])) {
        $conf['TakeATour_tour_ignored'] = [$conf['TakeATour_tour_ignored']];
    }

    $tours = array_diff($tours, $conf['TakeATour_tour_ignored']);
}

$tpl_tours = [];

foreach ($tours as $tour_id) {
    $tour = [
        'id' => $tour_id,
        'name' => functions::l10n($tour_id . '_name'),
        'desc' => functions::l10n($tour_id . '_descrp'),
    ];

    switch ($tour_id) {
        case 'first_contact':
            $tour['name'] = functions::l10n('First Contact');
            break;
        case 'privacy':
            $tour['name'] = functions::l10n('Privacy');
            break;
        case '2_9_0':
            $tour['desc'] = functions::l10n($tour_id . '_descrp', $tat_29url);
            break;
        case '2_8_0':
            $tour['name'] = functions::l10n('2.8 Tour');
            $tour['desc'] = functions::l10n($tour_id . '_descrp', $tat_28url);
            break;
        case '2_7_0':
            $tour['name'] = functions::l10n('2.7 Tour');
            break;
    }

    $tpl_tours[] = $tour;
}

$template->assign('tours', $tpl_tours);
$template->set_filename('plugin_admin_content', dirname(__FILE__) . '/tpl/admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
