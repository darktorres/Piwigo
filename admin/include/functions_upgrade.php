<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

function check_upgrade(): bool
{
    if (defined('PHPWG_IN_UPGRADE')) {
        return PHPWG_IN_UPGRADE;
    }

    return false;
}

// Deactivate all non-standard plugins
function deactivate_non_standard_plugins(): void
{
    global $page;

    $standard_plugins = [
        'AdminTools',
        'TakeATour',
        'language_switch',
        'LocalFilesEditor',
    ];

    $standard_plugins_ = implode("','", $standard_plugins);
    $query = "SELECT id FROM plugins WHERE state = 'active' AND id NOT IN ('{$standard_plugins_}');";

    $result = pwg_query($query);
    $plugins = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $plugins[] = $row['id'];
    }

    if ($plugins !== []) {
        $plugins_ = implode("','", $plugins);
        $query = "UPDATE plugins SET state = 'inactive' WHERE id IN ('{$plugins_}');";
        pwg_query($query);

        $page['infos'][] = l10n('As a precaution, following plugins have been deactivated. You must check for plugins upgrade before reactiving them:')
                            . '<p><i>' . implode(', ', $plugins) . '</i></p>';
    }
}

// Deactivate all non-standard themes
function deactivate_non_standard_themes(): void
{
    global $page, $conf;

    $standard_themes = [
        'modus',
        'elegant',
        'smartpocket',
    ];

    $standard_themes_ = implode("','", $standard_themes);
    $query = "SELECT id, name FROM themes WHERE id NOT IN ('{$standard_themes_}');";
    $result = pwg_query($query);
    $theme_ids = [];
    $theme_names = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $theme_ids[] = $row['id'];
        $theme_names[] = $row['name'];
    }

    if ($theme_ids !== []) {
        $theme_ids_ = implode("','", $theme_ids);
        $query = "DELETE FROM themes WHERE id IN ('{$theme_ids_}');";
        pwg_query($query);

        $page['infos'][] = l10n('As a precaution, following themes have been deactivated. You must check for themes upgrade before reactiving them:')
                            . '<p><i>' . implode(', ', $theme_names) . '</i></p>';

        // what is the default theme?
        $query = "SELECT theme FROM user_infos WHERE user_id = {$conf['default_user_id']};";
        [$default_theme] = pwg_db_fetch_row(pwg_query($query));

        // if the default theme has just been deactivated, let's set another core theme as default
        if (in_array($default_theme, $theme_ids)) {
            // make sure default Piwigo theme is active
            $query = "SELECT COUNT(*) FROM themes WHERE id = '" . PHPWG_DEFAULT_TEMPLATE . "';";
            [$counter] = pwg_db_fetch_row(pwg_query($query));
            if ($counter < 1) {
                // we need to activate theme first
                include_once(PHPWG_ROOT_PATH . 'admin/include/themes.class.php');
                $themes = new themes();
                $themes->perform_action('activate', PHPWG_DEFAULT_TEMPLATE);
            }

            // then associate it to default user
            $query = "UPDATE user_infos SET theme = '" . PHPWG_DEFAULT_TEMPLATE . "' WHERE user_id = {$conf['default_user_id']};";
            pwg_query($query);
        }
    }
}

// Deactivate all templates
function deactivate_templates(): void
{
    conf_update_param('extents_for_templates', []);
}

// Check access rights
function check_upgrade_access_rights(): void
{
    global $conf, $page, $current_release;

    if (version_compare($current_release, '2.0', '>=') && isset($_COOKIE[session_name()])) {
        // Check if user is already connected as webmaster
        session_start();
        if (! empty($_SESSION['pwg_uid'])) {
            $query = "SELECT status FROM user_infos WHERE user_id = {$_SESSION['pwg_uid']};";
            pwg_query($query);

            $row = pwg_db_fetch_assoc(pwg_query($query));
            if (isset($row['status']) && $row['status'] == 'webmaster') {
                define('PHPWG_IN_UPGRADE', true);
                return;
            }
        }
    }

    if (! isset($_POST['username']) || ! isset($_POST['password'])) {
        return;
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $username = pwg_db_real_escape_string($username);

    if (version_compare($current_release, '2.0', '<')) {
        $username = mb_convert_encoding($username, 'ISO-8859-1');
        $password = mb_convert_encoding($password, 'ISO-8859-1');
    }

    if (version_compare($current_release, '1.5', '<')) {
        $query = "SELECT password, status FROM users WHERE username = '{$username}';";
    } else {
        $query =
        "SELECT u.password, ui.status FROM users AS u INNER JOIN user_infos AS ui ON u.{$conf['user_fields']['id']} = ui.user_id
         WHERE {$conf['user_fields']['username']} = '{$username}';";
    }

    $row = pwg_db_fetch_assoc(pwg_query($query));

    if (! $conf['password_verify']($password, $row['password'])) {
        $page['errors'][] = l10n('Invalid password!');
    } elseif ($row['status'] != 'admin' && $row['status'] != 'webmaster') {
        $page['errors'][] = l10n('You do not have access rights to run upgrade');
    } else {
        define('PHPWG_IN_UPGRADE', true);
    }
}

/**
 * which upgrades are available ?
 */
function get_available_upgrade_ids(): array
{
    // $upgrades_path = PHPWG_ROOT_PATH.'install/db';

    // $available_upgrade_ids = array();

    // if ($contents = opendir($upgrades_path))
    // {
    //   while (($node = readdir($contents)) !== false)
    //   {
    //     if (is_file($upgrades_path.'/'.$node)
    //         and preg_match('/^(.*?)-database\.php$/', $node, $match))
    //     {
    //       $available_upgrade_ids[] = $match[1];
    //     }
    //   }
    // }
    // natcasesort($available_upgrade_ids);

    // return $available_upgrade_ids;
    return [];
}

/**
 * returns true if there are available upgrade files
 */
function check_upgrade_feed(): bool
{
    // retrieve already applied upgrades
    $query = 'SELECT id FROM upgrade;';
    $applied = query2array($query, null, 'id');

    // retrieve existing upgrades
    $existing = get_available_upgrade_ids();

    // which upgrades need to be applied?
    return array_diff($existing, $applied) !== [];
}

function upgrade_db_connect(): void
{
    global $conf;

    try {
        pwg_db_connect(
            $conf['db_host'],
            $conf['db_user'],
            $conf['db_password'],
            $conf['db_base']
        );
        pwg_db_check_version();
    } catch (Exception $exception) {
        my_error(l10n($exception->getMessage()), true);
    }
}
