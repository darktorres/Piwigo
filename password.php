<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_db_real_escape_string;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\dbLayer\single_update;
use function Piwigo\inc\deactivate_password_reset_key;
use function Piwigo\inc\deactivate_user_auth_keys;
use function Piwigo\inc\flush_page_messages;
use function Piwigo\inc\generate_key;
use function Piwigo\inc\get_gallery_home_url;
use function Piwigo\inc\get_pwg_token;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\get_userid;
use function Piwigo\inc\get_userid_by_email;
use function Piwigo\inc\getuserdata;
use function Piwigo\inc\is_a_guest;
use function Piwigo\inc\is_generic;
use function Piwigo\inc\l10n;
use function Piwigo\inc\pwg_mail;
use function Piwigo\inc\pwg_password_hash;
use function Piwigo\inc\pwg_password_verify;
use function Piwigo\inc\redirect;
use function Piwigo\inc\set_make_full_url;
use function Piwigo\inc\trigger_change;
use function Piwigo\inc\trigger_notify;
use function Piwigo\inc\unset_make_full_url;
use const Piwigo\inc\ACCESS_FREE;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');
include_once(PHPWG_ROOT_PATH . 'inc/functions_mail.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_FREE);

trigger_notify('loc_begin_password');

check_input_parameter('action', $_GET, false, '/^(lost|reset|none)$/');

// +-----------------------------------------------------------------------+
// | Functions                                                             |
// +-----------------------------------------------------------------------+

/**
 * checks the validity of input parameters, fills $page['errors'] and
 * $page['infos'] and send an email with confirmation link
 *
 * @return bool (true if email was sent, false otherwise)
 */
function process_password_request(): bool
{
    global $page, $conf;

    if (empty($_POST['username_or_email'])) {
        $page['errors'][] = l10n('Invalid username or email');
        return false;
    }

    $user_id = get_userid_by_email($_POST['username_or_email']);

    if (! is_numeric($user_id)) {
        $user_id = get_userid($_POST['username_or_email']);
    }

    if (! is_numeric($user_id)) {
        $page['errors'][] = l10n('Invalid username or email');
        return false;
    }

    $userdata = getuserdata($user_id);

    // password request is not possible for guest/generic users
    $status = $userdata['status'];
    if (is_a_guest($status) || is_generic($status)) {
        $page['errors'][] = l10n('Password reset is not allowed for this user');
        return false;
    }

    if (empty($userdata['email'])) {
        $page['errors'][] = l10n(
            'User "%s" has no email address, password reset is not possible',
            $userdata['username']
        );
        return false;
    }

    $activation_key = generate_key(20);

    [$expire] = pwg_db_fetch_row(pwg_query('SELECT ADDDATE(NOW(), INTERVAL 1 HOUR)'));

    single_update(
        USER_INFOS_TABLE,
        [
            'activation_key' => pwg_password_hash($activation_key),
            'activation_key_expire' => $expire,
        ],
        [
            'user_id' => $user_id,
        ]
    );

    $userdata['activation_key'] = $activation_key;

    set_make_full_url();

    $message = l10n('Someone requested that the password be reset for the following user account:') . "\r\n\r\n";
    $message .= l10n(
        'Username "%s" on gallery %s',
        $userdata['username'],
        get_gallery_home_url()
    );
    $message .= "\r\n\r\n";
    $message .= l10n('To reset your password, visit the following address:') . "\r\n";
    $message .= get_root_url() . 'password.php?key=' . $activation_key . '-' . urlencode((string) $userdata['email']);
    $message .= "\r\n\r\n";
    $message .= l10n('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n";

    unset_make_full_url();

    $message = trigger_change('render_lost_password_mail_content', $message);

    $email_params = [
        'subject' => '[' . $conf['gallery_title'] . '] ' . l10n('Password Reset'),
        'content' => $message,
        'email_format' => 'text/plain',
    ];

    if (pwg_mail($userdata['email'], $email_params)) {
        $page['infos'][] = l10n('Check your email for the confirmation link');
        return true;
    }

    $page['errors'][] = l10n('Error sending email');
    return false;

}

/**
 *  checks the activation key: does it match the expected pattern? is it
 *  linked to a user? is this user allowed to reset his password?
 *
 * @return mixed (user_id if OK, false otherwise)
 */
function check_password_reset_key(
    $reset_key
): mixed {
    global $page, $conf;

    [$key, $email] = explode('-', (string) $reset_key, 2);

    if (! preg_match('/^[a-z0-9]{20}$/i', $key)) {
        $page['errors'][] = l10n('Invalid key');
        return false;
    }

    $user_ids = [];

    $query = '
SELECT
  ' . $conf['user_fields']['id'] . ' AS id
  FROM ' . USERS_TABLE . '
  WHERE ' . $conf['user_fields']['email'] . " = '" . pwg_db_real_escape_string($email) . '\'
;';
    $user_ids = query2array($query, null, 'id');

    if (count($user_ids) == 0) {
        $page['errors'][] = l10n('Invalid username or email');
        return false;
    }

    $user_id = null;

    $query = '
SELECT
    user_id,
    status,
    activation_key,
    activation_key_expire,
    NOW() AS dbnow
  FROM ' . USER_INFOS_TABLE . '
  WHERE user_id IN (' . implode(',', $user_ids) . ')
;';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if (pwg_password_verify($key, $row['activation_key'])) {
            if (strtotime((string) $row['dbnow']) > strtotime((string) $row['activation_key_expire'])) {
                // key has expired
                $page['errors'][] = l10n('Invalid key');
                return false;
            }

            if (is_a_guest($row['status']) || is_generic($row['status'])) {
                $page['errors'][] = l10n('Password reset is not allowed for this user');
                return false;
            }

            $user_id = $row['user_id'];
        }
    }

    if (empty($user_id)) {
        $page['errors'][] = l10n('Invalid key');
        return false;
    }

    return $user_id;
}

