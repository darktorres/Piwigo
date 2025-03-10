<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// by default, we start with guest
$user['id'] = $conf['guest_id'];

if (isset($_COOKIE[session_name()])) {
    if (isset($_GET['act']) && $_GET['act'] == 'logout') { // logout
        logout_user();
        redirect(get_gallery_home_url());
    } elseif (! empty($_SESSION['pwg_uid'])) {
        $user['id'] = $_SESSION['pwg_uid'];
    }
}

// Now check the auto-login
if ($user['id'] == $conf['guest_id']) {
    auto_login();
}

// using Apache authentication override the above user search
if ($conf['apache_authentication']) {
    $remote_user = null;
    foreach (['REMOTE_USER', 'REDIRECT_REMOTE_USER'] as $server_key) {
        if (isset($_SERVER[$server_key])) {
            $remote_user = $_SERVER[$server_key];
            break;
        }
    }

    if (isset($remote_user) && ! ($user['id'] = get_userid($remote_user))) {
        $user['id'] = register_user($remote_user, '', '', false);
    }
}

// automatic login by authentication key
if (isset($_GET['auth'])) {
    auth_key_login($_GET['auth']);
}

if ((defined('IN_WS') && isset($_REQUEST['method']) && $_REQUEST['method'] == 'pwg.images.uploadAsync' && isset($_POST['username']) && isset($_POST['password'])) && ! try_log_user($_POST['username'], $_POST['password'], false)) {
    require_once PHPWG_ROOT_PATH . 'include/ws_init.inc.php';
    $service->sendResponse(new PwgError(999, 'Invalid username/password'));
    exit();
}

$user = build_user(
    $user['id'],
    (defined('IN_ADMIN') && IN_ADMIN) ? false : true // use cache?
);
if ($conf['browser_language'] && (is_a_guest() || is_generic()) && ($language = get_browser_language())) {
    $user['language'] = $language;
}

trigger_notify('user_init', $user);
