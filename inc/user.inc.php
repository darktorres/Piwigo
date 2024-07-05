<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// by default we start with guest
$user['id'] = $conf['guest_id'];

if (isset($_COOKIE[session_name()])) {
    if (isset($_GET['act']) && $_GET['act'] == 'logout') { // logout
        FunctionsUser::logout_user();
        redirect(get_gallery_home_url());
    } elseif (! empty($_SESSION['pwg_uid'])) {
        $user['id'] = $_SESSION['pwg_uid'];
    }
}

// Now check the auto-login
if ($user['id'] == $conf['guest_id']) {
    FunctionsUser::auto_login();
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

    if (isset($remote_user) && ! ($user['id'] = FunctionsUser::get_userid($remote_user))) {
        $user['id'] = FunctionsUser::register_user($remote_user, '', '', false);
    }
}

// automatic login by authentication key
if (isset($_GET['auth'])) {
    FunctionsUser::auth_key_login($_GET['auth']);
}

if ((defined(
    'IN_WS'
) && isset($_REQUEST['method']) && $_REQUEST['method'] == 'pwg.images.uploadAsync' && isset($_POST['username']) && isset($_POST['password'])) && ! FunctionsUser::try_log_user(
    $_POST['username'],
    $_POST['password'],
    false
)) {
    require_once(__DIR__ . '/../inc/ws_init.inc.php');
    $service->sendResponse(new Error(999, 'Invalid username/password'));
    exit();
}

$user = FunctionsUser::build_user(
    $user['id'],
    (defined('IN_ADMIN') && IN_ADMIN) ? false : true // use cache ?
);
if ($conf['browser_language'] && (FunctionsUser::is_a_guest() || FunctionsUser::is_generic()) && ($language = FunctionsUser::get_browser_language())) {
    $user['language'] = $language;
}

FunctionsPlugins::trigger_notify('user_init', $user);
