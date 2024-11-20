<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// Right after overwriting the previous version files by unzipping in the administration,
// the PHP engine might still have old files in the cache.
// We do not want to use the cache and force reload of all application files.
// Thus, we disable opcache.
if (function_exists('ini_set')) {
    ini_set('opcache.enable', 0);
}

define('PHPWG_ROOT_PATH', './');

// load config file
require PHPWG_ROOT_PATH . 'include/config_default.inc.php';
file_exists(PHPWG_ROOT_PATH . 'local/config/config.inc.php') && require PHPWG_ROOT_PATH . 'local/config/config.inc.php';

$config_file = PHPWG_ROOT_PATH . 'local/config/database.inc.php';
$config_file_contents = file_get_contents($config_file);
if ($config_file_contents === false) {
    die('Cannot load ' . $config_file);
}
$php_end_tag = strrpos($config_file_contents, '?' . '>');
if ($php_end_tag === false) {
    die('Cannot find php end tag in ' . $config_file);
}

require $config_file;

require_once PHPWG_ROOT_PATH . 'include/constants.php';
define('UPGRADES_PATH', PHPWG_ROOT_PATH . 'install/db');

require_once PHPWG_ROOT_PATH . 'include/functions.inc.php';
require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
require_once PHPWG_ROOT_PATH . 'include/template.class.php';

// +-----------------------------------------------------------------------+
// |                              functions                                |
// +-----------------------------------------------------------------------+

/**
 * list all tables in an array
 */
function get_tables(): array
{
    $tables = [];

    $query = <<<SQL
        SHOW TABLES;
        SQL;
    $result = pwg_query($query);

    while ($row = pwg_db_fetch_row($result)) {
        $tables[] = $row[0];
    }

    return $tables;
}

/**
 * list all columns of each given table
 *
 * @return string[][]
 */
function get_columns_of(
    array $tables
): array {
    $columns_of = [];

    foreach ($tables as $table) {
        $query = <<<SQL
            DESC {$table};
            SQL;
        $result = pwg_query($query);

        $columns_of[$table] = [];

        while ($row = pwg_db_fetch_row($result)) {
            $columns_of[$table][] = $row[0];
        }
    }

    return $columns_of;
}

function print_time(
    string $message
): void {
    global $last_time;

    $new_time = get_moment();
    echo '<pre>[' . get_elapsed_time($last_time, $new_time) . ']';
    echo ' ' . $message;
    echo '</pre>';
    flush();
    $last_time = $new_time;
}

// +-----------------------------------------------------------------------+
// |                             playing zone                              |
// +-----------------------------------------------------------------------+

// echo implode('<br>', get_tables());
// echo '<pre>'; print_r(get_columns_of(get_tables())); echo '</pre>';

// foreach (get_available_upgrade_ids() as $upgrade_id)
// {
//   echo $upgrade_id, '<br>';
// }

// +-----------------------------------------------------------------------+
// |                             language                                  |
// +-----------------------------------------------------------------------+
require PHPWG_ROOT_PATH . 'admin/include/languages.class.php';
$languages = new languages('utf-8');
if (isset($_GET['language'])) {
    $language = strip_tags($_GET['language']);

    if (! in_array($language, array_keys($languages->fs_languages))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
} else {
    $language = 'en_UK';
    // Try to get browser language
    foreach ($languages->fs_languages as $language_code => $fs_language) {
        if (substr($language_code, 0, 2) == substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)) {
            $language = $language_code;
            break;
        }
    }
}

if ($language == 'fr_FR') {
    define('PHPWG_DOMAIN', 'fr.piwigo.org');
} elseif ($language == 'it_IT') {
    define('PHPWG_DOMAIN', 'it.piwigo.org');
} elseif ($language == 'de_DE') {
    define('PHPWG_DOMAIN', 'de.piwigo.org');
} elseif ($language == 'es_ES') {
    define('PHPWG_DOMAIN', 'es.piwigo.org');
} elseif ($language == 'pl_PL') {
    define('PHPWG_DOMAIN', 'pl.piwigo.org');
} elseif ($language == 'zh_CN') {
    define('PHPWG_DOMAIN', 'cn.piwigo.org');
} elseif ($language == 'ru_RU') {
    define('PHPWG_DOMAIN', 'ru.piwigo.org');
} elseif ($language == 'nl_NL') {
    define('PHPWG_DOMAIN', 'nl.piwigo.org');
} elseif ($language == 'tr_TR') {
    define('PHPWG_DOMAIN', 'tr.piwigo.org');
} elseif ($language == 'da_DK') {
    define('PHPWG_DOMAIN', 'da.piwigo.org');
} elseif ($language == 'pt_BR') {
    define('PHPWG_DOMAIN', 'br.piwigo.org');
} else {
    define('PHPWG_DOMAIN', 'piwigo.org');
}
define('PHPWG_URL', 'https://' . PHPWG_DOMAIN);

