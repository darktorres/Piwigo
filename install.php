<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\functions_admin;
use Piwigo\admin\inc\functions_install;
use Piwigo\admin\inc\languages;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_cookie;
use Piwigo\inc\functions_mail;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;
use Piwigo\inc\Template;

//----------------------------------------------------------- include
define('PHPWG_ROOT_PATH', './');

// @set_magic_quotes_runtime(0); // Disable magic_quotes_runtime
//
// addslashes to vars if magic_quotes_gpc is off this is a security
// precaution to prevent someone trying to break out of a SQL statement.
//
if (function_exists('get_magic_quotes_gpc') && ! @get_magic_quotes_gpc()) {
    if (is_array($_POST)) {
        foreach ($_POST as $k => $v) {
            if (is_array($_POST[$k])) {
                foreach ($_POST[$k] as $k2 => $v2) {
                    $_POST[$k][$k2] = addslashes($v2);
                }

                @reset($_POST[$k]);
            } else {
                $_POST[$k] = addslashes($v);
            }
        }

        @reset($_POST);
    }

    if (is_array($_GET)) {
        foreach ($_GET as $k => $v) {
            if (is_array($_GET[$k])) {
                foreach ($_GET[$k] as $k2 => $v2) {
                    $_GET[$k][$k2] = addslashes($v2);
                }

                @reset($_GET[$k]);
            } else {
                $_GET[$k] = addslashes($v);
            }
        }

        @reset($_GET);
    }

    if (is_array($_COOKIE)) {
        foreach ($_COOKIE as $k => $v) {
            if (is_array($_COOKIE[$k])) {
                foreach ($_COOKIE[$k] as $k2 => $v2) {
                    $_COOKIE[$k][$k2] = addslashes($v2);
                }

                @reset($_COOKIE[$k]);
            } else {
                $_COOKIE[$k] = addslashes($v);
            }
        }

        @reset($_COOKIE);
    }
}

//----------------------------------------------------- variable initialization

define('DEFAULT_PREFIX_TABLE', 'piwigo_');

if (isset($_POST['install'])) {
    $prefixeTable = $_POST['prefix'];
} else {
    $prefixeTable = DEFAULT_PREFIX_TABLE;
}

include(PHPWG_ROOT_PATH . 'inc/config_default.php');
@include(PHPWG_ROOT_PATH . 'local/config/config.php');
defined('PWG_LOCAL_DIR') or define('PWG_LOCAL_DIR', 'local/');

include(PHPWG_ROOT_PATH . 'inc/functions.php');
include(PHPWG_ROOT_PATH . 'inc/Template.php');

// download database config file if exists
functions::check_input_parameter('dl', $_GET, false, '/^[a-f0-9]{32}$/');

if (! empty($_GET['dl']) && file_exists(PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $_GET['dl'])) {
    $filename = PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $_GET['dl'];
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Disposition: attachment; filename="database.php"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($filename));
    echo file_get_contents($filename);
    unlink($filename);
    exit();
}

