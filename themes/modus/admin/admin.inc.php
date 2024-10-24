<?php

declare(strict_types=1);

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

global $template;

require_once dirname(__FILE__, 2) . '/functions.inc.php';
require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$default_conf = modus_get_default_config();

load_language('theme.lang', __DIR__ . '/../');

$my_conf = $conf['modus_theme'];
if (! isset($my_conf)) {
    $my_conf = $default_conf;
} elseif (! is_array($my_conf)) {
    $my_conf = unserialize($my_conf);
    $my_conf = array_merge($default_conf, $my_conf);
}

$text_values = ['skin', 'album_thumb_size', 'index_photo_deriv', 'index_photo_deriv_hdpi'];
$bool_values = ['display_page_banner'];

// *************** POST management ********************
if (isset($_POST[$text_values[0]])) {
    foreach ($text_values as $k) {
        $my_conf[$k] = stripslashes((string) $_POST[$k]);
    }

    foreach ($bool_values as $k) {
        $my_conf[$k] = isset($_POST[$k]);
    }

    if (! isset($_POST['use_album_square_thumbs'])) {
        $my_conf['album_thumb_size'] = 0;
    }

    // int/double
    $my_conf['album_thumb_size'] = max(0, $my_conf['album_thumb_size']);
    $my_conf = array_intersect_key($my_conf, $default_conf);
    conf_update_param('modus_theme', addslashes(serialize($my_conf)));

    global $page;
    $page['infos'][] = l10n('Information data registered in database');
}

// *************** tabs ********************

$tabs = [
    [
        'code' => 'config',
        'label' => l10n('Configuration'),
    ],
];

$tab_codes = array_map(
    fn (array $a): string => $a['code'],
    $tabs
);

$page['tab'] = isset($_GET['tab']) && in_array($_GET['tab'], $tab_codes) ? $_GET['tab'] : $tabs[0]['code'];

$tabsheet = new tabsheet();
foreach ($tabs as $tab) {
    $tabsheet->add(
        $tab['code'],
        $tab['label'],
        'admin.php?page=theme&amp;theme=modus'
    );
}

$tabsheet->select($page['tab']);
$tabsheet->assign();

// *************** template init ********************

foreach ($text_values as $k) {
    $template->assign(strtoupper($k), $my_conf[$k]);
}

foreach ($bool_values as $k) {
    $template->assign(strtoupper($k), $my_conf[$k]);
}

// we don't use square thumbs if the thumb size is 0
$template->assign('use_album_square_thumbs', $my_conf['album_thumb_size'] != 0);

if ($my_conf['album_thumb_size'] == 0) {
    $template->assign('ALBUM_THUMB_SIZE', 250);
}

$available_derivatives = [];
foreach (array_keys(ImageStdParams::get_defined_type_map()) as $type) {
    $available_derivatives[$type] = l10n($type);
}

$available_skins = [];
$skin_dir = dirname(__FILE__, 2) . '/skins/';
$skin_suffix = '.inc.php';
foreach (glob($skin_dir . '*' . $skin_suffix) as $file) {
    $skin = substr($file, strlen($skin_dir), -strlen($skin_suffix));
    $available_skins[$skin] = ucwords(str_replace('_', ' ', $skin));
}

$template->assign([
    'available_derivatives' => $available_derivatives,
    'available_skins' => $available_skins,
]);

$template->set_filename('modus_content', __DIR__ . '/modus_admin.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'modus_content');
