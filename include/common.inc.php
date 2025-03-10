<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

defined('PHPWG_ROOT_PATH') || trigger_error('Hacking attempt!', E_USER_ERROR);

// determine the initial instant to indicate the generation time of this page
$t2 = microtime(true);

//
// addslashes to vars is a security precaution to prevent someone trying
// to break out of a SQL statement.
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

//
// Define some basic configuration arrays this also prevents malicious
// rewriting of language and other array values via URI params
//
$conf = [];
$page = [
    'infos' => [],
    'errors' => [],
    'warnings' => [],
    'messages' => [],
    'body_classes' => [],
    'body_data' => [],
];
$user = [];
$lang = [];
$header_msgs = [];
$header_notes = [];
$filter = [];

require PHPWG_ROOT_PATH . 'include/config_default.inc.php';
if (file_exists(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    require PHPWG_ROOT_PATH . 'local/config/config.inc.php';
}

if (file_exists(PHPWG_ROOT_PATH . 'local/config/database.inc.php')) {
    require PHPWG_ROOT_PATH . 'local/config/database.inc.php';
}

if (! defined('PHPWG_INSTALLED')) {
    header('Location: install.php');
    exit;
}

require PHPWG_ROOT_PATH . 'include/dblayer/functions_' . $conf['dblayer'] . '.inc.php';

if (isset($conf['show_php_errors']) && ! empty($conf['show_php_errors'])) {
    ini_set('error_reporting', $conf['show_php_errors']);
    if ($conf['show_php_errors_on_frontend']) {
        ini_set('display_errors', true);
    }
}

if ($conf['session_gc_probability'] > 0) {
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_probability', min((int) $conf['session_gc_probability'], 100));
}

require PHPWG_ROOT_PATH . 'include/constants.php';
require PHPWG_ROOT_PATH . 'include/functions.inc.php';
require PHPWG_ROOT_PATH . 'include/template.class.php';
require PHPWG_ROOT_PATH . 'include/cache.class.php';

$persistent_cache = new PersistentFileCache();

// Database connection
pwg_db_connect(
    $conf['db_host'],
    $conf['db_user'],
    $conf['db_password'],
    $conf['db_base']
);

load_conf_from_db();

$logger = new Katzgrau\KLogger\Logger(PHPWG_ROOT_PATH . $conf['data_location'] . $conf['log_dir'], $conf['log_level'], [
    // we use a hashed filename to prevent direct file access, and we salt with
    // the db_password instead of secret_key because the log must be usable in i.php
    // (secret_key is in the database)
    'filename' => 'log_' . date('Y-m-d') . '_' . sha1(date('Y-m-d') . $conf['db_password']) . '.txt',
]);

if (! $conf['check_upgrade_feed'] && (! isset($conf['piwigo_db_version']) || $conf['piwigo_db_version'] != get_branch_from_version(PHPWG_VERSION))) {
    redirect(get_root_url() . 'upgrade.php');
}

ImageStdParams::load_from_db();

session_start();
load_plugins();

if (! isset($conf['piwigo_installed_version'])) {
    conf_update_param('piwigo_installed_version', PHPWG_VERSION);
} elseif ($conf['piwigo_installed_version'] != PHPWG_VERSION) {
    pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'autoupdate', [
        'from_version' => $conf['piwigo_installed_version'],
        'to_version' => PHPWG_VERSION,
    ]);
    conf_update_param('piwigo_installed_version', PHPWG_VERSION);
}

// users can have defined a custom order pattern, incompatible with GUI form
if (isset($conf['order_by_custom'])) {
    $conf['order_by'] = $conf['order_by_custom'];
}

if (isset($conf['order_by_inside_category_custom'])) {
    $conf['order_by_inside_category'] = $conf['order_by_inside_category_custom'];
}

check_lounge();

require PHPWG_ROOT_PATH . 'include/user.inc.php';

if (in_array(substr($user['language'], 0, 2), ['fr', 'it', 'de', 'es', 'pl', 'ru', 'nl', 'tr', 'da'])) {
    define('PHPWG_DOMAIN', substr($user['language'], 0, 2) . '.piwigo.org');
} elseif ($user['language'] == 'zh_CN') {
    define('PHPWG_DOMAIN', 'cn.piwigo.org');
} elseif ($user['language'] == 'pt_BR') {
    define('PHPWG_DOMAIN', 'br.piwigo.org');
} else {
    define('PHPWG_DOMAIN', 'piwigo.org');
}