load_language('common.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
    'no_fallback' => true,
]);
load_language('admin.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
    'no_fallback' => true,
]);
load_language('install.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
    'no_fallback' => true,
]);
load_language('upgrade.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
    'no_fallback' => true,
]);

// +-----------------------------------------------------------------------+
// |                          database connection                          |
// +-----------------------------------------------------------------------+
require_once PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php';
require PHPWG_ROOT_PATH . 'include/dblayer/functions_' . $conf['dblayer'] . '.inc.php';

upgrade_db_connect();

list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$template = new Template(PHPWG_ROOT_PATH . 'admin/themes', 'roma');
$template->set_filenames([
    'upgrade' => 'upgrade.tpl',
]);
$template->assign(
    [
        'RELEASE' => PHPWG_VERSION,
        'L_UPGRADE_HELP' => l10n('Need help ? Ask your question on <a href="%s">Piwigo message board</a>.', PHPWG_URL . '/forum'),
    ]
);

// +-----------------------------------------------------------------------+
// | Remote sites are not compatible with Piwigo 2.4+                      |
// +-----------------------------------------------------------------------+

$has_remote_site = false;

$query = <<<SQL
    SELECT galleries_url FROM sites;
    SQL;
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    if (url_is_remote($row['galleries_url'])) {
        $has_remote_site = true;
    }
}

if ($has_remote_site) {
    require_once PHPWG_ROOT_PATH . 'admin/include/updates.class.php';

    $page['errors'] = [];
    $step = 3;
    updates::upgrade_to('2.3.4', $step, false);

    if (! empty($page['errors'])) {
        echo '<ul>';
        foreach ($page['errors'] as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
    }

    exit();
}

// +-----------------------------------------------------------------------+
// |                            upgrade choice                             |
// +-----------------------------------------------------------------------+

$tables = get_tables();
$columns_of = get_columns_of($tables);

// find the current release
if (! in_array('param', $columns_of['config'])) {
    // we're in branch 1.3, important upgrade, isn't it?
    if (in_array('user_category', $tables)) {
        $current_release = '1.3.1';
    } else {
        $current_release = '1.3.0';
    }
} elseif (! in_array('user_cache', $tables)) {
    $current_release = '1.4.0';
} elseif (! in_array('tags', $tables)) {
    $current_release = '1.5.0';
} elseif (! in_array('plugins', $tables)) {
    if (! in_array('auto_login_key', $columns_of['user_infos'])) {
        $current_release = '1.6.0';
    } else {
        $current_release = '1.6.2';
    }
} elseif (! in_array('md5sum', $columns_of['images'])) {
    $current_release = '1.7.0';
} elseif (! in_array('themes', $tables)) {
    $current_release = '2.0.0';
} elseif (! in_array('added_by', $columns_of['images'])) {
    $current_release = '2.1.0';
} elseif (! in_array('rating_score', $columns_of['images'])) {
    $current_release = '2.2.0';
} elseif (! in_array('rotation', $columns_of['images'])) {
    $current_release = '2.3.0';
} elseif (! in_array('website_url', $columns_of['comments'])) {
    $current_release = '2.4.0';
} elseif (! in_array('nb_available_tags', $columns_of['user_cache'])) {
    $current_release = '2.5.0';
} elseif (! in_array('activation_key_expire', $columns_of['user_infos'])) {
    $current_release = '2.6.0';
} elseif (! in_array('auth_key_id', $columns_of['history'])) {
    $current_release = '2.7.0';
} elseif (! in_array('history_id_to', $columns_of['history_summary'])) {
    $current_release = '2.8.0';
} elseif (! in_array('activity', $tables)) {
    $current_release = '2.9.0';
} else {
    // retrieve already applied upgrades
    $query = <<<SQL
        SELECT id
        FROM upgrade;
        SQL;
    $applied_upgrades = query2array($query, null, 'id');

    if (! in_array(159, $applied_upgrades)) {
        $current_release = '2.10.0';
    } elseif (! in_array(162, $applied_upgrades)) {
        $current_release = '11.0.0';
    } elseif (! in_array(164, $applied_upgrades)) {
        $current_release = '12.0.0';
    } elseif (! in_array(170, $applied_upgrades)) {
        $current_release = '13.0.0';
    } else {
        // confirm that the database is in the same version as source code files
        conf_update_param('piwigo_db_version', get_branch_from_version(PHPWG_VERSION));

        header('Content-Type: text/html; charset=utf-8');
        echo 'No upgrade required, the database structure is up to date';
        echo '<br><a href="index.php">← back to gallery</a>';
        exit();
    }
}

// +-----------------------------------------------------------------------+
// |                            upgrade launch                             |
// +-----------------------------------------------------------------------+
$page['infos'] = [];
$page['errors'] = [];
$mysql_changes = [];

// check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    $page['errors'][] = l10n('PHP version %s required (you are running on PHP %s)', REQUIRED_PHP_VERSION, PHP_VERSION);
}

