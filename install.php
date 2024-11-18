<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//----------------------------------------------------------- include
define('PHPWG_ROOT_PATH', './');

//
// addslashes to vars if magic_quotes_gpc is off, this is a security
// precaution to prevent someone trying to break out of a SQL statement.
//
function sanitize_mysql_kv(
    string &$v,
    string $k
): void {
    $v = addslashes($v);
}

array_walk_recursive($_GET, sanitize_mysql_kv(...));

array_walk_recursive($_POST, sanitize_mysql_kv(...));

array_walk_recursive($_COOKIE, sanitize_mysql_kv(...));

if (! empty($_SERVER['PATH_INFO'])) {
    $_SERVER['PATH_INFO'] = addslashes((string) $_SERVER['PATH_INFO']);
}

//----------------------------------------------------- variable initialization

require PHPWG_ROOT_PATH . 'include/config_default.inc.php';
if (file_exists(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    require PHPWG_ROOT_PATH . 'local/config/config.inc.php';
}

require PHPWG_ROOT_PATH . 'include/functions.inc.php';
require PHPWG_ROOT_PATH . 'include/template.class.php';

// download database config file if exists
check_input_parameter('dl', $_GET, false, '/^[a-f0-9]{32}$/');

if (! empty($_GET['dl']) && file_exists(PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $_GET['dl'])) {
    $filename = PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $_GET['dl'];
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Disposition: attachment; filename="database.inc.php"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($filename));
    echo file_get_contents($filename);
    unlink($filename);
    exit();
}

// Obtain various vars
$dbhost = (empty($_POST['dbhost'])) ? '' : $_POST['dbhost'];
$dbuser = (empty($_POST['dbuser'])) ? '' : $_POST['dbuser'];
$dbpasswd = (empty($_POST['dbpasswd'])) ? '' : $_POST['dbpasswd'];
$dbname = (empty($_POST['dbname'])) ? '' : $_POST['dbname'];

// dblayer
$dblayer = (empty($_POST['dbtype'])) ? 'mysqli' : str_replace('-socket', '', $_POST['dbtype']);

$admin_name = (empty($_POST['admin_name'])) ? '' : $_POST['admin_name'];
$admin_pass1 = (empty($_POST['admin_pass1'])) ? '' : $_POST['admin_pass1'];
$admin_pass2 = (empty($_POST['admin_pass2'])) ? '' : $_POST['admin_pass2'];
$admin_mail = (empty($_POST['admin_mail'])) ? '' : $_POST['admin_mail'];

$is_newsletter_subscribe = true;
if (isset($_POST['install'])) {
    $is_newsletter_subscribe = isset($_POST['newsletter_subscribe']);
}

$infos = [];
$errors = [];

$config_file = PHPWG_ROOT_PATH . 'local/config/database.inc.php';
if (file_exists($config_file)) {
    require $config_file;
    // Is Piwigo already installed ?
    if (defined('PHPWG_INSTALLED')) {
        die('Piwigo is already installed');
    }
}

require PHPWG_ROOT_PATH . 'include/constants.php';
require PHPWG_ROOT_PATH . 'admin/include/functions.php';

require PHPWG_ROOT_PATH . 'admin/include/languages.class.php';
$languages = new languages('utf-8');

