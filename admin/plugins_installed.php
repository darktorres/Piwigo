<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/include/plugins.class.php');

$template->set_filenames([
    'plugins' => 'plugins_installed.tpl',
]);

// should we display details on plugins?
if (isset($_GET['show_details'])) {
    $show_details = $_GET['show_details'] == 1;
    pwg_set_session_var('plugins_show_details', $show_details);
} elseif (pwg_get_session_var('plugins_show_details') != null) {
    $show_details = pwg_get_session_var('plugins_show_details');
} else {
    $show_details = false;
}

$base_url = get_root_url() . 'admin.php?page=' . $page['page'];
$pwg_token = get_pwg_token();
$action_url = $base_url . '&amp;plugin=' . '%s' . '&amp;pwg_token=' . $pwg_token;

$plugins = new plugins();

//--------------------------------------------------------Incompatible Plugins
if (isset($_GET['incompatible_plugins'])) {
    $incompatible_plugins_raw = $plugins->get_incompatible_plugins();

    if ($incompatible_plugins_raw === false) {
        echo json_encode([]);
        exit;
    }

    $incompatible_plugins = [];

    foreach ($incompatible_plugins_raw as $plugin => $version) {
        if ($plugin == '~~expire~~') {
            continue;
        }

        $incompatible_plugins[] = $plugin;

    }

    echo json_encode($incompatible_plugins);
    exit;
}

//--------------------------------------------------------Get the menu with the depreciated version

$plugin_menu_links_deprec = trigger_change('get_admin_plugin_menu_links', []);

$settings_url_for_plugin_deprec = [];

foreach ($plugin_menu_links_deprec as $value) {
    if (preg_match('/^admin\.php\?page=plugin-(.*)$/', (string) $value['URL'], $matches)) {
        $settings_url_for_plugin_deprec[$matches[1]] = $value['URL'];
    } elseif (preg_match('/^.*section=(.*?)[\/&%].*$/', (string) $value['URL'], $matches)) {
        $settings_url_for_plugin_deprec[$matches[1]] = $value['URL'];
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

$plugins->sort_fs_plugins('name');
$merged_extensions = $plugins->get_merged_extensions();
$merged_plugins = false;
$tpl_plugins = [];
$count_types_plugins = [
    'active' => 0,
    'inactive' => 0,
    'missing' => 0,
    'merged' => 0,
];

foreach ($plugins->fs_plugins as $plugin_id => $fs_plugin) {
    if (isset($_SESSION['incompatible_plugins'][$plugin_id]) && $fs_plugin['version'] != $_SESSION['incompatible_plugins'][$plugin_id]) {
        // Incompatible plugins must be reinitilized
        unset($_SESSION['incompatible_plugins']);
    }

    $setting_url = '';
    if (isset($settings_url_for_plugin_deprec[$plugin_id])) { //old version
        $setting_url = $settings_url_for_plugin_deprec[$plugin_id];
    } elseif ($fs_plugin['hasSettings']) { // new version
        $setting_url = 'admin.php?page=plugin-' . $plugin_id;

        if (preg_match('/^piwigo-(videojs|openstreetmap)$/', (string) $plugin_id)) {
            $setting_url = str_replace('piwigo-', 'piwigo_', $setting_url);
        }
    }

    $tpl_plugin = [
        'ID' => $plugin_id,
        'NAME' => $fs_plugin['name'],
        'VISIT_URL' => $fs_plugin['uri'],
        'VERSION' => $fs_plugin['version'],
        'DESC' => $fs_plugin['description'],
        'AUTHOR' => $fs_plugin['author'],
        'AUTHOR_URL' => @$fs_plugin['author uri'],
        'U_ACTION' => sprintf($action_url, $plugin_id),
        'SETTINGS_URL' => $setting_url,
    ];

    if (isset($plugins->db_plugins_by_id[$plugin_id])) {
        $tpl_plugin['STATE'] = $plugins->db_plugins_by_id[$plugin_id]['state'];
    } else {
        $tpl_plugin['STATE'] = 'inactive';
    }

    if (isset($fs_plugin['extension']) && isset($merged_extensions[$fs_plugin['extension']])) {
        // Deactivate manually plugin from database
        $query = "UPDATE plugins SET state = 'inactive' WHERE id = '{$plugin_id}';";
        pwg_query($query);

        $tpl_plugin['STATE'] = 'merged';
        $tpl_plugin['DESC'] = l10n('THIS PLUGIN IS NOW PART OF PIWIGO CORE! DELETE IT NOW.');
        $merged_plugins = true;
    }

    $count_types_plugins[$tpl_plugin['STATE']]++;

    $tpl_plugins[] = $tpl_plugin;
}

$template->append('plugin_states', 'active');
$template->append('plugin_states', 'inactive');

if ($merged_plugins) {
    $template->append('plugin_states', 'merged');
}

$missing_plugin_ids = array_diff(
    array_keys($plugins->db_plugins_by_id),
    array_keys($plugins->fs_plugins)
);

if ($missing_plugin_ids !== []) {
    foreach ($missing_plugin_ids as $plugin_id) {
        $tpl_plugins[] = [
            'NAME' => $plugin_id,
            'ID' => $plugin_id,
            'VERSION' => $plugins->db_plugins_by_id[$plugin_id]['version'],
            'DESC' => l10n('ERROR: THIS PLUGIN IS MISSING BUT IT IS INSTALLED! UNINSTALL IT NOW.'),
            'U_ACTION' => sprintf($action_url, $plugin_id),
            'STATE' => 'missing',
        ];
        $count_types_plugins['missing']++;
    }

    $template->append('plugin_states', 'missing');
}

// sort plugins by state then by name
function cmp(
    array $a,
    array $b
): bool|int {
    $s = [
        'merged' => 0,
        'missing' => 1,
        'active' => 2,
        'inactive' => 3,
    ];

    if ($a['STATE'] == $b['STATE']) {
        return strcasecmp((string) $a['NAME'], (string) $b['NAME']);
    }

    return $s[$a['STATE']] >= $s[$b['STATE']];
}

// Stoped plugin sorting for new plugin manager
// usort($tpl_plugins, cmp(...));

$template->assign(
    [
        'plugins' => $tpl_plugins,
        'count_types_plugins' => $count_types_plugins,
        'PWG_TOKEN' => $pwg_token,
        'base_url' => $base_url,
        'show_details' => $show_details,
        'max_inactive_before_hide' => isset($_GET['show_inactive']) ? 999 : 8,
        'isWebmaster' => (is_webmaster()) ? 1 : 0,
        'ADMIN_PAGE_TITLE' => l10n('Plugins'),
        'view_selector' => userprefs_get_param('plugin-manager-view', 'classic'),
        'CONF_ENABLE_EXTENSIONS_INSTALL' => $conf['enable_extensions_install'],
    ]
);

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugins');