check_upgrade_access_rights();

if ((isset($_POST['submit']) or isset($_GET['now']))
  and check_upgrade()) {
    $upgrade_file = PHPWG_ROOT_PATH . 'install/upgrade_' . $current_release . '.php';
    if (is_file($upgrade_file)) {
        // reset SQL counters
        $page['queries_time'] = 0;
        $page['count_queries'] = 0;

        $page['upgrade_start'] = get_moment();
        $conf['die_on_sql_error'] = false;
        require $upgrade_file;
        conf_update_param('piwigo_db_version', get_branch_from_version(PHPWG_VERSION));

        // Something to add in database.inc.php?
        if (! empty($mysql_changes)) {
            $config_file_contents =
              substr($config_file_contents, 0, $php_end_tag) . "\r\n"
              . implode("\r\n", $mysql_changes) . "\r\n"
              . substr($config_file_contents, $php_end_tag);

            if (! file_put_contents($config_file, $config_file_contents)) {
                $page['infos'][] = l10n(
                    'In <i>%s</i>, before <b>?></b>, insert:',
                    'local/config/database.inc.php'
                )
                . '<p><textarea rows="4" cols="40">'
                . implode("\r\n", $mysql_changes) . '</textarea></p>';
            }
        }

        // Deactivate non-standard extensions
        deactivate_non_standard_plugins();
        deactivate_non_standard_themes();
        deactivate_templates();

        $page['upgrade_end'] = get_moment();

        $template->assign(
            'upgrade',
            [
                'VERSION' => $current_release,
                'TOTAL_TIME' => get_elapsed_time(
                    $page['upgrade_start'],
                    $page['upgrade_end']
                ),
                'SQL_TIME' => number_format(
                    $page['queries_time'],
                    3,
                    '.',
                    ' '
                ) . ' s',
                'NB_QUERIES' => $page['count_queries'],
            ]
        );

        $page['infos'][] = l10n('Perform a maintenance check in [Administration>Tools>Maintenance] if you encounter any problem.');

        // Save $page['infos'] in order to restore after maintenance actions
        $page['infos_sav'] = $page['infos'];
        $page['infos'] = [];

        $template->assign(
            [
                'button_label' => l10n('Home'),
                'button_link' => 'index.php',
            ]
        );

        // if the webmaster has a session, let's give a link to discover new features
        if (! empty($_SESSION['pwg_uid'])) {
            $version_ = str_replace('.', '_', get_branch_from_version(PHPWG_VERSION) . '.0');

            if (file_exists(PHPWG_PLUGINS_PATH . 'TakeATour/tours/' . $version_ . '/config.inc.php')) {
                $query = <<<SQL
                    REPLACE INTO plugins
                        (id, state)
                    VALUES
                        ('TakeATour', 'active');
                    SQL;
                pwg_query($query);

                // we need the secret key for get_pwg_token()
                load_conf_from_db();

                $template->assign(
                    [
                        'button_label' => l10n('Discover what\'s new in Piwigo %s', get_branch_from_version(PHPWG_VERSION)),
                        'button_link' => 'admin.php?submitted_tour_path=tours/' . $version_ . '&amp;pwg_token=' . get_pwg_token(),
                    ]
                );
            }
        }

        // Delete cache data
        invalidate_user_cache(true);
        $template->delete_compiled_templates();

        // Restore $page['infos'] in order to hide information messages from functions callers
        // errors messages are not hide
        $page['infos'] = $page['infos_sav'];

    }
}

// +-----------------------------------------------------------------------+
// |                          start template output                        |
// +-----------------------------------------------------------------------+
else {
    require_once PHPWG_ROOT_PATH . 'admin/include/languages.class.php';
    $languages = new languages();

    foreach ($languages->fs_languages as $language_code => $fs_language) {
        if ($language == $language_code) {
            $template->assign('language_selection', $language_code);
        }
        $languages_options[$language_code] = $fs_language['name'];
    }
    $template->assign('language_options', $languages_options);

    $template->assign('introduction', [
        'CURRENT_RELEASE' => $current_release,
        'F_ACTION' => 'upgrade.php?language=' . $language,
    ]);

    if (! check_upgrade()) {
        $template->assign('login', true);
    }
}

if (count($page['errors']) != 0) {
    $template->assign('errors', $page['errors']);
}

if (count($page['infos']) != 0) {
    $template->assign('infos', $page['infos']);
}

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+

$template->pparse('upgrade');
