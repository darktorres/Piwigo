<?php

declare(strict_types=1);

// Check whether we are indeed included by Piwigo.
if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

// Includes
require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';
require_once PHPWG_THEMES_PATH . 'bootstrap_darkroom/include/config.php';

load_language('theme.lang', PHPWG_THEMES_PATH . 'bootstrap_darkroom/');

// Constants
define('THEME_ID', basename(dirname(__FILE__, 2)));
define('ADMIN_PATH', get_root_url() . 'admin.php?page=theme&theme=' . THEME_ID);
define('TAB_SETTINGS', 'settings');
define('TAB_ABOUT', 'about');

// Get current tab
$page['tab'] = $_GET['tab'] ?? ($page['tab'] = TAB_SETTINGS);
if (! in_array($page['tab'], [TAB_SETTINGS, TAB_ABOUT])) {
    $page['tab'] = TAB_SETTINGS;
}

$themeconfig = new \BootstrapDarkroom\Config();

// Save settings
if ($page['tab'] == TAB_SETTINGS && isset($_POST['boostrap_darkroom_settings'])) {
    $themeconfig->fromPost($_POST);
    $themeconfig->save();
}

// TabSheet
$tabsheet = new tabsheet();
$tabsheet->set_id('bsdark');
$tabsheet->add(TAB_SETTINGS, l10n('Settings'), ADMIN_PATH . '&tab=' . TAB_SETTINGS);
$tabsheet->add(TAB_ABOUT, l10n('About'), ADMIN_PATH . '&tab=' . TAB_ABOUT);
$tabsheet->select($page['tab']);
$tabsheet->assign();

// Fetch the template.
global $template;

// Add our template to the global template
$template->set_filenames(
    [
        'theme_admin_content' => __DIR__ . '/template/' . $page['tab'] . '.tpl',
    ]
);

// Assign the template contents to ADMIN_CONTENT
$template->assign('theme_config', $themeconfig);
$template->assign_var_from_handle('ADMIN_CONTENT', 'theme_admin_content');