/**
 * checks the passwords, checks that user is allowed to reset his password,
 * update password, fills $page['errors'] and $page['infos'].
 *
 * @return bool (true if password was reset, false otherwise)
 */
function reset_password(): bool
{
    global $page, $conf;

    if ($_POST['use_new_pwd'] != $_POST['passwordConf']) {
        $page['errors'][] = l10n('The passwords do not match');
        return false;
    }

    if (! isset($_GET['key'])) {
        $page['errors'][] = l10n('Invalid key');
    }

    $user_id = check_password_reset_key($_GET['key']);

    if (! is_numeric($user_id)) {
        return false;
    }

    single_update(
        USERS_TABLE,
        [
            $conf['user_fields']['password'] => $conf['password_hash']($_POST['use_new_pwd']),
        ],
        [
            $conf['user_fields']['id'] => $user_id,
        ]
    );

    deactivate_password_reset_key($user_id);
    deactivate_user_auth_keys($user_id);

    $page['infos'][] = l10n('Your password has been reset');
    $page['infos'][] = '<a href="' . get_root_url() . 'identification.php">' . l10n('Login') . '</a>';

    return true;
}

// +-----------------------------------------------------------------------+
// | Process form                                                          |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit'])) {
    check_pwg_token();

    if ($_GET['action'] == 'lost' && process_password_request()) {
        $page['action'] = 'none';
    }

    if ($_GET['action'] == 'reset' && reset_password()) {
        $page['action'] = 'none';
    }
}

// +-----------------------------------------------------------------------+
// | key and action                                                        |
// +-----------------------------------------------------------------------+

// a connected user can't reset the password from a mail
if (isset($_GET['key']) && ! is_a_guest()) {
    unset($_GET['key']);
}

if (isset($_GET['key']) && ! isset($_POST['submit'])) {
    $user_id = check_password_reset_key($_GET['key']);
    if (is_numeric($user_id)) {
        $userdata = getuserdata($user_id);
        $page['username'] = $userdata['username'];
        $template->assign('key', $_GET['key']);

        if (! isset($page['action'])) {
            $page['action'] = 'reset';
        }
    } else {
        $page['action'] = 'none';
    }
}

if (! isset($page['action'])) {
    if (! isset($_GET['action'])) {
        $page['action'] = 'lost';
    } elseif (in_array($_GET['action'], ['lost', 'reset', 'none'])) {
        $page['action'] = $_GET['action'];
    }
}

if ($page['action'] == 'reset' && ! isset($_GET['key']) && (is_a_guest() || is_generic())) {
    redirect(get_gallery_home_url());
}

if ($page['action'] == 'lost' && ! is_a_guest()) {
    redirect(get_gallery_home_url());
}

// +-----------------------------------------------------------------------+
// | template initialization                                               |
// +-----------------------------------------------------------------------+

$title = l10n('Password Reset');
if ($page['action'] == 'lost') {
    $title = l10n('Forgot your password?');

    if (isset($_POST['username_or_email'])) {
        $template->assign('username_or_email', htmlspecialchars(stripslashes((string) $_POST['username_or_email'])));
    }
}

$page['body_id'] = 'thePasswordPage';

$template->set_filenames([
    'password' => 'password.tpl',
]);
$template->assign(
    [
        'title' => $title,
        'form_action' => get_root_url() . 'password.php',
        'action' => $page['action'],
        'username' => $page['username'] ?? $user['username'],
        'PWG_TOKEN' => get_pwg_token(),
    ]
);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('thePasswordPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'inc/menubar.inc.php');
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

include(PHPWG_ROOT_PATH . 'inc/page_header.php');
trigger_notify('loc_end_password');
flush_page_messages();
$template->pparse('password');
include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