// Obtain various vars
$dbhost = (! empty($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
$dbuser = (! empty($_POST['dbuser'])) ? $_POST['dbuser'] : '';
$dbpasswd = (! empty($_POST['dbpasswd'])) ? $_POST['dbpasswd'] : '';
$dbname = (! empty($_POST['dbname'])) ? $_POST['dbname'] : '';

// dblayer
$dblayer = 'mysqli';

$admin_name = (! empty($_POST['admin_name'])) ? $_POST['admin_name'] : '';
$admin_pass1 = (! empty($_POST['admin_pass1'])) ? $_POST['admin_pass1'] : '';
$admin_pass2 = (! empty($_POST['admin_pass2'])) ? $_POST['admin_pass2'] : '';
$admin_mail = (! empty($_POST['admin_mail'])) ? $_POST['admin_mail'] : '';

$is_newsletter_subscribe = true;
if (isset($_POST['install'])) {
    $is_newsletter_subscribe = isset($_POST['newsletter_subscribe']);
}

$infos = [];
$errors = [];

$config_file = PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/database.php';
if (@file_exists($config_file)) {
    include($config_file);
    // Is Piwigo already installed ?
    if (defined('PHPWG_INSTALLED')) {
        die('Piwigo is already installed');
    }
}

include(PHPWG_ROOT_PATH . 'inc/constants.php');
include(PHPWG_ROOT_PATH . 'admin/inc/functions_admin.php');

$languages = new languages('utf-8');

if (isset($_GET['language'])) {
    $language = strip_tags($_GET['language']);

    if (! in_array($language, array_keys($languages->fs_languages))) {
        $language = PHPWG_DEFAULT_LANGUAGE;
    }
} else {
    $language = 'en_UK';
    // Try to get browser language
    // foreach ($languages->fs_languages as $language_code => $fs_language)
    // {
    //   if (substr($language_code,0,2) == @substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2))
    //   {
    //     $language = $language_code;
    //     break;
    //   }
    // }
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

functions::load_language('common.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);
functions::load_language('admin.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);
functions::load_language('install.lang', '', [
    'language' => $language,
    'target_charset' => 'utf-8',
]);

header('Content-Type: text/html; charset=UTF-8');
//------------------------------------------------- check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    $errors[] = functions::l10n('PHP version %s required (you are running on PHP %s)', REQUIRED_PHP_VERSION, PHP_VERSION);
}

//----------------------------------------------------- template initialization
$template = new Template(PHPWG_ROOT_PATH . 'admin/themes', 'roma');
$template->set_filenames([
    'install' => 'install.tpl',
]);
if (! isset($step)) {
    $step = 1;
}

//---------------------------------------------------------------- form analyze
include(PHPWG_ROOT_PATH . 'inc/dblayer/functions_' . $dblayer . '.php');
include(PHPWG_ROOT_PATH . 'admin/inc/functions_install.php');
include(PHPWG_ROOT_PATH . 'admin/inc/functions_upgrade.php');

if (isset($_POST['install'])) {
    functions_install::install_db_connect($infos, $errors);

    if (count($errors) > 0) {
        print_r($errors);
    }

    functions_mysqli::pwg_db_check_charset();

    $webmaster = trim(preg_replace('/\s{2,}/', ' ', $admin_name));
    if (empty($webmaster)) {
        $errors[] = functions::l10n('enter a login for webmaster');
    } elseif (preg_match('/[\'"]/', $webmaster)) {
        $errors[] = functions::l10n('webmaster login can\'t contain characters \' or "');
    }

    if ($admin_pass1 != $admin_pass2 || empty($admin_pass1)) {
        $errors[] = functions::l10n('please enter your password again');
    }

    if (empty($admin_mail)) {
        $errors[] = functions::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
    } else {
        $error_mail_address = functions_user::validate_mail_address(null, $admin_mail);
        if (! empty($error_mail_address)) {
            $errors[] = $error_mail_address;
        }
    }

    if (count($errors) == 0) {
        $step = 2;

        // tables creation, based on piwigo_structure.sql
        functions_install::execute_sqlfile(
            PHPWG_ROOT_PATH . 'install/piwigo_structure-mysql.sql',
            DEFAULT_PREFIX_TABLE,
            $prefixeTable,
            'mysql'
        );
        // We fill the tables with basic informations
        functions_install::execute_sqlfile(
            PHPWG_ROOT_PATH . 'install/config.sql',
            DEFAULT_PREFIX_TABLE,
            $prefixeTable,
            'mysql'
        );

        $query = '
INSERT INTO ' . $prefixeTable . 'config (param,value,comment)
   VALUES (\'secret_key\',md5(' . functions_mysqli::pwg_db_cast_to_text(functions_mysqli::DB_RANDOM_FUNCTION . '()') . '),
   \'a secret key specific to the gallery for internal use\');';
        functions_mysqli::pwg_query($query);

        functions::conf_update_param('piwigo_db_version', functions::get_branch_from_version(PHPWG_VERSION));
        functions::conf_update_param('gallery_title', functions_mysqli::pwg_db_real_escape_string(functions::l10n('Just another Piwigo gallery')));

        functions::conf_update_param(
            'page_banner',
            '<h1>%gallery_title%</h1>' . "\n\n<p>" . functions_mysqli::pwg_db_real_escape_string(functions::l10n('Welcome to my photo gallery')) . '</p>'
        );

        // fill languages table, only activate the current language
        $languages->perform_action('activate', $language);

        // fill $conf global array
        functions::load_conf_from_db();

        // PWG_CHARSET is required for building the fs_themes array in the
        // themes class
        if (! defined('PWG_CHARSET')) {
            define('PWG_CHARSET', 'utf-8');
        }

        functions_install::activate_core_themes();
        functions_install::activate_core_plugins();

        $insert = [
            'id' => 1,
            'galleries_url' => PHPWG_ROOT_PATH . 'galleries/',
        ];
        functions_mysqli::mass_inserts(SITES_TABLE, array_keys($insert), [$insert]);

        // webmaster admin user
        $inserts = [
            [
                'id' => 1,
                'username' => $admin_name,
                'password' => functions_user::pwg_password_hash($admin_pass1),
                'mail_address' => $admin_mail,
            ],
            [
                'id' => 2,
                'username' => 'guest',
            ],
        ];
        functions_mysqli::mass_inserts(USERS_TABLE, array_keys($inserts[0]), $inserts);

        functions_user::create_user_infos([1, 2], [
            'language' => $language,
        ]);

        // Available upgrades must be ignored after a fresh installation. To
        // make PWG avoid upgrading, we must tell it upgrades have already been
        // made.
        // list($dbnow) = \Piwigo\inc\dblayer\functions_mysqli::pwg_db_fetch_row(\Piwigo\inc\dblayer\functions_mysqli::pwg_query('SELECT NOW();'));
        // define('CURRENT_DATE', $dbnow);
        // $datas = array();
        // foreach (\Piwigo\admin\inc\functions_upgrade::get_available_upgrade_ids() as $upgrade_id)
        // {
        //   $datas[] = array(
        //     'id'          => $upgrade_id,
        //     'applied'     => CURRENT_DATE,
        //     'description' => 'upgrade included in installation',
        //     );
        // }
        // \Piwigo\inc\dblayer\functions_mysqli::mass_inserts(
        //   UPGRADE_TABLE,
        //   array_keys($datas[0]),
        //   $datas
        //   );

        $file_content = '<?php
$conf[\'dblayer\'] = \'' . $dblayer . '\';
$conf[\'db_base\'] = \'' . $dbname . '\';
$conf[\'db_user\'] = \'' . $dbuser . '\';
$conf[\'db_password\'] = \'' . $dbpasswd . '\';
$conf[\'db_host\'] = \'' . $dbhost . '\';

$prefixeTable = \'' . $prefixeTable . '\';

define(\'PHPWG_INSTALLED\', true);
define(\'PWG_CHARSET\', \'utf-8\');
define(\'DB_CHARSET\', \'utf8\');
define(\'DB_COLLATE\', \'\');

?' . '>';

        @umask(0111);
        // writing the configuration file
        if (! ($fp = @fopen($config_file, 'w'))) {
            // make sure nobody can list files of _data directory
            functions::secure_directory(PHPWG_ROOT_PATH . $conf['data_location']);

            $tmp_filename = md5(uniqid(time()));
            $fh = @fopen(PHPWG_ROOT_PATH . $conf['data_location'] . 'pwg_' . $tmp_filename, 'w');
            @fputs($fh, $file_content, strlen($file_content));
            @fclose($fh);

            $template->assign(
                [
                    'config_creation_failed' => true,
                    'config_url' => 'install.php?dl=' . $tmp_filename,
                    'config_file_content' => $file_content,
                ]
            );
        }

        @fputs($fp, $file_content, strlen($file_content));
        @fclose($fp);
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
        'F_DB_PREFIX' => $prefixeTable,
        'F_ADMIN' => $admin_name,
        'F_ADMIN_EMAIL' => $admin_mail,
        'EMAIL' => '<span class="adminEmail">' . $admin_mail . '</span>',
        'F_NEWSLETTER_SUBSCRIBE' => $is_newsletter_subscribe,
        'L_INSTALL_HELP' => functions::l10n('Need help ? Ask your question on <a href="%s">Piwigo message board</a>.', PHPWG_URL . '/forum'),
    ]
);

//------------------------------------------------------ errors & infos display
if ($step == 1) {
    $template->assign('install', true);
} else {
    functions::pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'install', [
        'version' => PHPWG_VERSION,
    ]);
    $infos[] = functions::l10n('Congratulations, Piwigo installation is completed');

    if (isset($error_copy)) {
        $errors[] = $error_copy;
    } else {
        session_set_save_handler(
            '\Piwigo\inc\functions_session::pwg_session_open',
            '\Piwigo\inc\functions_session::pwg_session_close',
            '\Piwigo\inc\functions_session::pwg_session_read',
            '\Piwigo\inc\functions_session::pwg_session_write',
            '\Piwigo\inc\functions_session::pwg_session_destroy',
            '\Piwigo\inc\functions_session::pwg_session_gc'
        );
        if (function_exists('ini_set')) {
            ini_set('session.use_cookies', $conf['session_use_cookies']);
            ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
            ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
            ini_set('session.cookie_httponly', 1);
        }

        session_name($conf['session_name']);
        session_set_cookie_params(0, functions_cookie::cookie_path());
        register_shutdown_function('session_write_close');

        $user = functions_user::build_user(1, true);
        functions_user::log_user($user['id'], false);

        // newsletter subscription
        if ($is_newsletter_subscribe) {
            functions_admin::fetchRemote(
                functions_admin::get_newsletter_subscribe_base_url($language) . $admin_mail,
                $result,
                [],
                [
                    'origin' => 'installation',
                ]
            );

            functions_user::userprefs_update_param('show_newsletter_subscription', false);
        }

        // email notification
        if (isset($_POST['send_credentials_by_mail'])) {
            include_once(PHPWG_ROOT_PATH . 'inc/functions_mail.php');

            $keyargs_content = [
                functions::get_l10n_args('Hello %s,', $admin_name),
                functions::get_l10n_args('Welcome to your new installation of Piwigo!', ''),
                functions::get_l10n_args('', ''),
                functions::get_l10n_args('Here are your connection settings', ''),
                functions::get_l10n_args('', ''),
                functions::get_l10n_args('Link: %s', functions_url::get_absolute_root_url()),
                functions::get_l10n_args('Username: %s', $admin_name),
                functions::get_l10n_args('Password: ********** (no copy by email)', ''),
                functions::get_l10n_args('Email: %s', $admin_mail),
                functions::get_l10n_args('', ''),
                functions::get_l10n_args('Don\'t hesitate to consult our forums for any help: %s', PHPWG_URL),
            ];

            functions_mail::pwg_mail(
                $admin_mail,
                [
                    'subject' => functions::l10n('Just another Piwigo gallery'),
                    'content' => functions::l10n_args($keyargs_content),
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