if (isset($_GET['language'])) {
    $language = strip_tags((string) $_GET['language']);

    if (! in_array($language, array_keys($languages->fs_languages))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
} else {
    $language = 'en_UK';
    // Try to get browser language
    // foreach ($languages->fs_languages as $language_code => $fs_language)
    // {
    //   if (substr($language_code,0,2) == substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2))
    //   {
    //     $language = $language_code;
    //     break;
    //   }
    // }
}

if ($language === 'fr_FR') {
    define('PHPWG_DOMAIN', 'fr.piwigo.org');
} elseif ($language === 'it_IT') {
    define('PHPWG_DOMAIN', 'it.piwigo.org');
} elseif ($language === 'de_DE') {
    define('PHPWG_DOMAIN', 'de.piwigo.org');
} elseif ($language === 'es_ES') {
    define('PHPWG_DOMAIN', 'es.piwigo.org');
} elseif ($language === 'pl_PL') {
    define('PHPWG_DOMAIN', 'pl.piwigo.org');
} elseif ($language === 'zh_CN') {
    define('PHPWG_DOMAIN', 'cn.piwigo.org');
} elseif ($language === 'ru_RU') {
    define('PHPWG_DOMAIN', 'ru.piwigo.org');
} elseif ($language === 'nl_NL') {
    define('PHPWG_DOMAIN', 'nl.piwigo.org');
} elseif ($language === 'tr_TR') {
    define('PHPWG_DOMAIN', 'tr.piwigo.org');
} elseif ($language === 'da_DK') {
    define('PHPWG_DOMAIN', 'da.piwigo.org');
} elseif ($language === 'pt_BR') {
    define('PHPWG_DOMAIN', 'br.piwigo.org');
} else {
    define('PHPWG_DOMAIN', 'piwigo.org');
}

define('PHPWG_URL', 'https://' . PHPWG_DOMAIN);

load_language('common.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);
load_language('admin.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);
load_language('install.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);

header('Content-Type: text/html; charset=UTF-8');
//------------------------------------------------- check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    $errors[] = l10n('PHP version %s required (you are running on PHP %s)', REQUIRED_PHP_VERSION, PHP_VERSION);
}

//----------------------------------------------------- template initialization
$template = new Template(PHPWG_ROOT_PATH . 'admin/themes', 'roma'); // TODO: fix dark theme
$template->set_filenames([
    'install' => 'install.tpl',
]);
if (! isset($step)) {
    $step = 1;
}

//---------------------------------------------------------------- form analyze
require PHPWG_ROOT_PATH . 'include/dblayer/functions_' . $dblayer . '.inc.php';
require PHPWG_ROOT_PATH . 'admin/include/functions_install.inc.php';
require PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php';

if (isset($_POST['install'])) {
    $webmaster = trim((string) preg_replace('/\s{2,}/', ' ', (string) $admin_name));
    if ($webmaster === '' || $webmaster === '0') {
        $errors[] = l10n('enter a login for webmaster');
    } elseif (preg_match('/[\'"]/', $webmaster)) {
        $errors[] = l10n('webmaster login can\'t contain characters \' or "');
    }

    if ($admin_pass1 != $admin_pass2 || empty($admin_pass1)) {
        $errors[] = l10n('please enter your password again');
    }

    if (empty($admin_mail)) {
        $errors[] = l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
    } else {
        $error_mail_address = validate_mail_address(null, $admin_mail);
        if ($error_mail_address !== null && $error_mail_address !== '' && $error_mail_address !== '0') {
            $errors[] = $error_mail_address;
        }
    }

    if (count($errors) == 0) {
        $step = 2;

        pwg_db_connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpasswd']);
        pwg_db_check_version();
        pwg_query("DROP DATABASE IF EXISTS {$dbname};");
        pwg_query("CREATE DATABASE {$dbname};");

        pwg_db_connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpasswd'], $_POST['dbname']);

        // tables creation, based on piwigo_structure.sql
        execute_sqlfile(
            PHPWG_ROOT_PATH . "install/piwigo_structure-{$dblayer}.sql",
            $dblayer
        );
        // We fill the tables with basic information
        execute_sqlfile(
            PHPWG_ROOT_PATH . 'install/config.sql',
            $dblayer
        );

        $random_function = DB_RANDOM_FUNCTION;
        $query = <<<SQL
            INSERT INTO config
                (param, value, comment)
            VALUES
                ('secret_key', md5({$random_function}), 'a secret key specific to the gallery for internal use');
            SQL;
        pwg_query($query);

        conf_update_param('piwigo_db_version', get_branch_from_version(PHPWG_VERSION));
        conf_update_param('gallery_title', pwg_db_real_escape_string(l10n('Just another Piwigo gallery')));

        conf_update_param(
            'page_banner',
            '<h1>%gallery_title%</h1>' . "\n\n<p>" . pwg_db_real_escape_string(l10n('Welcome to my photo gallery')) . '</p>'
        );

        // fill languages table, only activate the current language
        $languages->perform_action('activate', $language);

        // fill $conf global array
        load_conf_from_db();

        activate_core_themes();
        activate_core_plugins();

        $insert = [
            'id' => 1,
            'galleries_url' => PHPWG_ROOT_PATH . 'galleries/',
        ];
        mass_inserts('sites', array_keys($insert), [$insert]);

        // webmaster admin user
        $inserts = [
            [
                'id' => 1,
                'username' => $admin_name,
                'password' => pwg_password_hash($admin_pass1),
                'mail_address' => $admin_mail,
            ],
            [
                'id' => 2,
                'username' => 'guest',
            ],
        ];
        mass_inserts('users', array_keys($inserts[0]), $inserts);

        create_user_infos([1, 2], [
            'language' => $language,
        ]);

        // Available upgrades must be ignored after a fresh installation. To
        // make PWG avoid upgrading, we must tell it upgrades have already been
        // made.
        // list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
        // define('CURRENT_DATE', $dbnow);
        // $datas = array();
        // foreach (get_available_upgrade_ids() as $upgrade_id)
        // {
        //   $datas[] = array(
        //     'id'          => $upgrade_id,
        //     'applied'     => CURRENT_DATE,
        //     'description' => 'upgrade included in installation',
        //     );
        // }
        // mass_inserts(
        //   'upgrade',
        //   array_keys($datas[0]),
        //   $datas
        //   );

        $file_content =
          "<?php\n" .
          "\n" .
          "declare(strict_types=1);\n" .
          "\n" .
          "\$conf['dblayer'] = '{$dblayer}';\n" .
          "\$conf['db_base'] = '{$dbname}';\n" .
          "\$conf['db_user'] = '{$dbuser}';\n" .
          "\$conf['db_password'] = '{$dbpasswd}';\n" .
          "\$conf['db_host'] = '{$dbhost}';\n" .
          "\n" .
          "define('PHPWG_INSTALLED', true);\n";

        umask(0111);

        // writing the configuration file
        if (! ($fp = fopen($config_file, 'w'))) {
            // make sure nobody can list files of _data directory
            secure_directory(PHPWG_ROOT_PATH . $conf['data_location']);
            $tmp_filename = md5(uniqid((string) time()));
            $fh = fopen(PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $tmp_filename, 'w');
            fwrite($fh, $file_content, strlen($file_content));
            fclose($fh);
            $template->assign(
                [
                    'config_creation_failed' => true,
                    'config_url' => 'install.php?dl=' . $tmp_filename,
                    'config_file_content' => $file_content,
                ]
            );
        }

        fwrite($fp, $file_content, strlen($file_content));
        fclose($fp);
    }
}

//------------------------------------------------------ start template output
foreach ($languages->fs_languages as $language_code => $fs_language) {
    if ($language == $language_code) {
        $template->assign('language_selection', $language_code);
    }

    $languages_options[$language_code] = $fs_language['name'];
}

$template->assign('language_options', $languages_options);

$template->assign(
    [
        'T_CONTENT_ENCODING' => 'utf-8',
        'RELEASE' => PHPWG_VERSION,
        'F_ACTION' => 'install.php?language=' . $language,
        'F_DB_HOST' => $dbhost,
        'F_DB_USER' => $dbuser,
        'F_DB_NAME' => $dbname,
        'F_ADMIN' => $admin_name,
        'F_ADMIN_EMAIL' => $admin_mail,
        'EMAIL' => '<span class="adminEmail">' . $admin_mail . '</span>',
        'F_NEWSLETTER_SUBSCRIBE' => $is_newsletter_subscribe,
        'L_INSTALL_HELP' => l10n('Need help ? Ask your question on <a href="%s">Piwigo message board</a>.', PHPWG_URL . '/forum'),
    ]
);

//------------------------------------------------------ errors & infos display
if ($step == 1) {
    $template->assign('install', true);
} else {
    pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'install', [
        'version' => PHPWG_VERSION,
    ]);
    $infos[] = l10n('Congratulations, Piwigo installation is completed');

    if (isset($error_copy)) {
        $errors[] = $error_copy;
    } else {
        require_once PHPWG_ROOT_PATH . 'include/PwgSessionHandler.php';
        $handler = new PwgSessionHandler();
        session_set_save_handler($handler, true);

        if (function_exists('ini_set')) {
            ini_set('session.use_cookies', $conf['session_use_cookies']);
            ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
            ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
            ini_set('session.cookie_httponly', 1);
        }

        session_name($conf['session_name']);
        session_set_cookie_params(0, cookie_path());
        register_shutdown_function(session_write_close(...));

        $user = build_user(1, true);
        log_user($user['id'], false);

        // newsletter subscription
        if ($is_newsletter_subscribe) {
            fetchRemote(
                get_newsletter_subscribe_base_url($language) . $admin_mail,
                $result,
                [],
                [
                    'origin' => 'installation',
                ]
            );

            userprefs_update_param('show_newsletter_subscription', false);
        }

        // email notification
        if (isset($_POST['send_credentials_by_mail'])) {
            require_once PHPWG_ROOT_PATH . 'include/functions_mail.inc.php';

            $keyargs_content = [
                get_l10n_args('Hello %s,', $admin_name),
                get_l10n_args('Welcome to your new installation of Piwigo!', ''),
                get_l10n_args('', ''),
                get_l10n_args('Here are your connection settings', ''),
                get_l10n_args('', ''),
                get_l10n_args('Link: %s', get_absolute_root_url()),
                get_l10n_args('Username: %s', $admin_name),
                get_l10n_args('Password: ********** (no copy by email)', ''),
                get_l10n_args('Email: %s', $admin_mail),
                get_l10n_args('', ''),
                get_l10n_args("Don't hesitate to consult our forums for any help: %s", PHPWG_URL),
            ];

            pwg_mail(
                $admin_mail,
                [
                    'subject' => l10n('Just another Piwigo gallery'),
                    'content' => l10n_args($keyargs_content),
                    'content_format' => 'text/plain',
                ]
            );
        }
    }
}

if (count($errors) != 0) {
    $template->assign('errors', $errors);
}

if (count($infos) != 0) {
    $template->assign('infos', $infos);
}

//----------------------------------------------------------- html code display
$template->pparse('install');