define('PHPWG_URL', 'https://' . PHPWG_DOMAIN);

if (isset($conf['alternative_pem_url']) && $conf['alternative_pem_url'] != '') {
    define('PEM_URL', $conf['alternative_pem_url']);
} else {
    define('PEM_URL', 'https://' . PHPWG_DOMAIN . '/ext');
}

// language files
load_language('common.lang');
if (is_admin() || (defined('IN_ADMIN') && IN_ADMIN)) {
    load_language('admin.lang');
}

trigger_notify('loading_lang');
load_language('lang', PHPWG_ROOT_PATH . 'local/', [
    'no_fallback' => true,
    'local' => true,
]);

// only now we can set the localized username of the guest user (and not in
// include/user.inc.php)
if (is_a_guest()) {
    $user['username'] = l10n('guest');
}

// in case an auth key was provided and is no longer valid, we must wait to
// be here, with language loaded, to prepare the message
if (isset($page['auth_key_invalid']) && $page['auth_key_invalid']) {
    $page['errors'][] =
      l10n('Your authentication key is no longer valid.')
      . sprintf(' <a href="%s">%s</a>', get_root_url() . 'identification.php', l10n('Login'))
    ;
}

// template instance
if (defined('IN_ADMIN') && IN_ADMIN) {// Admin template
    $template = new Template(PHPWG_ROOT_PATH . 'admin/themes', userprefs_get_param('admin_theme', 'roma'));
} else { // Classic template
    $theme = $user['theme'];
    if (script_basename() !== 'ws' && mobile_theme()) {
        $theme = $conf['mobile_theme'];
    }

    $template = new Template(PHPWG_ROOT_PATH . 'themes', $theme);
}

if (! isset($conf['no_photo_yet'])) {
    require PHPWG_ROOT_PATH . 'include/no_photo_yet.inc.php';
}

if (isset($user['internal_status']['guest_must_be_guest']) && $user['internal_status']['guest_must_be_guest'] === true) {
    $header_msgs[] = l10n('Bad status for user "guest", using default status. Please notify the webmaster.');
}

if ($conf['gallery_locked']) {
    $header_msgs[] = l10n('The gallery is locked for maintenance. Please, come back later.');

    if (script_basename() !== 'identification' && ! is_admin()) {
        set_status_header(503, 'Service Unavailable');
        header('Retry-After: 900');
        header('Content-Type: text/html; charset=utf-8');
        echo '<a href="' . get_absolute_root_url(false) . 'identification.php">' . l10n('The gallery is locked for maintenance. Please, come back later.') . '</a>';
        echo str_repeat(' ', 512); //IE6 doesn't error output if below a size
        exit();
    }
}

if ($conf['check_upgrade_feed']) {
    require_once PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php';
    if (check_upgrade_feed()) {
        $header_msgs[] = 'Some database upgrades are missing, '
          . '<a href="' . get_absolute_root_url(false) . 'upgrade_feed.php">upgrade now</a>';
    }
}

if ($header_msgs !== []) {
    $template->assign('header_msgs', $header_msgs);
    $header_msgs = [];
}

if (! empty($conf['filter_pages']) && get_filter_page_value('used')) {
    require PHPWG_ROOT_PATH . 'include/filter.inc.php';
} else {
    $filter['enabled'] = false;
}

if (isset($conf['header_notes'])) {
    $header_notes = array_merge($header_notes, $conf['header_notes']);
}

// default event handlers
add_event_handler('render_category_literal_description', render_category_literal_description(...));
if (! $conf['allow_html_descriptions']) {
    add_event_handler('render_category_description', nl2br(...));
}

add_event_handler('render_comment_content', render_comment_content(...));
add_event_handler('render_comment_author', strip_tags(...));
add_event_handler('render_tag_url', str2url(...));
add_event_handler('blockmanager_register_blocks', register_default_menubar_blocks(...), EVENT_HANDLER_PRIORITY_NEUTRAL - 1);
if (! empty($conf['original_url_protection'])) {
    add_event_handler('get_element_url', get_element_url_protection_handler(...));
    add_event_handler('get_src_image_url', get_src_image_url_protection_handler(...));
}

trigger_notify('init');
